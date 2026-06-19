<?php

declare(strict_types=1);

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\POS\AccountingService;

class SalesTransactionsController extends Controller
{
    protected AccountingService $acc;

    public function __construct(AccountingService $acc)
    {
        $this->acc = $acc;
    }

    /**
     * إنشاء حركة:
     * - payment        : دفعة تقلّل الرصيد
     * - refund         : استرداد يقلّل الرصيد (ل voucher غير مستخدم)
     * - adjust_invoice : تعديل فاتورة قديمة برقمها (تطبيق فرق التأثير الآن)
     */
    public function store(Request $request)
    {
        // صلاحيات الأدمن
        $userData = $request->session()->get('auth_user', []);
        if ((int)($userData['is_admin'] ?? 0) !== 1) {
            return back()->with('error', '🚫 غير مصرح')->with('flash_tab', '#transactions');
        }

        $type = (string) $request->input('type');

        // فاليديشن أساسي
        $baseRules = [
            'user_id' => ['required','integer'],
            'type'    => ['required','in:payment,refund,adjust_invoice'],
            'note'    => ['nullable','string','max:255'],
        ];

        // فروع الفاليديشن حسب النوع
        if ($type === 'refund') {
            $rules = $baseRules + [
                'voucher_ref' => ['required','string','max:64'],
            ];
        } elseif ($type === 'adjust_invoice') {
            $rules = $baseRules + [
                'adjust_doc_code' => ['required','string','max:64'], // رقم الفاتورة القديمة (doc_code)
                'new_amount'      => ['required','numeric','min:0'], // المبلغ الجديد المرغوب
            ];
        } else { // payment
            $rules = $baseRules + [
                'amount' => ['required','numeric','min:0'],
            ];
        }

        $data = $request->validate($rules);

        try {
            if ($type === 'refund') {
                return $this->handleRefund(
                    $request,
                    (int) $data['user_id'],
                    (string) $data['voucher_ref'],
                    (string) ($data['note'] ?? '')
                );
            }

            if ($type === 'adjust_invoice') {
                return $this->adjustInvoice(
                    userId: (int) $data['user_id'],
                    oldDocCode: (string) $data['adjust_doc_code'],
                    newAmount: (float) $data['new_amount'],
                    extraNote: (string) ($data['note'] ?? '')
                );
            }

            // ====== Payment (العادي) ======
            $now       = now('Asia/Amman');
            $periodKey = $now->format('Y-m');
            $userId    = (int) $data['user_id'];
            $amount    = (float) ($data['amount'] ?? 0);
            $note      = $data['note'] ?? null;

            $newBalance = DB::transaction(function () use ($userId, $periodKey, $amount, $note, $now) {

                // أحدث نقطة بيع للمندوب + قفل
                $sp = DB::table('sales_points')
                    ->where('user_id', $userId)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if (!$sp) {
                    abort(422, 'لا توجد نقطة بيع لهذا المندوب. أضفه أولًا في تبويب نقاط البيع.');
                }

                // تهيئة رصيد الشهر
                $currentBalance = 0.0;
                if (($sp->balance_month_key ?? null) === $periodKey) {
                    $currentBalance = (float) $sp->balance;
                } else {
                    DB::table('sales_points')->where('id', $sp->id)->update([
                        'balance' => 0,
                        'balance_month_key' => $periodKey,
                        'updated_at' => $now,
                    ]);
                    $currentBalance = 0.0;
                }

                $before = $currentBalance;
                $after  = max(0, $before - $amount); // payment يقلّل الرصيد

                $insert = [
                    'user_id'        => $userId,
                    'voucher_id'     => null,
                    'amount'         => $amount,
                    'balance_before' => $before,
                    'balance_after'  => $after,
                    'type'           => 'payment',
                    'note'           => $note,
                    'period_key'     => $periodKey,
                    'created_at'     => $now,
                ];
                if (Schema::hasColumn('sales_transactions', 'doc_code')) {
                    $insert['doc_code'] = $this->acc->nextDocCode('TX');
                }

                DB::table('sales_transactions')->insertGetId($insert);

                DB::table('sales_points')->where('id', $sp->id)->update([
                    'balance' => $after,
                    'balance_month_key' => $periodKey,
                    'updated_at' => $now,
                ]);

                return $after;
            });

            return redirect()
                ->route('pos.sales-points.index', ['tab' => 'transactions'])
                ->with('success', '✅ تم حفظ العملية. الرصيد الحالي: ' . number_format($newBalance, 2) . ' JOD')
                ->with('flash_tab', '#transactions');

        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage() ?: 'فشل حفظ العملية')->with('flash_tab', '#transactions');
        }
    }

    /**
     * تعديل فاتورة قديمة عبر doc_code:
     * - يقرأ الحركة القديمة ويتأكد أنها لنفس المندوب.
     * - يحسب الفرق (new_amount - old_amount).
     * - يحوّل الفرق إلى تأثير على الرصيد الآن:
     *     oldType=payment  ⇒ signOld=-1
     *     oldType=adjustment ⇒ signOld=+1
     *   effectOnBalance = signOld * delta
     * - يسجّل سطر جديد type = 'adjust_invoice' بمبلغ |delta| ويطبّق before/after.
     * - يضيف Note مفصّلة (doc_code، القديم/الجديد/الفرق).
     */
    private function adjustInvoice(int $userId, string $oldDocCode, float $newAmount, string $extraNote = '')
    {
        $now       = now('Asia/Amman');
        $periodKey = $now->format('Y-m');

        try {
            $afterBalance = DB::transaction(function () use ($userId, $oldDocCode, $newAmount, $periodKey, $extraNote, $now) {

                // 1) جلب الفاتورة القديمة
                $oldTx = DB::table('sales_transactions')
                    ->where('doc_code', $oldDocCode)
                    ->lockForUpdate()
                    ->first();

                if (!$oldTx) {
                    abort(422, 'رقم الفاتورة القديمة غير موجود.');
                }
                if ((int) $oldTx->user_id !== $userId) {
                    abort(422, 'هذه الفاتورة لا تخص المندوب المحدد.');
                }

                $oldType   = (string) $oldTx->type;
                $oldAmount = (float) $oldTx->amount;

                if (!in_array($oldType, ['payment','adjustment'], true)) {
                    abort(422, 'لا يمكن تعديل هذا النوع من الفواتير (مسموح: دفع/تسوية فقط).');
                }

                $newAmount = max(0, $newAmount);
                $delta     = round($newAmount - $oldAmount, 2);

                if ($delta == 0.0) {
                    abort(422, 'المبلغ الجديد يساوي القديم — لا يوجد فرق لتطبيقه.');
                }

                // 2) أحدث نقطة بيع + قفل
                $sp = DB::table('sales_points')
                    ->where('user_id', $userId)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if (!$sp) abort(422, 'لا توجد نقطة بيع لهذا المندوب. أضفه أولًا.');

                // تهيئة رصيد الشهر
                $currentBalance = 0.0;
                if (($sp->balance_month_key ?? null) === $periodKey) {
                    $currentBalance = (float) $sp->balance;
                } else {
                    DB::table('sales_points')->where('id', $sp->id)->update([
                        'balance' => 0,
                        'balance_month_key' => $periodKey,
                        'updated_at' => $now,
                    ]);
                }

                // 3) حساب تأثير الفرق الآن
                $signOld         = ($oldType === 'payment') ? -1 : +1;
                $effectOnBalance = $signOld * $delta;            // موجب = زيادة رصيد، سالب = تقليل رصيد
                $diffAbs         = abs($delta);

                $before = $currentBalance;
                $after  = $before + $effectOnBalance;
                if ($after < 0) $after = 0;

                // 4) ملاحظة مفصّلة
                $detail = sprintf(
                    'تعديل على فاتورة قديمة %s — قديم: %.2f — جديد: %.2f — فرق: %+0.2f',
                    $oldDocCode, $oldAmount, $newAmount, $delta
                );
                $note = trim($detail . ($extraNote ? (' — ' . $extraNote) : ''));

                // 5) إدراج سطر تعديل فاتورة (نوع موحّد adjust_invoice)
                $insert = [
                    'user_id'        => (int) $userId,
                    'voucher_id'     => null,
                    'amount'         => $diffAbs,
                    'balance_before' => $before,
                    'balance_after'  => $after,
                    'type'           => 'adjust_invoice',
                    'note'           => $note,
                    'period_key'     => $periodKey,
                    'created_at'     => $now,
                ];
                if (Schema::hasColumn('sales_transactions', 'doc_code')) {
                    $insert['doc_code'] = $this->acc->nextDocCode('TX');
                }

                DB::table('sales_transactions')->insertGetId($insert);

                // 6) تحديث الرصيد
                DB::table('sales_points')->where('id', $sp->id)->update([
                    'balance' => $after,
                    'balance_month_key' => $periodKey,
                    'updated_at' => $now,
                ]);

                return $after;
            });

            return redirect()
                ->route('pos.sales-points.index', ['tab' => 'transactions'])
                ->with('success', '✅ تم تطبيق تعديل الفاتورة. الرصيد الحالي: ' . number_format($afterBalance, 2) . ' JOD')
                ->with('flash_tab', '#transactions');

        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage() ?: 'فشل تعديل الفاتورة')->with('flash_tab', '#transactions');
        }
    }
    public function adjust(Request $request, int $id)
    {
    // صلاحيات الأدمن
    $userData = $request->session()->get('auth_user', []);
    if ((int)($userData['is_admin'] ?? 0) !== 1) {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['status'=>'error','message'=>'غير مصرح'], 403);
        }
        return redirect()->route('pos.sales-points.index', ['tab'=>'transactions'])->with('error','🚫 غير مصرح');
    }

    // تحقق المدخلات
    $data = $request->validate([
        'new_amount' => ['required','numeric','min:0'],
        'note'       => ['nullable','string','max:255'],
    ]);

    $periodKey = now()->format('Y-m');

    try {
        $afterBalance = \DB::transaction(function () use ($id, $data, $periodKey) {

            // جلب الحركة الأصلية وقفلها
            $orig = \DB::table('sales_transactions')
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$orig) {
                abort(404, 'الحركة الأصلية غير موجودة');
            }

            // نسمح بتعديل دفعات فقط (منطق المودال الحالي)
            if ($orig->type !== 'payment') {
                abort(422, 'لا يمكن تعديل هذا النوع. يُسمح بتعديل الدفعات فقط.');
            }

            $userId    = (int) $orig->user_id;
            $oldAmount = (float) $orig->amount;
            $newAmount = (float) $data['new_amount'];
            $delta     = round($newAmount - $oldAmount, 2);

            if ($delta == 0.0) {
                // لا تغيير
                return optional(\DB::table('sales_points')
                    ->where('user_id', $userId)
                    ->orderByDesc('id')
                    ->first())->balance ?? 0.0;
            }

            // أحدث نقطة بيع للمندوب + قفل
            $sp = \DB::table('sales_points')
                ->where('user_id', $userId)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (!$sp) {
                abort(422, 'لا توجد نقطة بيع لهذا المندوب. أضفه أولاً.');
            }

            // تهيئة/تصفير رصيد الشهر عند تغيّر الشهر
            $currentBalance = 0.0;
            if (($sp->balance_month_key ?? null) === $periodKey) {
                $currentBalance = (float) $sp->balance;
            } else {
                \DB::table('sales_points')->where('id', $sp->id)->update([
                    'balance' => 0,
                    'balance_month_key' => $periodKey,
                    'updated_at' => now(),
                ]);
            }

            $before = $currentBalance;
            $after  = $currentBalance;

            // تفسير الفرق:
            // delta > 0  => دفعة إضافية (ينقص الرصيد)
            // delta < 0  => تقليل دفعة (يزيد الرصيد)
            $abs = abs($delta);
            if ($delta > 0) {
                $after = max(0, $before - $abs);
            } else { // $delta < 0
                $after = max(0, $before + $abs);
            }

            // إعداد الملاحظة
            $oldDoc = $orig->doc_code ?? null;
            $note = 'تعديل على فاتورة قديمة'
                  . ($oldDoc ? (' ' . $oldDoc) : '')
                  . ' — قديم: ' . number_format($oldAmount, 2)
                  . ' — جديد: ' . number_format($newAmount, 2)
                  . ' — فرق: ' . number_format($delta, 2);

            if (!empty($data['note'])) {
                $note .= ' — ' . trim($data['note']);
            }

            // إدراج قيد الفرق بنوع adjust_invoice
            $insert = [
                'user_id'        => $userId,
                'voucher_id'     => null,
                'amount'         => $abs,                 // نسجّل الفرق المطلق
                'balance_before' => $before,
                'balance_after'  => $after,
                'type'           => 'adjust_invoice',
                'note'           => $note,
                'period_key'     => $periodKey,
                'created_at'     => now(),
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('sales_transactions', 'doc_code')) {
                $insert['doc_code'] = $this->acc->nextDocCode('TX');
            }

            \DB::table('sales_transactions')->insertGetId($insert);

            // تحديث رصيد المندوب
            \DB::table('sales_points')->where('id', $sp->id)->update([
                'balance' => $after,
                'balance_month_key' => $periodKey,
                'updated_at' => now(),
            ]);

            return $after;
        });

        // استجابة
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['status'=>'success','balance'=>round((float)$afterBalance,2)]);
        }

        return redirect()
            ->route('pos.sales-points.index', ['tab' => 'transactions'])
            ->with('success', '✅ تم تطبيق الفرق بنجاح. الرصيد الحالي: ' . number_format((float)$afterBalance, 2) . ' JOD')
            ->with('flash_tab', '#transactions');

    } catch (\Throwable $e) {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['status'=>'error','message'=>$e->getMessage() ?: 'فشل التعديل'], 422);
        }
        return back()->with('error', $e->getMessage() ?: 'فشل التعديل')->with('flash_tab', '#transactions');
    }
}

    /**
     * منطق الاسترداد Refund (كما هو)
     */
    private function handleRefund(Request $request, int $userId, string $voucherRef, string $extraNote = '')
    {
        $now       = now('Asia/Amman');
        $periodKey = $now->format('Y-m');

        try {
            $afterBalance = DB::transaction(function () use ($userId, $voucherRef, $periodKey, $extraNote, $now) {

                // أحدث نقطة بيع + قفل
                $sp = DB::table('sales_points')
                    ->where('user_id', $userId)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if (!$sp) abort(422, 'لا توجد نقطة بيع لهذا المندوب. أضفه أولًا.');

                // جلب الفاوتشر محليًا (قفل)
                $voucher = DB::table('vouchers')
                    ->where(function($q) use ($voucherRef){
                        if (ctype_digit($voucherRef)) {
                            $q->orWhere('id', (int) $voucherRef);
                        }
                        $q->orWhere('code', $voucherRef);
                    })
                    ->lockForUpdate()
                    ->first();

                if (!$voucher) abort(422, 'الفاوتشر غير موجود محليًا.');

                // تحقق الانتماء
                $belongs = true;
                if (property_exists($voucher, 'issued_by') && !is_null($voucher->issued_by)) {
                    $belongs = ((int)$voucher->issued_by === $userId);
                } elseif (property_exists($voucher, 'issued_by_name') && $voucher->issued_by_name) {
                    $belongs = (trim($voucher->issued_by_name) === trim($sp->user_name));
                }
                if (!$belongs) abort(422, 'هذه الفاتورة لا تعود للمندوب المحدد.');

                if ((int)($voucher->is_cancel ?? 0) === 1) {
                    abort(422, 'الفاوتشر ملغى مسبقًا.');
                }

                $usedLocal = (int)($voucher->is_redeemed ?? 0) === 1;

                // معلومات اختيارية
                $apiUserName = null;
                $platform    = (string) ($voucher->platform ?? '');
                $codeValue   = (string) ($voucher->code ?? (string)$voucherRef);
                if ($platform && $codeValue) {
                    $res = $this->lookupPlatformUser($platform, $codeValue);
                    if (!empty($res['username'])) {
                        $apiUserName = $res['username'];
                    }
                }

                // تهيئة رصيد الشهر
                $currentBalance = 0.0;
                if (($sp->balance_month_key ?? null) === $periodKey) {
                    $currentBalance = (float) $sp->balance;
                } else {
                    DB::table('sales_points')->where('id', $sp->id)->update([
                        'balance' => 0,
                        'balance_month_key' => $periodKey,
                        'updated_at' => $now,
                    ]);
                }

                $netDueTx = DB::table('sales_transactions')
                    ->where('voucher_id', (int) $voucher->id)
                    ->where('type', 'voucher_sale')
                    ->orderBy('amount', 'asc')
                    ->value('amount');

                $netDue = (float) ($netDueTx ?? $voucher->price_amount ?? 0);

                $amount = 0.0;
                $note   = '';

                if ($usedLocal) {
                    $amount = 0.0;
                    $note = 'لا يمكن الاسترداد: الفاوتشر مستخدم'
                        . ($apiUserName ? (' للمستخدم ' . $apiUserName) : '')
                        . ($platform ? (' على منصة ' . $platform) : '')
                        . ($codeValue ? (' (كود: ' . $codeValue . ')') : '');
                } else {
                    $amount = max(0, $netDue);
                    $note = 'استرداد فاتورة'
                        . ($codeValue ? (' (كود: ' . $codeValue . ')') : '')
                        . ($platform ? (' من منصة ' . $platform) : '');

                    DB::table('vouchers')->where('id', $voucher->id)->update([
                        'is_cancel'  => 1,
                        'updated_at' => $now,
                    ]);
                }

                $before = $currentBalance;
                $after  = $currentBalance;
                if ($amount > 0) {
                    $after = max(0, $before - $amount);
                }

                $insert = [
                    'user_id'        => (int) $userId,
                    'voucher_id'     => (int) $voucher->id,
                    'amount'         => $amount,
                    'balance_before' => $before,
                    'balance_after'  => $after,
                    'type'           => 'refund',
                    'note'           => $note . ($extraNote ? (' — ' . $extraNote) : ''),
                    'period_key'     => $periodKey,
                    'created_at'     => $now,
                ];
                if (Schema::hasColumn('sales_transactions', 'doc_code')) {
                    $insert['doc_code'] = $this->acc->nextDocCode('TX');
                }

                DB::table('sales_transactions')->insertGetId($insert);

                DB::table('sales_points')->where('id', $sp->id)->update([
                    'balance' => $after,
                    'balance_month_key' => $periodKey,
                    'updated_at' => $now,
                ]);

                return $after;
            });

            return redirect()
                ->route('pos.sales-points.index', ['tab' => 'transactions'])
                ->with('success', '✅ تم تسجيل الاسترداد. الرصيد الحالي: ' . number_format($afterBalance, 2) . ' JOD')
                ->with('flash_tab', '#transactions');

        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage() ?: 'فشل الاسترداد')->with('flash_tab', '#transactions');
        }
    }

    /**
     * (اختياري) معاينة تعديل الفاتورة: يُعيد المبلغ القديم ونوع الفاتورة للتحقق في الواجهة.
     */
    public function adjustInvoicePreview(Request $request)
    {
        $userData = $request->session()->get('auth_user', []);
        if ((int)($userData['is_admin'] ?? 0) !== 1) {
            return response()->json(['ok'=>false, 'message'=>'غير مصرح'], 403);
        }

        $data = $request->validate([
            'user_id'         => ['required','integer'],
            'adjust_doc_code' => ['required','string','max:64'],
        ]);

        $userId = (int) $data['user_id'];
        $code   = trim($data['adjust_doc_code']);

        $tx = DB::table('sales_transactions')->where('doc_code', $code)->first();
        if (!$tx) {
            return response()->json(['ok'=>false, 'message'=>'رقم الفاتورة غير موجود'], 422);
        }
        if ((int) $tx->user_id !== $userId) {
            return response()->json(['ok'=>false, 'message'=>'الفاتورة لا تخص هذا المندوب'], 422);
        }

        if (!in_array($tx->type, ['payment','adjustment'], true)) {
            return response()->json(['ok'=>false, 'message'=>'لا يمكن تعديل هذا النوع (يسمح بالدفع/التسوية فقط)'], 422);
        }

        return response()->json([
            'ok'         => true,
            'old_amount' => (float) $tx->amount,
            'old_type'   => (string) $tx->type,
        ]);
    }

    /**
     * استدعاء API المنصة لجلب اسم المستخدم (اختياري للعرض فقط)
     */
    private function lookupPlatformUser(string $platform, string $code): array
    {
        $cfg = config('pos.platforms.' . $platform);
        if (!$cfg || empty($cfg['base']) || empty($cfg['verification'])) {
            return [];
        }

        $url = rtrim($cfg['base'], '/') . $cfg['verification'];

        try {
            $res = Http::timeout((int) config('pos.http_timeout', 8))
                ->acceptJson()
                ->get($url, ['code' => $code]);

            if ($res->ok()) {
                $json = (array) $res->json();
                return ['username' => $json['username'] ?? ($json['user'] ?? null)];
            }
        } catch (\Throwable $e) {
            \Log::warning('Platform verification failed', [
                'platform' => $platform,
                'code'     => $code,
                'error'    => $e->getMessage(),
            ]);
        }

        return [];
    }
}

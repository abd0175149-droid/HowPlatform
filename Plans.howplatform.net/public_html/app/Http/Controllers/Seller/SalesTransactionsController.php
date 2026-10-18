<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * حركات محاسبية على رصيد المندوب (للمشرف): دفعة / استرداد بطاقة / تعديل فاتورة.
 * مُرحَّل من www POS؛ الاسترداد يعمل على بطاقة Plans محلّية (لا منصّة فرعية).
 */
class SalesTransactionsController extends Controller
{
    public function __construct(private AccountingService $acc) {}

    public function store(Request $request)
    {
        $type = (string) $request->input('type');

        $base = [
            'user_id' => ['required', 'integer'],
            'type'    => ['required', 'in:payment,refund,adjust_invoice'],
            'note'    => ['nullable', 'string', 'max:255'],
        ];
        if ($type === 'refund') {
            $rules = $base + ['card_ref' => ['required', 'string', 'max:64']];
        } elseif ($type === 'adjust_invoice') {
            $rules = $base + ['adjust_doc_code' => ['required', 'string', 'max:64'], 'new_amount' => ['required', 'numeric', 'min:0']];
        } else {
            $rules = $base + ['amount' => ['required', 'numeric', 'min:0']];
        }
        $data = $request->validate($rules);

        try {
            if ($type === 'refund') {
                $this->handleRefund((int) $data['user_id'], (string) $data['card_ref'], (string) ($data['note'] ?? ''));
                return back()->with('ok', '✅ تم تسجيل الاسترداد.');
            }
            if ($type === 'adjust_invoice') {
                $bal = $this->adjustInvoice((int) $data['user_id'], (string) $data['adjust_doc_code'], (float) $data['new_amount'], (string) ($data['note'] ?? ''));
                return back()->with('ok', '✅ تم تطبيق تعديل الفاتورة. الرصيد: ' . number_format($bal, 2) . ' JOD');
            }
            // payment
            $r = $this->acc->recordTransaction((int) $data['user_id'], 'payment', (float) $data['amount'], $data['note'] ?? null);
            return back()->with('ok', '✅ تم حفظ الدفعة. الرصيد: ' . number_format($r['after'], 2) . ' JOD');
        } catch (\Throwable $e) {
            return back()->with('err', $e->getMessage() ?: 'فشل حفظ العملية');
        }
    }

    /** تعديل قيمة دفعة قديمة عبر مودال (يسجّل فرقًا بنوع adjust_invoice). */
    public function adjust(Request $request, int $id)
    {
        $data = $request->validate([
            'new_amount' => ['required', 'numeric', 'min:0'],
            'note'       => ['nullable', 'string', 'max:255'],
        ]);
        $periodKey = now()->format('Y-m');

        try {
            $after = DB::transaction(function () use ($id, $data, $periodKey) {
                $orig = DB::table('sales_transactions')->where('id', $id)->lockForUpdate()->first();
                if (! $orig) {
                    abort(404, 'الحركة الأصلية غير موجودة.');
                }
                if ($orig->type !== 'payment') {
                    abort(422, 'يُسمح بتعديل الدفعات فقط.');
                }

                $userId    = (int) $orig->user_id;
                $delta     = round((float) $data['new_amount'] - (float) $orig->amount, 2);
                $sp        = DB::table('sales_points')->where('user_id', $userId)->orderByDesc('id')->lockForUpdate()->first();
                if (! $sp) {
                    abort(422, 'لا توجد نقطة بيع لهذا المندوب.');
                }
                $before = ((string) $sp->balance_month_key === $periodKey) ? (float) $sp->balance : 0.0;
                if ($delta == 0.0) {
                    return $before;
                }
                $after = ($delta > 0) ? max(0, $before - abs($delta)) : max(0, $before + abs($delta));

                $note = 'تعديل دفعة ' . ($orig->doc_code ?? '') . ' — قديم: ' . number_format((float) $orig->amount, 2)
                      . ' — جديد: ' . number_format((float) $data['new_amount'], 2) . ' — فرق: ' . number_format($delta, 2)
                      . (! empty($data['note']) ? (' — ' . trim($data['note'])) : '');

                DB::table('sales_transactions')->insert([
                    'user_id' => $userId, 'card_id' => null, 'doc_code' => $this->acc->nextDocCode('TX'),
                    'type' => 'adjust_invoice', 'amount' => abs($delta), 'balance_before' => $before,
                    'balance_after' => $after, 'note' => $note, 'period_key' => $periodKey, 'created_at' => now(),
                ]);
                DB::table('sales_points')->where('id', $sp->id)->update(['balance' => $after, 'balance_month_key' => $periodKey, 'updated_at' => now()]);
                return $after;
            });

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['status' => 'success', 'balance' => round((float) $after, 2)]);
            }
            return back()->with('ok', '✅ تم تطبيق الفرق. الرصيد: ' . number_format((float) $after, 2) . ' JOD');
        } catch (\Throwable $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
            }
            return back()->with('err', $e->getMessage() ?: 'فشل التعديل');
        }
    }

    /** تعديل فاتورة قديمة عبر doc_code (يطبّق فرق التأثير على الرصيد الآن). */
    private function adjustInvoice(int $userId, string $oldDocCode, float $newAmount, string $extraNote = ''): float
    {
        $periodKey = now()->format('Y-m');

        return DB::transaction(function () use ($userId, $oldDocCode, $newAmount, $periodKey, $extraNote) {
            $oldTx = DB::table('sales_transactions')->where('doc_code', $oldDocCode)->lockForUpdate()->first();
            if (! $oldTx) {
                abort(422, 'رقم الفاتورة القديمة غير موجود.');
            }
            if ((int) $oldTx->user_id !== $userId) {
                abort(422, 'هذه الفاتورة لا تخص المندوب المحدد.');
            }
            if (! in_array($oldTx->type, ['payment', 'adjustment'], true)) {
                abort(422, 'لا يمكن تعديل هذا النوع (مسموح: دفع/تسوية).');
            }

            $newAmount = max(0, $newAmount);
            $delta     = round($newAmount - (float) $oldTx->amount, 2);
            if ($delta == 0.0) {
                abort(422, 'المبلغ الجديد يساوي القديم.');
            }

            $sp = DB::table('sales_points')->where('user_id', $userId)->orderByDesc('id')->lockForUpdate()->first();
            if (! $sp) {
                abort(422, 'لا توجد نقطة بيع لهذا المندوب.');
            }
            $before = ((string) $sp->balance_month_key === $periodKey) ? (float) $sp->balance : 0.0;

            $signOld = ($oldTx->type === 'payment') ? -1 : +1;
            $after   = max(0, $before + $signOld * $delta);

            $note = sprintf('تعديل فاتورة %s — قديم: %.2f — جديد: %.2f — فرق: %+0.2f', $oldDocCode, (float) $oldTx->amount, $newAmount, $delta)
                  . ($extraNote ? (' — ' . $extraNote) : '');

            DB::table('sales_transactions')->insert([
                'user_id' => $userId, 'card_id' => null, 'doc_code' => $this->acc->nextDocCode('TX'),
                'type' => 'adjust_invoice', 'amount' => abs($delta), 'balance_before' => $before,
                'balance_after' => $after, 'note' => $note, 'period_key' => $periodKey, 'created_at' => now(),
            ]);
            DB::table('sales_points')->where('id', $sp->id)->update(['balance' => $after, 'balance_month_key' => $periodKey, 'updated_at' => now()]);
            return $after;
        });
    }

    /** استرداد بطاقة لم تُستبدَل بعد (يُلغيها + يُعيد الصافي على رصيد المندوب). */
    private function handleRefund(int $userId, string $cardRef, string $extraNote = ''): void
    {
        $periodKey = now()->format('Y-m');

        DB::transaction(function () use ($userId, $cardRef, $periodKey, $extraNote) {
            $sp = DB::table('sales_points')->where('user_id', $userId)->orderByDesc('id')->lockForUpdate()->first();
            if (! $sp) {
                abort(422, 'لا توجد نقطة بيع لهذا المندوب.');
            }

            $card = Card::where(function ($q) use ($cardRef) {
                if (ctype_digit($cardRef)) {
                    $q->orWhere('id', (int) $cardRef);
                }
                $q->orWhere('code', strtoupper(trim($cardRef)));
            })->lockForUpdate()->first();

            if (! $card) {
                abort(422, 'البطاقة غير موجودة.');
            }
            if ((int) $card->issued_by !== $userId) {
                abort(422, 'هذه البطاقة لا تعود للمندوب المحدد.');
            }

            $before = ((string) $sp->balance_month_key === $periodKey) ? (float) $sp->balance : 0.0;

            $netDue = (float) (DB::table('sales_transactions')
                ->where('card_id', $card->id)->where('type', 'card_sale')->value('amount') ?? $card->price_amount ?? 0);

            if ($card->status === 'redeemed') {
                $amount = 0.0;
                $note   = 'لا يمكن الاسترداد: البطاقة مستبدَلة (كود: ' . $card->code . ')';
            } else {
                $amount = max(0, $netDue);
                $note   = 'استرداد بطاقة (كود: ' . $card->code . ')';
                $card->update(['status' => 'disabled']);
            }

            $after = $amount > 0 ? max(0, $before - $amount) : $before;

            DB::table('sales_transactions')->insert([
                'user_id' => $userId, 'card_id' => $card->id, 'doc_code' => $this->acc->nextDocCode('TX'),
                'type' => 'refund', 'amount' => $amount, 'balance_before' => $before, 'balance_after' => $after,
                'note' => $note . ($extraNote ? (' — ' . $extraNote) : ''), 'period_key' => $periodKey, 'created_at' => now(),
            ]);
            DB::table('sales_points')->where('id', $sp->id)->update(['balance' => $after, 'balance_month_key' => $periodKey, 'updated_at' => now()]);
        });
    }

    /** معاينة فاتورة قبل تعديلها (JSON). */
    public function adjustInvoicePreview(Request $request)
    {
        $data = $request->validate([
            'user_id'         => ['required', 'integer'],
            'adjust_doc_code' => ['required', 'string', 'max:64'],
        ]);
        $tx = DB::table('sales_transactions')->where('doc_code', trim($data['adjust_doc_code']))->first();
        if (! $tx) {
            return response()->json(['ok' => false, 'message' => 'رقم الفاتورة غير موجود'], 422);
        }
        if ((int) $tx->user_id !== (int) $data['user_id']) {
            return response()->json(['ok' => false, 'message' => 'الفاتورة لا تخص هذا المندوب'], 422);
        }
        if (! in_array($tx->type, ['payment', 'adjustment'], true)) {
            return response()->json(['ok' => false, 'message' => 'يُسمح بتعديل الدفع/التسوية فقط'], 422);
        }
        return response()->json(['ok' => true, 'old_amount' => (float) $tx->amount, 'old_type' => (string) $tx->type]);
    }
}

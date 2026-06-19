<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use App\Http\Controllers\POS\AccountingService;

class VoucherIssueController extends Controller
{
    public function index(Request $request)
    {
        $platforms = collect(config('pos.platforms', []))
            ->map(fn ($p, $key) => ['key' => $key, 'label' => $p['label'] ?? $key])
            ->values();

        $operator = optional($request->user())->name ?? 'بائع';
        return view('pos.issue', compact('platforms', 'operator'));
    }

public function plans(Request $request, string $platform)
{
    $cfg = config("pos.platforms.{$platform}");
    if (!$cfg) {
        return response()->json(['status' => 'fail', 'message' => 'منصة غير معروفة'], 404);
    }

    $url = rtrim($cfg['base'], '/') . $cfg['plans'];

    try {
        $res = \Illuminate\Support\Facades\Http::timeout(config('pos.http_timeout', 8))
            ->acceptJson()
            ->get($url);

        if (!$res->ok()) {
            return response()->json(['status' => 'fail', 'message' => 'تعذر جلب الخطط'], 502);
        }

        $remote = $res->json();
        $list = [];

        if (is_array($remote)) {
            if (array_is_list($remote)) {
                // الحالة: الاستجابة مصفوفة خطط مباشرة
                $list = $remote;
            } elseif (isset($remote['data']) && is_array($remote['data'])) {
                $list = $remote['data'];
            } elseif (isset($remote['plans'])) {
                $node = $remote['plans'];
                if (is_array($node)) {
                    if (array_is_list($node)) $list = $node;
                    elseif (isset($node['data']) && is_array($node['data'])) $list = $node['data'];
                }
            }
        }

        // تأكد أنها مصفوفة عناصر
        $list = array_values(array_filter($list, 'is_array'));

        return response()->json(
            ['status' => 'success', 'plans' => $list],
            200,
            [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

    } catch (\Throwable $e) {
        return response()->json(['status' => 'fail', 'message' => 'فشل الاتصال بالمنصة'], 504);
    }
}


    /**
     * توليد كود فاوتشر: 3 أحرف (A-Za-z) + 6 أرقام مشتقة من الوقت بشكل غير مباشر.
     * مثال: AbZ493027
     */
    protected function generateVoucherCode(): string
    {
        // 3 أحرف عشوائية (كب/سمول)
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $letters = '';
        for ($i = 0; $i < 3; $i++) {
            $letters .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        // 6 أرقام: اعتماد غير مباشر على الوقت (microtime + random + crc32) ثم modulus 1e6
        $micros = (int) floor(microtime(true) * 1_000_000);
        $seed   = $micros ^ random_int(100_000, 999_999) ^ crc32(uniqid('', true));
        $digits = (string) ($seed % 1_000_000);
        $digits = str_pad($digits, 6, '0', STR_PAD_LEFT);

        return $letters . $digits;
    }

    /**
     * تحقّق محلي من فرادة الكود (قاعدة بيانات لارفيل) قبل أي إرسال للمنصة الفرعية.
     */
    protected function codeExistsLocal(string $code): bool
    {
        return DB::table('vouchers')->where('code', $code)->exists();
    }

    public function store(Request $request, AccountingService $acc)
    {
        // تأكد من وجود بائع (الميدلوير pos.access يمنع غير المخوّلين أصلًا)
        $user = $request->user();
        $issuedBy = $user->id ?? null;
        if (!$issuedBy) {
            return response()->json([
                'status'  => 'fail',
                'message' => 'غير مصرح: يجب تسجيل الدخول كبائع قبل إصدار الفاوتشر.'
            ], 401);
        }
    
        // التحقق
        $request->validate([
            'platform'       => ['required', Rule::in(array_keys(config('pos.platforms', [])))],
            'plan_id'        => 'required|integer',
            'plan_name'      => 'required|string|max:255',
            'price_amount'   => 'required|numeric|min:0',
            'price_currency' => 'nullable|string|max:10',
            'duration_days'  => 'required|integer|min:1|max:1095',
            'buyer_name'     => 'required|string|max:255',
            'buyer_email'    => 'nullable|email|max:255',
            'buyer_phone'    => 'nullable|string|max:50',
            'notes'          => 'nullable|string|max:2000',
        ]);
    
        $platform = (string) $request->input('platform');
        $cfg      = config("pos.platforms.{$platform}");
        if (!$cfg) {
            return response()->json(['status' => 'fail', 'message' => 'منصة غير معروفة'], 422);
        }
    
        $planId        = (int) $request->integer('plan_id');
        $planName      = (string) $request->input('plan_name');
        $durationDays  = (int)   $request->integer('duration_days');
        $priceAmount   = (float) $request->input('price_amount');
        $priceCurrency = (string)$request->input('price_currency', 'JOD');
    
        $buyerName  = (string) $request->input('buyer_name');
        $buyerEmail = $request->input('buyer_email');
        $buyerPhone = $request->input('buyer_phone');
        $notes      = $request->input('notes');
    
        $issuedAt = now();
        $expires  = now()->addDays($durationDays);
    
        // روابط/إعدادات المنصّة الفرعية
        $remoteUrl = rtrim($cfg['base'], '/') . ($cfg['vouchers'] ?? '/api/v2/vouchers');
        $verifyUrl = rtrim($cfg['base'], '/') . '/voucher/verify?code=';
    
        // ==== [A] فحص السقف الشهري قبل أي اتصال بالمنصة الفرعية ====
        $periodKey = now()->format('Y-m');
    
        // جلب نقطة البيع للمندوب
        $sp = DB::table('sales_points')
            ->where('user_id', $issuedBy)
            ->orderByDesc('id')
            ->first();
    
        if (!$sp) {
            return response()->json([
                'status'  => 'fail',
                'message' => 'لا توجد نقطة بيع للمندوب الحالي. الرجاء إضافته أولًا من تبويب نقاط البيع.'
            ], 422);
        }
    
        // رصيد الشهر الجاري (لو المفتاح مختلف نعتبر الرصيد 0 بدون تعديل القاعدة هنا)
        $currentBalance = ((string)($sp->balance_month_key ?? '') === $periodKey)
            ? (float) $sp->balance
            : 0.0;
    
        // صافي المستحق على المندوب بعد الخصم (عمولته %)
        $discountPercent = (float) ($sp->discount_percent ?? 0);
        $netDue          = round($priceAmount * (1 - $discountPercent / 100), 2);
    
        // السقف الشهري (0 يعني بلا سقف)
        $limit = (float) (DB::table('sales_limits')
            ->where('user_id', $issuedBy)
            ->value('monthly_limit') ?? 0);
    
        // المبلغ المتوقع بعد الإصدار
        $projected = $currentBalance + $netDue;
    
        // إن تجاوز السقف، امنع الإصدار وأظهر المبالغ بوضوح
        if ($limit > 0 && $projected > $limit) {
            $available = max(0, $limit - $currentBalance);  // المتاح للإصدار الآن
            $needToPay = $projected - $limit;               // المطلوب تسديده للسماح بهذه البطاقة
            return response()->json([
                'status'  => 'fail',
                'message' => 'لا يمكن إصدار البطاقة: تجاوز السقف الشهري. '
                    . 'المتاح الآن للإصدار: ' . number_format($available, 2) . ' JOD. '
                    . 'المطلوب تسديده أولًا: ' . number_format($needToPay, 2) . ' JOD.',
                'data' => [
                    'period_key'       => $periodKey,
                    'balance'          => round($currentBalance, 2),
                    'discount_percent' => round($discountPercent, 2),
                    'net_due'          => round($netDue, 2),
                    'limit'            => round($limit, 2),
                    'projected'        => round($projected, 2),
                    'available'        => round($available, 2),
                    'need_to_pay'      => round($needToPay, 2),
                ]
            ], 422);
        }
        // ==== [END A] ==================================================
    
        // 1) توليد كود فريد محليًا قبل أي اتصال
        $maxTries  = 8;
        $code      = null;
        for ($i = 0; $i < $maxTries; $i++) {
            $candidate = $this->generateVoucherCode();
            if (!$this->codeExistsLocal($candidate)) {
                $code = $candidate;
                break;
            }
        }
        if (!$code) {
            return response()->json([
                'status'  => 'fail',
                'message' => 'تعذر توليد كود فريد محليًا، أعد المحاولة.'
            ], 500);
        }
    
        // 2) إرسال للمنصّة الفرعية أولًا — دون أي حفظ محلي
        $payload = [
            'code'                 => $code,
            'plan_id'              => $planId,
            'duration_days'        => $durationDays,
            'plan_type_id'         => $planId,   // نملأها بنفس القيمة كما اتفقنا
            'buyer_name'           => $buyerName,
            'buyer_email'          => $buyerEmail,
            'buyer_phone'          => $buyerPhone,
            'notes'                => $notes,
            'issued_at'            => $issuedAt->format('Y-m-d H:i:s'),
            'expires_at'           => $expires->format('Y-m-d H:i:s'),
            'is_redeemed'          => 0,
            'redeemed_by_user_id'  => null,
            'redeemed_at'          => null,
            'redeemed_ip'          => null,
            'redeemed_ua'          => null,
        ];
    
        $lastError = null;
        try {
            $remote = Http::timeout(config('pos.http_timeout', 10))
                ->acceptJson()
                ->post($remoteUrl, $payload);
    
            if (!$remote->successful()) {
                // في حال تعارض/تكرار على الفرعي جرّب أكواد أخرى بسرعة (ضمن حد المحاولات)
                if (in_array($remote->status(), [409, 422])) {
                    for ($i = 0; $i < $maxTries; $i++) {
                        $candidate = $this->generateVoucherCode();
                        if ($this->codeExistsLocal($candidate)) continue;
                        $code = $candidate;
                        $payload['code'] = $code;
                        $remote = Http::timeout(config('pos.http_timeout', 10))
                            ->acceptJson()
                            ->post($remoteUrl, $payload);
                        if ($remote->successful()) break;
                    }
                }
            }
    
            if (!$remote->successful()) {
                $lastError = 'remote_failed_status_' . $remote->status();
                return response()->json([
                    'status'  => 'fail',
                    'message' => 'تعذر المزامنة مع المنصة الفرعية، لم يتم حفظ الفاوتشر. (' . $lastError . ')'
                ], 502);
            }
        } catch (\Throwable $e) {
            $lastError = 'remote_exception_' . $e->getMessage();
            return response()->json([
                'status'  => 'fail',
                'message' => 'تعذر المزامنة مع المنصة الفرعية، لم يتم حفظ الفاوتشر. (' . $lastError . ')'
            ], 502);
        }
    
        // 3) حفظ محلي (محاسبي) بعد نجاح الفرعي فقط
        try {
            DB::beginTransaction();
    
            $row = [
                'code'              => $code,
                'platform'          => $platform,
                'platform_plan_id'  => $planId,
                'plan_name'         => $planName,
                'price_amount'      => $priceAmount,
                'price_currency'    => $priceCurrency,
                'buyer_name'        => $buyerName,
                'buyer_email'       => $buyerEmail,
                'buyer_phone'       => $buyerPhone,
                'notes'             => $notes,
                'issued_by'         => $issuedBy,
                'issued_at'         => $issuedAt,
                'expires_at'        => $expires,
                'is_redeemed'       => 0,
            ];
    
            // أعمدة إضافية إن وُجدت محليًا
            $issuedByName  = $user->name ?? $user->full_name ?? $user->username ?? 'POS';
            $issuedByEmail = $user->email ?? null;
    
            if (Schema::hasColumn('vouchers', 'issued_by_name')) {
                $row['issued_by_name'] = $issuedByName;
            }
            if (Schema::hasColumn('vouchers', 'issued_by_email')) {
                $row['issued_by_email'] = $issuedByEmail;
            }
            if (Schema::hasColumn('vouchers', 'operator_ip')) {
                $row['operator_ip'] = $request->ip();
            }
            if (Schema::hasColumn('vouchers', 'platform_label')) {
                $row['platform_label'] = $cfg['label'] ?? $platform;
            }
    
            $voucherId = DB::table('vouchers')->insertGetId($row);
    
            // تسجّل قيد البيع وتحدّث رصيد المندوب (داخل ترانزاكشن مستقلة داخل الدالة)
            $acc->recordVoucherSale((int)$issuedBy, (int)$voucherId, (float)$priceAmount);
    
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
    
            // محاولة تنظيف الفرعي حتى لا يبقى هناك دون مقابل محلي
            try {
                $cleanup = Http::timeout(6)->acceptJson()->delete($remoteUrl, ['code' => $code]);
                if (!$cleanup->successful()) {
                    $cancelUrl = rtrim($cfg['base'], '/') . ($cfg['vouchers_cancel'] ?? '/api/vouchers/cancel');
                    @Http::timeout(6)->acceptJson()->post($cancelUrl, ['code' => $code]);
                }
            } catch (\Throwable $ee) { /* تجاهل */ }
    
            \Log::error('Voucher local save failed', [
                'code' => $code,
                'error' => $e->getMessage(),
            ]);
    
            return response()->json([
                'status'  => 'fail',
                'message' => 'تم الحفظ في المنصة الفرعية لكن فشل الحفظ المحلي، تمّت محاولة إلغاء العملية.'
            ], 500);
        }
    
        // 4) إرجاع بيانات العرض
        $verifyUrlFull = $verifyUrl . urlencode($code);
    
        return response()->json([
            'status'     => 'success',
            'voucher_id' => $voucherId,
            'code'       => $code,
            'expiry'     => $expires->toDateString(),
            'verify_url' => $verifyUrlFull,
        ], 200);
    }

}
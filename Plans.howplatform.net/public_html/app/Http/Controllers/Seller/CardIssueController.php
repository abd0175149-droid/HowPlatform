<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Plan;
use App\Models\PointPackage;
use App\Services\AccountingService;
use App\Services\VoucherRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * إصدار بطاقة Plans من قِبل بائع (POS).
 * يختار البائع خطة Plans + مدّة + سعر + بيانات المشتري، فتُنشأ بطاقة محلّية
 * (نوع subscription) وتُسجَّل عملية بيع على رصيد البائع الشهري (مع سقف وعمولة).
 * المشتري يستبدل الكود لاحقًا فيُفعَّل اشتراك Plans عبر CardRedemptionService.
 */
class CardIssueController extends Controller
{
    /** أبجدية آمنة بلا أحرف ملتبسة (O/0/I/1/L) — مطابقة لإصدار الأدمن. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function index(Request $request)
    {
        $sellerId = (int) (session('auth_user')['id'] ?? 0);
        $sp = DB::table('sales_points')->where('user_id', $sellerId)->orderByDesc('id')->first();

        $periodKey = now()->format('Y-m');
        $balance   = ($sp && (string) $sp->balance_month_key === $periodKey) ? (float) $sp->balance : 0.0;
        $limit     = (float) (DB::table('sales_limits')->where('user_id', $sellerId)->value('monthly_limit') ?? 0);

        return view('seller.issue', [
            'plans'     => Plan::where('is_active', 1)->orderBy('name')->get(),
            'packages'  => PointPackage::where('is_active', 1)->orderBy('sort_order')->orderBy('points')->get(),
            'salePoint' => $sp,
            'balance'   => $balance,
            'limit'     => $limit,
            'available' => $limit > 0 ? max(0, $limit - $balance) : null,
            'discount'  => $sp ? (float) $sp->discount_percent : 0.0,
        ]);
    }

    public function store(Request $request, AccountingService $acc)
    {
        $sellerId = (int) (session('auth_user')['id'] ?? 0);
        if ($sellerId <= 0) {
            return response()->json(['status' => 'fail', 'message' => 'غير مصرّح: سجّل الدخول كبائع.'], 401);
        }

        $v = $request->validate([
            'type'          => ['nullable', 'in:subscription,points'],
            'plan_id'       => ['nullable', 'integer', 'exists:plans,id'],
            'package_id'    => ['nullable', 'integer', 'exists:point_packages,id'],
            'price_amount'  => ['nullable', 'numeric', 'min:0'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:1095'],
            'buyer_name'    => ['required', 'string', 'max:255'],
            'buyer_email'   => ['nullable', 'email', 'max:255'],
            'buyer_phone'   => ['nullable', 'string', 'max:50'],
            'notes'         => ['nullable', 'string', 'max:2000'],
        ]);

        $type = $v['type'] ?? 'subscription';

        // قيم البطاقة حسب النوع
        $plan         = null;
        $cardType     = 'subscription';
        $planId       = null;
        $points       = 0;
        $durationDays = null;
        $cardLabel    = '';

        if ($type === 'points') {
            // بطاقة نقاط: النقاط وسعرها من سلّم الحزم (لا سعر حرّ).
            if (empty($v['package_id'])) {
                return response()->json(['status' => 'fail', 'message' => 'اختر حزمة نقاط.'], 422);
            }
            $pkg = PointPackage::find((int) $v['package_id']);
            if (! $pkg || ! (int) $pkg->is_active) {
                return response()->json(['status' => 'fail', 'message' => 'حزمة النقاط غير متاحة.'], 422);
            }
            $cardType    = 'points';
            $points      = (int) $pkg->points;
            $priceAmount = (float) $pkg->price_amount;
            $cardLabel   = number_format($pkg->points) . ' نقطة';
        } else {
            // بطاقة اشتراك: خطة + سعر حرّ + مدّة.
            if (empty($v['plan_id'])) {
                return response()->json(['status' => 'fail', 'message' => 'اختر خطة.'], 422);
            }
            if (! isset($v['price_amount'])) {
                return response()->json(['status' => 'fail', 'message' => 'أدخِل السعر.'], 422);
            }
            $plan = Plan::find((int) $v['plan_id']);
            if (! $plan || ! (int) $plan->is_active) {
                return response()->json(['status' => 'fail', 'message' => 'خطة غير متاحة.'], 422);
            }
            $planId       = (int) $plan->id;
            $durationDays = (int) ($v['duration_days'] ?? $plan->duration_days ?? 30);
            $priceAmount  = (float) $v['price_amount'];
            $cardLabel    = $plan->name;
        }

        // ===== فحص السقف الشهري قبل الإنشاء =====
        $sp = DB::table('sales_points')->where('user_id', $sellerId)->orderByDesc('id')->first();
        if (! $sp) {
            return response()->json([
                'status'  => 'fail',
                'message' => 'لا توجد نقطة بيع لحسابك. تواصل مع الإدارة لإضافتها أولًا.',
            ], 422);
        }

        $periodKey      = now()->format('Y-m');
        $currentBalance = ((string) ($sp->balance_month_key ?? '') === $periodKey) ? (float) $sp->balance : 0.0;
        $discountPct    = (float) ($sp->discount_percent ?? 0);
        $netDue         = round($priceAmount * (1 - $discountPct / 100), 2);
        $limit          = (float) (DB::table('sales_limits')->where('user_id', $sellerId)->value('monthly_limit') ?? 0);
        $projected      = $currentBalance + $netDue;

        if ($limit > 0 && $projected > $limit) {
            $available = max(0, $limit - $currentBalance);
            $needToPay = $projected - $limit;
            return response()->json([
                'status'  => 'fail',
                'message' => 'لا يمكن إصدار البطاقة: تجاوز السقف الشهري. '
                    . 'المتاح الآن: ' . number_format($available, 2) . ' JOD. '
                    . 'المطلوب تسديده أولًا: ' . number_format($needToPay, 2) . ' JOD.',
                'data'    => compact('currentBalance', 'discountPct', 'netDue', 'limit', 'projected', 'available', 'needToPay'),
            ], 422);
        }

        // ===== توليد كود فريد + إنشاء البطاقة + قيد البيع (ذرّيًا) =====
        $code = $this->uniqueCode();

        try {
            DB::beginTransaction();

            $card = Card::create([
                'code'           => $code,
                'type'           => $cardType,
                'plan_id'        => $planId,
                'points'         => $points,
                'status'         => 'active',
                'batch'          => 'SELLER-' . now()->format('ym'),
                'expires_at'     => now()->addDays(max((int) $durationDays, 365)), // مهلة استبدال سخيّة
                'issued_by'      => $sellerId,
                'buyer_name'     => $v['buyer_name'],
                'buyer_email'    => $v['buyer_email'] ?? null,
                'buyer_phone'    => $v['buyer_phone'] ?? null,
                'price_amount'   => $priceAmount,
                'price_currency' => 'JOD',
                'duration_days'  => $durationDays,
                'created_by'     => $sellerId,
            ]);

            $acc->recordCardSale($sellerId, (int) $card->id, $priceAmount);

            DB::commit();

            \App\Models\AdminNotification::notify('sale', 'بيع بطاقة ' . $code . ' (' . $cardLabel . ') — ' . number_format($priceAmount, 2) . ' JOD', [
                'icon' => 'fa-cash-register', 'color' => '#f59e0b', 'link' => '/admin/cards?q=' . $code,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Seller card issue failed', ['code' => $code, 'error' => $e->getMessage()]);
            $msg = ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) ? $e->getMessage() : 'تعذّر إصدار البطاقة.';
            return response()->json(['status' => 'fail', 'message' => $msg], 422);
        }

        return response()->json([
            'status'        => 'success',
            'card_id'       => $card->id,
            'code'          => $code,
            'type'          => $cardType,
            'plan'          => $cardLabel,
            'points'        => $points,
            'duration_days' => $durationDays ? ($durationDays . ' يوم') : '—',
            'price'         => number_format($priceAmount, 2),
            'net_due'       => number_format($netDue, 2),
            'expiry'        => optional($card->expires_at)->toDateString(),
        ], 200);
    }

    /** قسيمة بطاقة واحدة كصفحة HTML A4 (البائع لبطاقاته فقط؛ الأدمن لأي بطاقة). */
    public function voucher(Card $card, VoucherRenderer $r)
    {
        $u        = session('auth_user') ?? [];
        $sellerId = (int) ($u['id'] ?? 0);
        $isAdmin  = (int) ($u['is_admin'] ?? 0) === 1;
        if (! $isAdmin && (int) $card->issued_by !== $sellerId) {
            abort(403);
        }
        $card->load('plan');

        return view('cards.voucher', [
            'vouchers' => $r->forCards([$card]),
            'bg'       => asset('templates/voucher_card_bg.png'),
        ]);
    }

    /** قسائم البائع لكل بطاقاته (صفحة A4 لكل بطاقة). */
    public function vouchers(Request $request, VoucherRenderer $r)
    {
        $sellerId = (int) (session('auth_user')['id'] ?? 0);
        $cards    = Card::with('plan')->where('issued_by', $sellerId)->latest('id')->limit(500)->get();

        return view('cards.voucher', [
            'vouchers' => $r->forCards($cards),
            'bg'       => asset('templates/voucher_card_bg.png'),
        ]);
    }

    private function uniqueCode(): string
    {
        do {
            $a = self::ALPHABET; $len = strlen($a); $out = '';
            for ($i = 0; $i < 12; $i++) {
                $out .= $a[random_int(0, $len - 1)];
            }
        } while (Card::where('code', $out)->exists());

        return $out;
    }
}

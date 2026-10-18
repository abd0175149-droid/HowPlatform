<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * تنظيف حساب مستخدم مركزيًا عند حذفه من منصّة (مثل حذف الحساب في diploma).
 * تستهلكها منصّة المصدر عبر X-Api-Key لتجنّب ترك بيانات يتيمة في Plans.
 */
class AccountController extends Controller
{
    public function __construct(private WalletService $wallet) {}

    /**
     * POST /api/v1/account/close {user_id}
     * ذرّي وidempotent: (1) يلغي الاشتراكات الفعّالة/المجدولة → canceled،
     * (2) يُصفّر رصيد المحفظة عبر قيد تعويضي (adjust) يحفظ سلامة الدفتر append-only.
     * لا يحذف صفوف الدفتر (مصدر الحقيقة المالي) — يُصفّر الرصيد فقط مع أثر تدقيقي.
     */
    public function close(Request $request): JsonResponse
    {
        $v = $request->validate(['user_id' => ['required', 'integer', 'min:1']]);
        $userId = (int) $v['user_id'];
        $client = (string) $request->attributes->get('service_client', 'unknown');

        // (1) إلغاء الاشتراكات (idempotent طبيعيًا: الملغى يبقى ملغى).
        $canceled = Subscription::where('user_id', $userId)
            ->whereIn('status', ['active', 'scheduled'])
            ->update(['status' => 'canceled']);

        // (2) تصفير الرصيد بقيد تعويضي (idempotent عبر المفتاح الثابت).
        $balanceBefore = $this->wallet->balance($userId);
        $walletResult  = ['ok' => true, 'skipped' => true, 'balance' => $balanceBefore];

        if ($balanceBefore !== 0) {
            $walletResult = $this->wallet->adjust($userId, -$balanceBefore, [
                'service_client'  => $client,
                'idempotency_key' => 'plans:account:close:' . $userId,
                'endpoint'        => 'account.close',
                'event_type'      => 'plans.account.closed',
                'event_ref_type'  => 'account',
                'event_ref_id'    => (string) $userId,
                'description'     => 'تصفير المحفظة عند حذف الحساب من المنصّة',
            ])['body'];
        }

        return response()->json([
            'ok'                    => true,
            'user_id'               => $userId,
            'subscriptions_canceled' => (int) $canceled,
            'balance_before'        => $balanceBefore,
            'balance_after'         => $this->wallet->balance($userId),
            'wallet'                => $walletResult,
        ]);
    }
}

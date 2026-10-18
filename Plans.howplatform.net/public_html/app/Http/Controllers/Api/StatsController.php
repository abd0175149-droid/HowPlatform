<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WalletService;
use App\Models\Streak;
use App\Models\Referral;
use App\Models\ReferralCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * نقاط قراءة (للدبلوما): ملخّص محفظة المستخدم + قائمة الإحالات.
 * عديمة الأثر — لا تنشئ ولا تعدّل أي صفّ.
 */
class StatsController extends Controller
{
    public function __construct(private WalletService $wallet) {}

    /** GET /api/v1/wallet/stats?user_id= — ملخّص نقاط + تتابع + إحالات + كود. */
    public function walletStats(Request $request): JsonResponse
    {
        $v = $request->validate(['user_id' => ['required', 'integer', 'min:1']]);
        $userId = (int) $v['user_id'];

        $streak = Streak::where('user_id', $userId)->value('current_streak');

        $completed = Referral::where('referrer_id', $userId)
            ->whereIn('status', ['completed', 'rewarded'])
            ->count();
        $rewarded = Referral::where('referrer_id', $userId)
            ->where('status', 'rewarded')
            ->count();
        $pending = Referral::where('referrer_id', $userId)
            ->where('status', 'pending')
            ->count();
        $total = Referral::where('referrer_id', $userId)->count();

        // عديمة الأثر: نُعيد الكود الموجود فقط (لا نُنشئ).
        $code = ReferralCode::where('user_id', $userId)->value('code');

        return response()->json([
            'ok'                   => true,
            'user_id'              => $userId,
            'total_points'         => $this->wallet->balance($userId),
            'current_streak'       => (int) ($streak ?? 0),
            'total_referrals'      => $total,
            'completed_referrals'  => $completed,
            'rewarded_referrals'   => $rewarded,
            'pending_referrals'    => $pending,
            'referral_max_rewarded'=> \App\Services\EarningService::REFERRAL_MAX_REWARDED,
            'referral_code'        => $code,
        ]);
    }

    /** GET /api/v1/referrals?user_id=&limit=&offset= — قائمة إحالات الداعي. */
    public function referrals(Request $request): JsonResponse
    {
        $v = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
            'limit'   => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset'  => ['nullable', 'integer', 'min:0'],
        ]);
        $userId = (int) $v['user_id'];
        $limit  = (int) ($v['limit'] ?? 20);
        $offset = (int) ($v['offset'] ?? 0);

        $base = Referral::where('referrer_id', $userId);
        $total = (clone $base)->count();

        $rows = $base->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get(['referred_id', 'status', 'created_at', 'rewarded_at']);

        $items = $rows->map(fn ($r) => [
            'referred_id'   => (int) $r->referred_id,
            'referred_name' => null, // تُحَلّ الأسماء محليًا في الدبلوما
            'status'        => $r->status,
            'created_at'    => optional($r->created_at)->toIso8601String(),
            'rewarded_at'   => optional($r->rewarded_at)->toIso8601String(),
        ]);

        return response()->json([
            'ok'    => true,
            'total' => $total,
            'items' => $items,
        ]);
    }
}

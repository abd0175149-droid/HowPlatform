<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Services\CardRedemptionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CardsController extends Controller
{
    /** POST /api/v1/cards/redeem — المنصّة المُصادَقة تمرّر user_id للمستخدم المسجّل لديها. */
    public function redeem(Request $request, CardRedemptionService $svc): JsonResponse
    {
        $v = $request->validate([
            'code'    => ['required', 'string', 'max:60'],
            'user_id' => ['required', 'integer', 'min:1'],
        ]);

        $res = $svc->redeem($v['code'], (int) $v['user_id'], 'card');

        return response()->json($res, (int) ($res['code'] ?? 200));
    }

    /**
     * GET /api/v1/cards/verify — معاينة بطاقة لمنصّة الدبلوما قبل الاستبدال.
     * لا تستبدل ولا تغيّر أي شيء — قراءة فقط.
     */
    public function verify(Request $request): JsonResponse
    {
        $v = $request->validate([
            'code' => ['required', 'string', 'max:60'],
        ]);

        $code = CardRedemptionService::normalize($v['code']);

        $card = Card::where('code', $code)->with('plan')->first();

        if (! $card) {
            return response()->json(['found' => false]);
        }

        $expired         = $card->expires_at && $card->expires_at->isPast();
        $alreadyRedeemed = $card->status === 'redeemed';
        $redeemable      = $card->status === 'active' && ! $expired && ! $alreadyRedeemed;

        $plan = null;
        if ($card->plan) {
            $plan = [
                'id'            => (int) $card->plan->id,
                'name'          => $card->plan->name,
                'level'         => (int) $card->plan->level,
                'duration_days' => (int) $card->plan->duration_days,
                'grant_points'  => (int) $card->plan->grant_points,
            ];
        }

        return response()->json([
            'found'            => true,
            'redeemable'       => $redeemable,
            'status'          => $card->status,
            'type'            => $card->type,
            'points'          => (int) $card->points,
            'expires_at'      => $card->expires_at?->toISOString(),
            'already_redeemed' => $alreadyRedeemed,
            'plan'            => $plan,
        ]);
    }
}

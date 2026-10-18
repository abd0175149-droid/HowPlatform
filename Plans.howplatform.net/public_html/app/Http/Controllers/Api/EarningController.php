<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EarningService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EarningController extends Controller
{
    public function __construct(private EarningService $earning) {}

    /** POST /api/v1/earn/login */
    public function login(Request $request): JsonResponse
    {
        $v = $request->validate(['user_id' => ['required', 'integer', 'min:1']]);
        return response()->json($this->earning->recordLogin((int) $v['user_id']));
    }

    /** POST /api/v1/earn/referral */
    public function referral(Request $request): JsonResponse
    {
        $v = $request->validate([
            'referrer_id' => ['required', 'integer', 'min:1'],
            'referred_id' => ['required', 'integer', 'min:1'],
        ]);
        return response()->json($this->earning->recordReferral((int) $v['referrer_id'], (int) $v['referred_id']));
    }

    /** POST /api/v1/earn/referral/complete */
    public function referralComplete(Request $request): JsonResponse
    {
        $v = $request->validate(['referred_id' => ['required', 'integer', 'min:1']]);
        return response()->json($this->earning->completeReferral((int) $v['referred_id']));
    }

    /** GET /api/v1/referral-code?user_id= */
    public function referralCode(Request $request): JsonResponse
    {
        $v = $request->validate(['user_id' => ['required', 'integer', 'min:1']]);
        return response()->json([
            'ok'      => true,
            'user_id' => (int) $v['user_id'],
            'code'    => $this->earning->referralCode((int) $v['user_id']),
        ]);
    }

    /** GET /api/v1/referral-code/resolve?code= — كود إحالة → صاحبه (لتحقّق التسجيل في أوث). */
    public function resolveCode(Request $request): JsonResponse
    {
        $v = $request->validate(['code' => ['required', 'string', 'max:32']]);

        $code = strtoupper(trim($v['code']));
        $userId = \App\Models\ReferralCode::where('code', $code)->value('user_id');

        if (! $userId) {
            return response()->json(['ok' => false, 'error' => 'not_found']);
        }

        return response()->json(['ok' => true, 'user_id' => (int) $userId, 'code' => $code]);
    }
}

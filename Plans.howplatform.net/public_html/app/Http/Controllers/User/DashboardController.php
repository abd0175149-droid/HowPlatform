<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Streak;
use App\Models\WalletLedger;
use App\Services\WalletService;
use App\Services\EntitlementService;
use App\Services\EarningService;

class DashboardController extends Controller
{
    public function index(
        WalletService $wallet,
        EntitlementService $entitlements,
        EarningService $earning
    ) {
        $uid = (int) (session('auth_user')['id'] ?? 0);

        $balance = $wallet->balance($uid);
        $ent     = $entitlements->forUser($uid);

        $streak = (int) (Streak::where('user_id', $uid)->value('current_streak') ?? 0);

        $code = $earning->referralCode($uid);
        $link = 'https://auth.howplatform.net/register?ref=' . $code;

        $recent = WalletLedger::where('user_id', $uid)
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return view('user.dashboard', [
            'balance' => $balance,
            'ent'     => $ent,
            'streak'  => $streak,
            'code'    => $code,
            'link'    => $link,
            'recent'  => $recent,
        ]);
    }
}

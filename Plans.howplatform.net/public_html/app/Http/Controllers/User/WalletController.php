<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\WalletLedger;
use App\Services\WalletService;

class WalletController extends Controller
{
    public function index(WalletService $wallet)
    {
        $uid = (int) (session('auth_user')['id'] ?? 0);

        $balance = $wallet->balance($uid);

        $ledger = WalletLedger::where('user_id', $uid)
            ->orderByDesc('id')
            ->paginate(25);

        return view('user.wallet', [
            'balance' => $balance,
            'ledger'  => $ledger,
        ]);
    }
}

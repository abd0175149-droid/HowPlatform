<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Card;
use App\Models\WalletAccount;
use App\Models\AdminNotification;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $now = now();

        $stats = [
            'plans'          => Plan::where('is_active', 1)->count(),
            'subs_active'    => Subscription::where('status', 'active')
                ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>', $now))->count(),
            'cards_active'   => Card::where('status', 'active')->count(),
            'cards_redeemed' => Card::where('status', 'redeemed')->count(),
            'wallets'        => WalletAccount::count(),
            'points_total'   => (int) WalletAccount::sum('balance'),
        ];

        // مبيعات الشهر الحالي (إن وُجدت جداول المبيعات)
        $salesMonth = 0.0;
        try {
            $salesMonth = (float) DB::table('sales_transactions')
                ->where('type', 'card_sale')->where('period_key', $now->format('Y-m'))
                ->sum('amount');
        } catch (\Throwable $e) {
            // تجاهل
        }

        $recent = AdminNotification::orderByDesc('id')->limit(8)->get();

        return view('admin.dashboard', compact('stats', 'salesMonth', 'recent'));
    }
}

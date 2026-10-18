<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Card;
use App\Models\WalletAccount;
use App\Models\WalletLedger;
use App\Models\PointPackage;
use Illuminate\Support\Facades\DB;

/**
 * تقارير مجمّعة للوحة الأدمن: اشتراكات + بطاقات + محفظة + مبيعات الشهر.
 */
class ReportsController extends Controller
{
    public function index()
    {
        $now       = now();
        $periodKey = $now->format('Y-m');

        // توزيع الاشتراكات الفعّالة حسب الخطة
        $byPlan = Subscription::query()
            ->where('subscriptions.status', 'active')
            ->where(fn ($w) => $w->whereNull('end_at')->orWhere('end_at', '>', $now))
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->groupBy('plans.id', 'plans.name', 'plans.level')
            ->orderByDesc('plans.level')
            ->get([DB::raw('plans.name as plan'), DB::raw('plans.level as level'), DB::raw('COUNT(*) as c')]);

        // مبيعات الشهر الحالي (قيود بيع البطاقات على البائعين)
        $sales = ['count' => 0, 'amount' => 0.0];
        try {
            $row = DB::table('sales_transactions')
                ->where('type', 'card_sale')->where('period_key', $periodKey)
                ->selectRaw('COUNT(*) c, COALESCE(SUM(amount),0) s')->first();
            $sales = ['count' => (int) ($row->c ?? 0), 'amount' => (float) ($row->s ?? 0)];
        } catch (\Throwable $e) {
            // جداول المبيعات قد لا تكون موجودة في بيئة ما — لا تُسقط التقرير
        }

        return view('admin.reports.index', [
            'periodKey' => $periodKey,
            'byPlan'    => $byPlan,
            'sales'     => $sales,
            'cards' => [
                'total'    => Card::count(),
                'active'   => Card::where('status', 'active')->count(),
                'redeemed' => Card::where('status', 'redeemed')->count(),
                'disabled' => Card::where('status', 'disabled')->count(),
                'points'   => Card::where('type', 'points')->count(),
                'sub'      => Card::where('type', 'subscription')->count(),
                'mixed'    => Card::where('type', 'mixed')->count(),
            ],
            'wallet' => [
                'accounts'     => WalletAccount::count(),
                'total_points' => (int) WalletAccount::sum('balance'),
                'ledger'       => WalletLedger::count(),
                'earned'       => (int) WalletLedger::where('points', '>', 0)->sum('points'),
                'spent'        => (int) abs((int) WalletLedger::where('points', '<', 0)->sum('points')),
            ],
            'subs' => [
                'total'      => Subscription::count(),
                'active_now' => Subscription::where('status', 'active')
                    ->where(fn ($w) => $w->whereNull('end_at')->orWhere('end_at', '>', $now))->count(),
            ],
            'plansCount' => Plan::where('is_active', 1)->count(),
            'packages'   => PointPackage::where('is_active', 1)->orderBy('sort_order')->orderBy('points')->get(),
        ]);
    }
}

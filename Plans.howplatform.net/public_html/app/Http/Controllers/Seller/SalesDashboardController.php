<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * لوحات المبيعات:
 *  - index() للمشرف: نظرة عامة (إجماليات + حسب البائع/الخطة) + معاملات + نماذج (دفع/استرداد/تعديل).
 *  - mine()  للبائع: رصيده وبطاقاته ومعاملاته.
 */
class SalesDashboardController extends Controller
{
    public function index(Request $request)
    {
        $periodKey = now()->format('Y-m');

        $totalCards = DB::table('cards')->whereNotNull('issued_by')->count();
        $totalSales = (float) DB::table('cards')->whereNotNull('issued_by')->sum('price_amount');

        // حسب البائع
        $bySeller = DB::table('cards as c')
            ->leftJoin('sales_points as sp', 'sp.user_id', '=', 'c.issued_by')
            ->whereNotNull('c.issued_by')
            ->select('c.issued_by as user_id', DB::raw('MAX(sp.user_name) as name'),
                DB::raw('COUNT(*) as count'), DB::raw('SUM(c.price_amount) as total'))
            ->groupBy('c.issued_by')->orderByDesc('total')->get();

        // حسب الخطة
        $byPlan = DB::table('cards as c')
            ->leftJoin('plans as p', 'p.id', '=', 'c.plan_id')
            ->whereNotNull('c.issued_by')
            ->select(DB::raw('COALESCE(p.name, "—") as plan'), DB::raw('COUNT(*) as count'), DB::raw('SUM(c.price_amount) as total'))
            ->groupBy('p.name')->orderByDesc('total')->get();

        // أحدث صفّ لكل مندوب (للاسم في المعاملات)
        $latest = DB::table('sales_points')->select('user_id', DB::raw('MAX(id) as max_id'))->groupBy('user_id');

        $transactions = DB::table('sales_transactions as t')
            ->leftJoinSub($latest, 'ls', fn ($j) => $j->on('ls.user_id', '=', 't.user_id'))
            ->leftJoin('sales_points as sp', fn ($j) => $j->on('sp.id', '=', 'ls.max_id'))
            ->select('t.*', DB::raw('COALESCE(sp.user_name, "غير معروف") as user_name'))
            ->orderByDesc('t.id')->paginate(15)->withQueryString();

        $sellers = DB::table('sales_points as sp')
            ->joinSub($latest, 'l', fn ($j) => $j->on('sp.id', '=', 'l.max_id'))
            ->select('sp.user_id', 'sp.user_name')->orderBy('sp.user_name')->get();

        return view('seller.dashboard', compact('totalCards', 'totalSales', 'bySeller', 'byPlan', 'transactions', 'sellers', 'periodKey'));
    }

    public function mine(Request $request)
    {
        $userId    = (int) (session('auth_user')['id'] ?? 0);
        $periodKey = now()->format('Y-m');

        $sp        = DB::table('sales_points')->where('user_id', $userId)->orderByDesc('id')->first();
        $balance   = ($sp && (string) $sp->balance_month_key === $periodKey) ? (float) $sp->balance : 0.0;
        $limit     = (float) (DB::table('sales_limits')->where('user_id', $userId)->value('monthly_limit') ?? 0);

        $byPlan = DB::table('cards as c')
            ->leftJoin('plans as p', 'p.id', '=', 'c.plan_id')
            ->where('c.issued_by', $userId)
            ->select(DB::raw('COALESCE(p.name, "—") as plan'), DB::raw('COUNT(*) as count'), DB::raw('SUM(c.price_amount) as total'))
            ->groupBy('p.name')->orderByDesc('total')->get();

        $totalCards = DB::table('cards')->where('issued_by', $userId)->count();
        $totalSales = (float) DB::table('cards')->where('issued_by', $userId)->sum('price_amount');

        $type = trim((string) $request->get('type', ''));
        $q    = trim((string) $request->get('q', ''));
        $tq = DB::table('sales_transactions')->where('user_id', $userId);
        if ($type !== '') {
            $tq->where('type', $type);
        }
        if ($request->filled('from')) {
            $tq->whereDate('created_at', '>=', $request->get('from'));
        }
        if ($request->filled('to')) {
            $tq->whereDate('created_at', '<=', $request->get('to'));
        }
        if ($q !== '') {
            $tq->where(fn ($w) => $w->where('doc_code', 'like', "%{$q}%")->orWhere('note', 'like', "%{$q}%"));
        }
        $transactions = $tq->orderByDesc('created_at')->paginate(12)->withQueryString();

        return view('seller.mine', compact('sp', 'balance', 'limit', 'byPlan', 'totalCards', 'totalSales', 'transactions', 'periodKey'));
    }
}

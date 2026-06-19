<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SalesDashboardController extends Controller
{
    public function index(Request $request)
    {
        // ===== الصلاحيات =====
        $userData = $request->session()->get('auth_user', []);
        if ((int)($userData['is_admin'] ?? 0) !== 1) {
            return redirect('/')->with('error', '🚫 غير مصرح لك بالوصول إلى صفحة المبيعات');
        }

        // ===== تبويب نشط + أحجام الصفحات =====
        $activeTab   = (string) $request->query('tab', 'overview'); // overview|points|limits|transactions
        $periodKey   = $request->query('period', now()->format('Y-m'));

        $perPlatforms = (int) max(5, min(100, (int) $request->query('per_page_platforms', 10)));
        $perSellers   = (int) max(5, min(100, (int) $request->query('per_page_sellers', 10)));
        $perPoints    = (int) max(5, min(100, (int) $request->query('per_page_points', 10)));
        $perLimits    = (int) max(5, min(100, (int) $request->query('per_page_limits', 10)));
        $perTx        = (int) max(5, min(100, (int) $request->query('per_page_tx', 10)));

        session(['pos_active_tab' => $activeTab]);

        // ===== الإحصائيات العامة (Overview) =====
        $totalVouchers = DB::table('vouchers')->count();
        $totalSales    = (float) DB::table('vouchers')->sum('price_amount');

        // منصات - مُرقّمة
        $salesByPlatform = DB::table('vouchers')
            ->select('platform', DB::raw('COUNT(*) AS count'), DB::raw('SUM(price_amount) AS total'))
            ->groupBy('platform')
            ->orderByDesc('total')
            ->paginate($perPlatforms)
            ->appends($request->query())
            ->appends(['tab' => 'overview', 'per_page_platforms' => $perPlatforms]);

        // أفضل البائعين - مُرقّمة
        $salesByUser = DB::table('vouchers')
            ->select('issued_by_name AS name', DB::raw('COUNT(*) AS count'), DB::raw('SUM(price_amount) AS total'))
            ->groupBy('issued_by_name')
            ->orderByDesc('total')
            ->paginate($perSellers)
            ->appends($request->query())
            ->appends(['tab' => 'overview', 'per_page_sellers' => $perSellers]);

        // ===== جلب المندوبين من AUTH (للDDL) =====
        $salesUsers = collect();
        try {
            $base = rtrim((string) config('services.auth.base_url'), '/');
            $url  = $base . '/sales-users';

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . (string) config('services.auth.secret_key'),
                'Accept'        => 'application/json',
            ])->get($url);

            if ($response->ok()) {
                $json = $response->json();
                $salesUsers = collect(is_array($json) ? $json : []);
            } else {
                \Log::error('AUTH /sales-users failed', ['url' => $url, 'status' => $response->status(), 'body' => $response->body()]);
            }
        } catch (\Throwable $e) {
            \Log::error('AUTH /sales-users exception', ['message' => $e->getMessage()]);
        }

        // ===== Subquery: أحدث نقطة بيع لكل user_id =====
        $lastPointSub = DB::table('sales_points as sp2')
            ->select('sp2.user_id', DB::raw('MAX(sp2.id) as max_id'))
            ->groupBy('sp2.user_id');

        // ===== نقاط البيع (points) — Paginate =====
        $salesPoints = DB::table('sales_points')
            ->orderByDesc('id')
            ->paginate($perPoints)
            ->appends($request->query())
            ->appends(['tab' => 'points', 'per_page_points' => $perPoints]);

        $points = $salesPoints;

        // ===== سقوف المبيعات (limits) — Paginate مع اسم المندوب =====
        $salesLimits = DB::table('sales_limits as sl')
            ->leftJoinSub($lastPointSub, 'lp', function ($join) {
                $join->on('lp.user_id', '=', 'sl.user_id');
            })
            ->leftJoin('sales_points as sp', function ($join) {
                $join->on('sp.user_id', '=', 'sl.user_id')
                     ->on('sp.id', '=', 'lp.max_id');
            })
            ->select(
                'sl.id',
                'sl.user_id',
                'sl.monthly_limit',
                'sl.created_at',
                'sl.updated_at',
                DB::raw('COALESCE(sp.user_name, "غير معروف") as user_name')
            )
            ->orderByDesc('sl.updated_at')
            ->paginate($perLimits)
            ->appends($request->query())
            ->appends(['tab' => 'limits', 'per_page_limits' => $perLimits]);

        // ===== DDL المندوبين (Distinct من أحدث أسماء) =====
        $limitUsersSub = DB::table('sales_points')
            ->select('user_id', DB::raw('MAX(id) as max_id'))
            ->groupBy('user_id');

        $limitUsers = DB::table('sales_points as sp')
            ->joinSub($limitUsersSub, 'lu', function($join){
                $join->on('sp.id', '=', 'lu.max_id');
            })
            ->select(DB::raw('sp.user_id as id'), 'sp.user_name as name')
            ->orderBy('sp.user_name')
            ->get();

        // ===== إحصاءات المندوب للHints =====
        $userStats = DB::table('sales_points as sp')
            ->joinSub($limitUsersSub, 'lu', function($join){
                $join->on('sp.id', '=', 'lu.max_id');
            })
            ->leftJoin('sales_limits as sl', 'sl.user_id', '=', 'sp.user_id')
            ->select(
                'sp.user_id',
                'sp.user_name',
                'sp.balance',
                'sp.balance_month_key',
                DB::raw('COALESCE(sl.monthly_limit, 0) as monthly_limit')
            )
            ->orderBy('sp.user_name')
            ->get();

        // ===== العمليات المالية (transactions) — Paginate + اسم المندوب =====
        $salesTransactions = DB::table('sales_transactions as tx')
            ->leftJoinSub($lastPointSub, 'lp', function($join){
                $join->on('lp.user_id', '=', 'tx.user_id');
            })
            ->leftJoin('sales_points as sp', function($join){
                $join->on('sp.id', '=', 'lp.max_id');
            })
            ->when($periodKey, function($q) use ($periodKey){
                $q->where('tx.period_key', $periodKey);
            })
            ->select(
                'tx.*',
                DB::raw('COALESCE(sp.user_name, "غير معروف") as user_name')
            )
            ->orderByDesc('tx.id')
            ->paginate($perTx)
            ->appends($request->query())
            ->appends([
                'tab' => 'transactions',
                'per_page_tx' => $perTx,
                'period' => $periodKey
            ]);

        $methodLabel = function (?string $v) {
            $map = ['online' => 'أونلاين', 'in_person' => 'حضوري', 'both' => 'كلاهما'];
            return $map[$v] ?? ($v ?: '-');
        };

        return view('sales.dashboard', [
            // overview
            'totalVouchers'    => $totalVouchers,
            'totalSales'       => $totalSales,
            'salesByPlatform'  => $salesByPlatform,
            'salesByUser'      => $salesByUser,

            // common lists
            'salesUsers'       => $salesUsers,

            // points
            'points'           => $points,
            'salesPoints'      => $salesPoints,
            'methodLabel'      => $methodLabel,

            // limits
            'salesLimits'      => $salesLimits,
            'limitUsers'       => $limitUsers,

            // transactions
            'salesTransactions'=> $salesTransactions,
            'userStats'        => $userStats,

            // ui helpers
            'activeTab'        => $activeTab,
            'periodKey'        => $periodKey,
            'perPoints'        => $perPoints,
            'perTx'            => $perTx,
            'perPlatforms'     => $perPlatforms,
            'perSellers'       => $perSellers,
            'perLimits'        => $perLimits,
        ]);
    }
}

<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesPersonDashboardController extends Controller
{
    public function index(Request $request)
    {
        // تحقق من بيانات السيشن والصلاحيات
        $userData = $request->session()->get('auth_user', []);
        if (empty($userData)) {
            return redirect('/')->with('error', '🚫 لم يتم العثور على بيانات المستخدم');
        }
        if ((int)($userData['is_sales'] ?? 0) !== 1) {
            return redirect('/')->with('error', '🚫 غير مصرح لك بالوصول إلى صفحة المبيعات الخاصة بالمندوبين');
        }

        $userId = $userData['id'] ?? null;
        if (!$userId) {
            return redirect('/')->with('error', '🚫 معرف المستخدم غير موجود');
        }

        // المبيعات حسب المنصة
        $vouchersByPlatform = DB::table('vouchers')
            ->select('platform', DB::raw('COUNT(*) as count'), DB::raw('SUM(price_amount) as total'))
            ->where('issued_by', $userId)
            ->groupBy('platform')
            ->get()
            ->map(fn($row) => [
                'platform' => $row->platform,
                'count'    => (int) $row->count,
                'total'    => (float) $row->total,
            ]);

        // المبيعات حسب نوع الاشتراك
        $vouchersByType = DB::table('vouchers')
            ->select('plan_name', DB::raw('COUNT(*) as count'), DB::raw('SUM(price_amount) as total'))
            ->where('issued_by', $userId)
            ->groupBy('plan_name')
            ->get()
            ->map(fn($row) => [
                'plan_name' => $row->plan_name,
                'count'     => (int) $row->count,
                'total'     => (float) $row->total,
            ]);

        // فلاتر
        $type = trim((string) $request->get('type', ''));      // voucher_sale / payment / adjustment / refund
        $from = $request->get('from');                         // تاريخ بداية
        $to   = $request->get('to');                           // تاريخ نهاية
        $q    = trim((string) $request->get('q', ''));         // بحث في doc_code أو الملاحظة

        // الاستعلام (بدون DataTables) مع ترقيم 10/صفحة
        $transactionsQuery = DB::table('sales_transactions as t')
            ->where('t.user_id', $userId)
            ->select([
                't.id',
                't.type',
                't.amount',
                't.balance_before',
                't.balance_after',
                't.note',
                't.created_at',
                't.doc_code',   // رقم الفاتورة
            ]);

        if ($type !== '') {
            $transactionsQuery->where('t.type', $type);
        }
        if (!empty($from)) {
            $transactionsQuery->whereDate('t.created_at', '>=', $from);
        }
        if (!empty($to)) {
            $transactionsQuery->whereDate('t.created_at', '<=', $to);
        }
        if ($q !== '') {
            $like = "%{$q}%";
            $transactionsQuery->where(function ($w) use ($like) {
                $w->where('t.doc_code', 'like', $like)
                  ->orWhere('t.note', 'like', $like);
            });
        }

        $transactions = $transactionsQuery
            ->orderByDesc('t.created_at')
            ->paginate(10)
            ->withQueryString();

        // السقف الشهري
        $limit = DB::table('sales_limits')
            ->where('user_id', $userId)
            ->value('monthly_limit');

        // الرصيد الحالي للمستخدم
        $currentBalance = (float) (DB::table('sales_points')
            ->where('user_id', $userId)
            ->value('balance') ?? 0);

        return view('sales.person-dashboard', compact(
            'vouchersByPlatform',
            'vouchersByType',
            'transactions',
            'limit',
            'currentBalance',
            'userData'
        ));
    }
}

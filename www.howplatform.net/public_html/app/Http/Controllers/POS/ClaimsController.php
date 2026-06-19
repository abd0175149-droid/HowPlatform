<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\POS\AccountingService;

class ClaimsController extends Controller
{
    /** معاينة المطالبة: يرجّع count/total للفواتير غير المدفوعة ضمن النطاق */
    public function preview(Request $request)
    {
        $request->validate([
            'user_id' => ['required','integer','min:1'],
            'from'    => ['required','date'],
            'to'      => ['required','date'],
        ]);

        $uid  = (int) $request->query('user_id');
        $from = $request->query('from');
        $to   = $request->query('to');

        // الفواتير غير الملغاة وغير المدفوعة ضمن النطاق
        $row = DB::table('vouchers')
            ->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(price_amount),0) AS total')
            ->where('issued_by', $uid)
            ->where('is_cancel', 0)
            ->where('is_paid', 0)
            ->whereBetween('issued_at', [$from, $to])
            ->first();

        return response()->json([
            'count' => (int)($row->cnt ?? 0),
            'total' => (float)($row->total ?? 0.0),
        ]);
    }

    /** إنشاء المطالبة (CLM) وتجميع عناصرها */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => ['required','integer','min:1'],
            'from'    => ['required','date'],
            'to'      => ['required','date'],
        ]);

        $uid  = (int) $request->input('user_id');
        $from = $request->input('from');
        $to   = $request->input('to');

        // اجلب الفواتير المرشحة
        $invoices = DB::table('vouchers')
            ->select('id','price_amount')
            ->where('issued_by', $uid)
            ->where('is_cancel', 0)
            ->where('is_paid', 0)
            ->whereBetween('issued_at', [$from, $to])
            ->orderBy('issued_at','asc')
            ->get();

        if ($invoices->isEmpty()) {
            return response()->json(['status'=>'fail','message'=>'لا توجد فواتير غير مدفوعة ضمن النطاق'], 422);
        }

        $claimId = null;
        $claimCode = null;

        DB::transaction(function () use ($uid, $from, $to, $invoices, &$claimId, &$claimCode) {
            $svc = app(AccountingService::class);
            $claimCode = $svc->nextDocCode('CLM');

            $count = $invoices->count();
            $total = (float) $invoices->sum('price_amount');

            // رأس المطالبة
            $claimId = DB::table('sales_claims')->insertGetId([
                'claim_code'     => $claimCode,
                'user_id'        => $uid,
                'date_from'      => $from,
                'date_to'        => $to,
                'invoices_count' => $count,
                'invoices_total' => $total,
                'status'         => 'open',
                'created_by'     => optional(auth()->user())->id,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            // عناصر المطالبة + ربط الفاتورة بالمطالبة
            foreach ($invoices as $inv) {
                DB::table('sales_claim_items')->insert([
                    'claim_id'   => $claimId,
                    'voucher_id' => $inv->id,
                    'amount'     => $inv->price_amount,
                    'paid_amount'=> 0.00,
                    'is_paid'    => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('vouchers')->where('id',$inv->id)->update([
                    'claim_id'   => $claimId,
                ]);
            }
        });

        return response()->json([
            'status'     => 'success',
            'message'    => 'تم إنشاء المطالبة',
            'claim_id'   => $claimId,
            'claim_code' => $claimCode,
            'redirect'   => route('pos.claims.show', $claimId),
        ]);
    }

public function show(\Illuminate\Http\Request $request, $id)
{
    // صلاحية الوصول (اختياري حسب نظامك)
    $userData = $request->session()->get('auth_user', []);
    if ((int)($userData['is_admin'] ?? 0) !== 1) {
        return redirect('/')->with('error', '🚫 غير مصرح لك بعرض المطالبات');
    }

    // رأس المطالبة + اسم المندوب من sales_points
    $claim = \DB::table('sales_claims as c')
        ->leftJoin('sales_points as sp', 'sp.user_id', '=', 'c.user_id')
        ->select('c.*', \DB::raw('COALESCE(sp.user_name,"غير معروف") as user_name'))
        ->where('c.id', $id)
        ->first();

    if (!$claim) {
        return redirect()->route('pos.claims.index')->with('error', 'المطالبة غير موجودة');
    }

    // عناصر المطالبة — نعتمد voucher_id فقط، ونربط المعاملة على voucher_sale
    $perPage = (int) max(5, min(100, (int) $request->query('per_page', 10))); // ← 10 لكل صفحة
    $items = \DB::table('sales_claim_items as ci')
        ->leftJoin('vouchers as v', 'v.id', '=', 'ci.voucher_id')
        ->leftJoin('sales_transactions as t', function($j) {
            $j->on('t.voucher_id', '=', 'ci.voucher_id')
              ->where('t.type', '=', 'voucher_sale');
        })
        ->select(
            'ci.id','ci.claim_id','ci.voucher_id','ci.amount',
            'ci.paid_amount','ci.is_paid','ci.paid_at','ci.created_at',
            'v.code as voucher_code','v.plan_name','v.price_amount','v.platform',
            't.doc_code as tx_doc_code','t.amount as net_due','t.created_at as sold_at'
        )
        ->where('ci.claim_id', $claim->id)
        ->orderBy('ci.id', 'asc')
        ->paginate($perPage)
        ->appends($request->query());

    // لزر الرجوع لنفس التبويب
    session(['pos_active_tab' => '#points']);

    return view('sales.claims.show', compact('claim','items'));
}

public function print(\Illuminate\Http\Request $request, $id)
{
    $userData = $request->session()->get('auth_user', []);
    if ((int)($userData['is_admin'] ?? 0) !== 1) {
        return redirect('/')->with('error', '🚫 غير مصرح لك');
    }

    $claim = \DB::table('sales_claims as c')
        ->leftJoin('sales_points as sp', 'sp.user_id', '=', 'c.user_id')
        ->select('c.*', \DB::raw('COALESCE(sp.user_name,"غير معروف") as user_name'))
        ->where('c.id', $id)->first();

    if (!$claim) {
        return redirect()->route('pos.claims.index')->with('error', 'المطالبة غير موجودة');
    }

    // كل العناصر بدون ترقيم (عشان الطباعة)
    $items = \DB::table('sales_claim_items as ci')
        ->leftJoin('vouchers as v', 'v.id', '=', 'ci.voucher_id')
        ->leftJoin('sales_transactions as t', function($j) {
            $j->on('t.voucher_id', '=', 'ci.voucher_id')
              ->where('t.type', '=', 'voucher_sale');
        })
        ->select(
            'ci.id','ci.claim_id','ci.voucher_id','ci.amount',
            'ci.paid_amount','ci.is_paid','ci.paid_at','ci.created_at',
            'v.code as voucher_code','v.plan_name','v.price_amount','v.platform',
            't.doc_code as tx_doc_code','t.amount as net_due','t.created_at as sold_at'
        )
        ->where('ci.claim_id', $claim->id)
        ->orderBy('ci.id', 'asc')
        ->get();

    return view('sales.claims.print', compact('claim','items'));
}


    public function listByUser($userId, Request $request)
{
    $userId = (int) $userId;
    $limit  = (int) max(1, min(100, (int) $request->query('limit', 20)));

    $claims = DB::table('sales_claims')
        ->where('user_id', $userId)
        ->orderByDesc('id')
        ->limit($limit)
        ->get([
            'id','claim_code','date_from','date_to','invoices_count','invoices_total','status','created_at'
        ]);

    return response()->json([
        'status' => 'success',
        'data'   => $claims,
        'count'  => $claims->count(),
    ]);
}

}

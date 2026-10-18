<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * المطالبات: تجميع مبيعات البطاقات غير المسوّاة لمندوب ضمن فترة في مطالبة (CLM).
 * مُرحَّل من www POS ومكيّف على بطاقات Plans (sales_transactions.type=card_sale + card_id).
 * بطاقة غير مسوّاة = حركة card_sale لا يوجد لها سطر في sales_claim_items (حسب card_id).
 */
class ClaimsController extends Controller
{
    /** استعلام مبيعات البطاقات غير المسوّاة لمندوب ضمن فترة. */
    private function unclaimedQuery(int $userId, ?string $from, ?string $to)
    {
        $q = DB::table('sales_transactions as t')
            ->where('t.type', 'card_sale')
            ->where('t.user_id', $userId)
            ->whereNotNull('t.card_id')
            ->whereNotIn('t.card_id', function ($sub) {
                $sub->select('card_id')->from('sales_claim_items')->whereNotNull('card_id');
            });

        if ($from) {
            $q->whereDate('t.created_at', '>=', $from);
        }
        if ($to) {
            $q->whereDate('t.created_at', '<=', $to);
        }

        return $q;
    }

    /** صفحة الإدارة: نموذج إنشاء مطالبة + آخر المطالبات + نموذج دفعة. */
    public function index(Request $request)
    {
        // أحدث صفّ لكل مندوب (للاسم)
        $latest = DB::table('sales_points')->select('user_id', DB::raw('MAX(id) as max_id'))->groupBy('user_id');

        $sellers = DB::table('sales_points as sp')
            ->joinSub($latest, 'l', fn ($j) => $j->on('sp.id', '=', 'l.max_id'))
            ->select('sp.user_id', 'sp.user_name')->orderBy('sp.user_name')->get();

        $claims = DB::table('sales_claims as c')
            ->leftJoin('sales_points as sp', 'sp.user_id', '=', 'c.user_id')
            ->select('c.*', DB::raw('COALESCE(sp.user_name, "غير معروف") as user_name'),
                DB::raw('(SELECT COALESCE(SUM(paid_amount),0) FROM sales_claim_items WHERE claim_id = c.id) as paid_total'))
            ->orderByDesc('c.id')->paginate(15)->withQueryString();

        return view('seller.claims', compact('sellers', 'claims'));
    }

    /** معاينة المطالبة: يرجّع count/total لمبيعات البطاقات غير المسوّاة ضمن النطاق. */
    public function preview(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
            'from'    => ['nullable', 'date'],
            'to'      => ['nullable', 'date'],
        ]);

        $uid  = (int) $request->query('user_id');
        $from = $request->query('from');
        $to   = $request->query('to');

        $row = $this->unclaimedQuery($uid, $from, $to)
            ->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(t.amount),0) AS total')
            ->first();

        return response()->json([
            'count' => (int) ($row->cnt ?? 0),
            'total' => (float) ($row->total ?? 0.0),
        ]);
    }

    /** إنشاء المطالبة (CLM) وتجميع عناصرها من مبيعات البطاقات غير المسوّاة. */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
            'from'    => ['nullable', 'date'],
            'to'      => ['nullable', 'date'],
        ]);

        $uid  = (int) $request->input('user_id');
        $from = $request->input('from');
        $to   = $request->input('to');

        $createdBy = (int) (session('auth_user')['id'] ?? 0) ?: null;

        $claimCode = null;

        DB::transaction(function () use ($uid, $from, $to, $createdBy, &$claimCode) {
            // اجلب البطاقات المرشحة مع قفل لمنع السباق على التجميع
            $sales = $this->unclaimedQuery($uid, $from, $to)
                ->select('t.card_id', 't.amount')
                ->orderBy('t.created_at', 'asc')
                ->lockForUpdate()
                ->get();

            if ($sales->isEmpty()) {
                abort(422, 'لا توجد مبيعات بطاقات غير مسوّاة ضمن النطاق.');
            }

            $svc   = app(AccountingService::class);
            $code  = $svc->nextDocCode('CLM');
            $count = $sales->count();
            $total = (float) $sales->sum('amount');

            $claimId = DB::table('sales_claims')->insertGetId([
                'claim_code'     => $code,
                'user_id'        => $uid,
                'date_from'      => $from,
                'date_to'        => $to,
                'invoices_count' => $count,
                'invoices_total' => $total,
                'status'         => 'open',
                'created_by'     => $createdBy,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            foreach ($sales as $s) {
                DB::table('sales_claim_items')->insert([
                    'claim_id'    => $claimId,
                    'card_id'     => $s->card_id,
                    'amount'      => $s->amount,
                    'paid_amount' => 0.00,
                    'is_paid'     => 0,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            $claimCode = $code;
        });

        return back()->with('ok', '✅ تم إنشاء المطالبة ' . $claimCode . '.');
    }

    /** عرض مطالبة + عناصرها (مع كود/مشتري البطاقة واسم الخطة). */
    public function show(Request $request, int $claim)
    {
        $row = DB::table('sales_claims as c')
            ->leftJoin('sales_points as sp', 'sp.user_id', '=', 'c.user_id')
            ->select('c.*', DB::raw('COALESCE(sp.user_name, "غير معروف") as user_name'))
            ->where('c.id', $claim)->first();

        if (! $row) {
            return redirect()->route('seller.claims')->with('err', 'المطالبة غير موجودة.');
        }

        $items = DB::table('sales_claim_items as ci')
            ->leftJoin('cards as cd', 'cd.id', '=', 'ci.card_id')
            ->leftJoin('plans as p', 'p.id', '=', 'cd.plan_id')
            ->select('ci.*', 'cd.code as card_code', 'cd.buyer_name',
                DB::raw('COALESCE(p.name, "—") as plan_name'))
            ->where('ci.claim_id', $row->id)
            ->orderBy('ci.id', 'asc')
            ->paginate(10)->withQueryString();

        $paidTotal = (float) DB::table('sales_claim_items')->where('claim_id', $row->id)->sum('paid_amount');

        return view('seller.claims.show', ['claim' => $row, 'items' => $items, 'paidTotal' => $paidTotal]);
    }

    /** نسخة قابلة للطباعة من المطالبة. */
    public function print(Request $request, int $claim)
    {
        $row = DB::table('sales_claims as c')
            ->leftJoin('sales_points as sp', 'sp.user_id', '=', 'c.user_id')
            ->select('c.*', DB::raw('COALESCE(sp.user_name, "غير معروف") as user_name'))
            ->where('c.id', $claim)->first();

        if (! $row) {
            return redirect()->route('seller.claims')->with('err', 'المطالبة غير موجودة.');
        }

        $items = DB::table('sales_claim_items as ci')
            ->leftJoin('cards as cd', 'cd.id', '=', 'ci.card_id')
            ->leftJoin('plans as p', 'p.id', '=', 'cd.plan_id')
            ->select('ci.*', 'cd.code as card_code', 'cd.buyer_name',
                DB::raw('COALESCE(p.name, "—") as plan_name'))
            ->where('ci.claim_id', $row->id)
            ->orderBy('ci.id', 'asc')
            ->get();

        $paidTotal = (float) DB::table('sales_claim_items')->where('claim_id', $row->id)->sum('paid_amount');

        return view('seller.claims.print', ['claim' => $row, 'items' => $items, 'paidTotal' => $paidTotal]);
    }
}

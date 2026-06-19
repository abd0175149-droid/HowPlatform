<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\POS\AccountingService;

class PaymentsController extends Controller
{
    /**
     * body: { user_id, amount, method (optional), claim_id (optional), note (optional) }
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'  => ['required','integer','min:1'],
            'amount'   => ['required','numeric','min:0.01'],
            'method'   => ['nullable','string','max:32'],
            'claim_id' => ['nullable','integer','min:1'],
            'note'     => ['nullable','string','max:255'],
        ]);

        $uid     = (int) $data['user_id'];
        $amount  = (float) $data['amount'];
        $method  = $data['method'] ?? null;
        $claimId = !empty($data['claim_id']) ? (int)$data['claim_id'] : null;
        $note    = $data['note'] ?? null;

        $result = DB::transaction(function () use ($uid, $amount, $method, $claimId, $note) {
            $svc = app(AccountingService::class);

            // 1) أنشئ حركة دفع (PAY) وحدث رصيد المندوب
            $tx = $svc->recordPaymentWithClaim($uid, $amount, $method, $claimId, $note);
            $paymentTxId = $tx['tx_id'];

            $remaining = $amount;

            // 2) احضر الفواتير الهدف: من عناصر المطالبة أو كل غير المدفوع FIFO
            if ($claimId) {
                $rows = DB::table('sales_claim_items as ci')
                    ->join('vouchers as v','v.id','=','ci.voucher_id')
                    ->where('ci.claim_id',$claimId)
                    ->where('v.is_cancel',0)
                    ->orderBy('v.issued_at','asc')
                    ->select('ci.id as claim_item_id','ci.amount','ci.paid_amount','v.id as voucher_id','v.price_amount')
                    ->get();
            } else {
                // غير مدفوعة على مستوى المندوب كله
                $rows = DB::table('vouchers as v')
                    ->leftJoin('sales_claim_items as ci','ci.voucher_id','=','v.id')
                    ->where('v.issued_by',$uid)
                    ->where('v.is_cancel',0)
                    ->where('v.is_paid',0)
                    ->orderBy('v.issued_at','asc')
                    ->select('ci.id as claim_item_id','ci.amount','ci.paid_amount','v.id as voucher_id','v.price_amount')
                    ->get();
            }

            // 3) وزّع المبلغ
            foreach ($rows as $r) {
                if ($remaining <= 0) break;

                // المتبقي على الفاتورة:
                // إن وُجد claim_item: remaining = amount - paid_amount
                // وإلا: remaining = price_amount - مجموع تخصيصات سابقة (نحسب سريعًا)
                if ($r->claim_item_id) {
                    $needed = round($r->amount - (float)$r->paid_amount, 2);
                } else {
                    $prev = (float) DB::table('sales_payment_allocations')
                        ->where('voucher_id',$r->voucher_id)
                        ->sum('amount');
                    $needed = round($r->price_amount - $prev, 2);
                }

                if ($needed <= 0) {
                    // هذه الفاتورة مغطّاة بالفعل
                    continue;
                }

                $alloc = $remaining >= $needed ? $needed : $remaining;

                // سجل التخصيص
                DB::table('sales_payment_allocations')->insert([
                    'payment_tx_id' => $paymentTxId,
                    'claim_id'      => $claimId,
                    'claim_item_id' => $r->claim_item_id,
                    'voucher_id'    => $r->voucher_id,
                    'amount'        => $alloc,
                    'created_at'    => now(),
                ]);

                // حدّث claim_item لو موجود
                if ($r->claim_item_id) {
                    $newPaid = round(((float)$r->paid_amount) + $alloc, 2);
                    $isPaid  = $newPaid + 0.00001 >= (float)$r->amount ? 1 : 0;

                    DB::table('sales_claim_items')->where('id',$r->claim_item_id)->update([
                        'paid_amount' => $newPaid,
                        'is_paid'     => $isPaid,
                        'paid_at'     => $isPaid ? now() : null,
                        'updated_at'  => now(),
                    ]);
                }

                // حدّث الفاتورة نفسها
                $allocSum = (float) DB::table('sales_payment_allocations')
                    ->where('voucher_id',$r->voucher_id)
                    ->sum('amount');

                $fully = $allocSum + 0.00001 >= (float)$r->price_amount ? 1 : 0;

                DB::table('vouchers')->where('id',$r->voucher_id)->update([
                    'is_paid'   => $fully ? 1 : 0,
                    'paid_at'   => $fully ? now() : null,
                    'paid_tx_id'=> $paymentTxId, // آخر دفعة
                ]);

                $remaining = round($remaining - $alloc, 2);
            }

            // 4) إن كانت المطالبة محدّدة، حدّث حالتها إن اكتملت
            if ($claimId) {
                $openItems = DB::table('sales_claim_items')
                    ->where('claim_id',$claimId)
                    ->where('is_paid',0)
                    ->count();

                if ($openItems == 0) {
                    DB::table('sales_claims')->where('id',$claimId)->update([
                        'status'    => 'paid',
                        'updated_at'=> now(),
                    ]);
                }
            }

            return [
                'tx_id'     => $paymentTxId,
                'doc_code'  => $tx['docCode'],
                'remaining' => $remaining,
            ];
        });

        return response()->json([
            'status'    => 'success',
            'message'   => 'تم تسجيل الدفعة وتسويتها',
            'tx_id'     => $result['tx_id'],
            'doc_code'  => $result['doc_code'],
            'unallocated_amount' => $result['remaining'], // إن بقي جزء غير مخصص
        ]);
    }
}

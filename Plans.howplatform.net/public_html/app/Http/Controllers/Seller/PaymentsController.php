<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * المدفوعات: تسجيل دفعة لمندوب (تُنقص رصيده) وتوزيعها FIFO على عناصر مطالبة.
 * مُرحَّل من www POS ومكيّف على بطاقات Plans (sales_claim_items.card_id).
 */
class PaymentsController extends Controller
{
    /** body: { user_id, amount, method?, claim_id?, note? } */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'  => ['required', 'integer', 'min:1'],
            'amount'   => ['required', 'numeric', 'min:0.01'],
            'method'   => ['nullable', 'string', 'max:32'],
            'claim_id' => ['nullable', 'integer', 'min:1'],
            'note'     => ['nullable', 'string', 'max:255'],
        ]);

        $uid     = (int) $data['user_id'];
        $amount  = (float) $data['amount'];
        $method  = $data['method'] ?? null;
        $claimId = ! empty($data['claim_id']) ? (int) $data['claim_id'] : null;
        $note    = $data['note'] ?? null;

        $svc = app(AccountingService::class);

        // تسجيل الدفعة + توزيعها FIFO ضمن معاملة واحدة ذرّية (atomic).
        // recordPaymentWithClaim و nextDocCode يستخدمان DB::transaction داخليًا،
        // فتُعامَل كـ savepoints متداخلة ضمن هذه المعاملة الخارجية — آمن.
        $tx = DB::transaction(function () use ($svc, $uid, $amount, $method, $claimId, $note) {
            // 1) سجّل حركة الدفع (تُنقص الرصيد) — ترجع tx_id + docCode
            $tx          = $svc->recordPaymentWithClaim($uid, $amount, $method, $claimId, $note);
            $paymentTxId = $tx['tx_id'];

            // 2) إن لم تُحدَّد مطالبة: نكتفي بتسجيل الدفعة بلا توزيع
            if (! $claimId) {
                return $tx;
            }

            // 3) وزّع المبلغ FIFO على عناصر المطالبة غير المدفوعة (الأقدم أولًا)
            $remaining = round($amount, 2);

            $items = DB::table('sales_claim_items')
                ->where('claim_id', $claimId)
                ->where('is_paid', 0)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($items as $it) {
                if ($remaining <= 0) {
                    break;
                }

                $needed = round((float) $it->amount - (float) $it->paid_amount, 2);
                if ($needed <= 0) {
                    continue;
                }

                $pay     = $remaining >= $needed ? $needed : $remaining;
                $newPaid = round((float) $it->paid_amount + $pay, 2);
                $isPaid  = ($newPaid + 0.00001) >= (float) $it->amount ? 1 : 0;

                DB::table('sales_claim_items')->where('id', $it->id)->update([
                    'paid_amount' => $newPaid,
                    'is_paid'     => $isPaid,
                    'paid_at'     => $isPaid ? now() : $it->paid_at,
                    'updated_at'  => now(),
                ]);

                DB::table('sales_payment_allocations')->insert([
                    'payment_tx_id' => $paymentTxId,
                    'claim_id'      => $claimId,
                    'claim_item_id' => $it->id,
                    'card_id'       => $it->card_id,
                    'amount'        => $pay,
                    'created_at'    => now(),
                ]);

                $remaining = round($remaining - $pay, 2);
            }

            // 4) إن أصبحت كل العناصر مدفوعة → أغلق المطالبة
            $openItems = DB::table('sales_claim_items')->where('claim_id', $claimId)->where('is_paid', 0)->count();
            if ($openItems === 0) {
                DB::table('sales_claims')->where('id', $claimId)->update([
                    'status'     => 'paid',
                    'updated_at' => now(),
                ]);
            }

            return $tx;
        });

        if (! $claimId) {
            return back()->with('ok', '✅ تم تسجيل الدفعة ' . $tx['docCode'] . '.');
        }

        return back()->with('ok', '✅ تم تسجيل الدفعة ' . $tx['docCode'] . ' وتوزيعها على المطالبة.');
    }
}

<?php

namespace App\Http\Controllers\POS;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema; // ← أضِف هذا

class AccountingService
{
    private function periodKey(): string { return Carbon::now()->format('Y-m'); }

    public function nextDocCode(string $prefix='TX'): string
    {
        return DB::transaction(function () use ($prefix) {
            $table = DB::table('doc_counters');

            $hasPrefix = Schema::hasColumn('doc_counters','prefix');
            $hasLast   = Schema::hasColumn('doc_counters','last_number');

            if ($hasPrefix && $hasLast) {
                $row = $table->where('prefix',$prefix)->lockForUpdate()->first();
                if (!$row) {
                    $table->insert([
                        'prefix'=>$prefix,'last_number'=>0,
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                    $row = (object)['last_number'=>0];
                }
                $next = (int)$row->last_number + 1;
                $table->where('prefix',$prefix)->update([
                    'last_number'=>$next,
                    'updated_at'=>now(),
                ]);
            } else {
                // دعم مخطط قديم key/value إن وُجد في بيئة ثانية
                $row = $table->where('key',$prefix)->lockForUpdate()->first();
                if (!$row) {
                    $table->insert([
                        'key'=>$prefix,'value'=>0,
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                    $row = (object)['value'=>0];
                }
                $next = (int)$row->value + 1;
                $table->where('key',$prefix)->update([
                    'value'=>$next,
                    'updated_at'=>now(),
                ]);
            }

            return sprintf('%s-%06d', $prefix, $next);
        });
    }


    private function getAgentRowForUpdate(int $userId)
    {
        $sp = DB::table('sales_points')->where('user_id',$userId)->lockForUpdate()->first();
        if (!$sp) abort(422, 'لا توجد نقطة بيع لهذا المندوب. أضفه أولًا.');
        // صفّر الرصيد عند بداية شهر جديد
        $pk = $this->periodKey();
        if ($sp->balance_month_key !== $pk) {
            DB::table('sales_points')->where('id',$sp->id)->update([
                'balance' => 0, 'balance_month_key' => $pk, 'updated_at'=>now(),
            ]);
            $sp->balance = 0; $sp->balance_month_key = $pk;
        }
        return $sp;
    }

    private function getMonthlyLimit(int $userId): float
    {
        return (float) DB::table('sales_limits')->where('user_id',$userId)->value('monthly_limit') ?? 0.0;
    }

    /** تسجّل قيد بيع قسيمة (net_due) وتتحقق من السقف */
    public function recordVoucherSale(int $userId, int $voucherId, float $priceAmount): array
    {
        return DB::transaction(function () use ($userId, $voucherId, $priceAmount) {
            $sp = $this->getAgentRowForUpdate($userId);
            $pk = $this->periodKey();

            // خصم/عمولة المندوب (%) من sales_points
            $discount = (float) ($sp->discount_percent ?? 0);
            $netDue   = round($priceAmount * (1 - $discount/100), 2);

            // تحقق السقف
            $limit    = $this->getMonthlyLimit($userId);
            $projected = $sp->balance + $netDue;
            if ($limit > 0 && $projected > $limit) {
                abort(422, 'تجاوز السقف الشهري. المتاح: ' . number_format(max(0,$limit - $sp->balance), 2) . ' JOD');
            }

            $before  = (float) $sp->balance;
            $after   = $before + $netDue;

            $docCode = $this->nextDocCode();

            DB::table('sales_transactions')->insert([
                'user_id'        => $userId,
                'voucher_id'     => $voucherId,
                'doc_code'       => $docCode,
                'type'           => 'voucher_sale',
                'amount'         => $netDue,
                'balance_before' => $before,
                'balance_after'  => $after,
                'note'           => 'صافي مستحق عن القسيمة',
                'created_at'     => now(),
                'period_key'     => $pk,
            ]);

            DB::table('sales_points')->where('id',$sp->id)->update([
                'balance' => $after, 'balance_month_key'=>$pk, 'updated_at'=>now(),
            ]);

            return compact('docCode','netDue','before','after','limit');
        });
    }

    /** دفعة/تعديل/ردّ */
    public function recordTransaction(int $userId, string $type, float $amount, ?string $note=null): array
    {
        if (!in_array($type, ['payment','adjustment','refund'], true)) abort(422, 'نوع عملية غير مدعوم.');
        if ($amount <= 0) abort(422, 'المبلغ يجب أن يكون أكبر من صفر.');

        return DB::transaction(function () use ($userId, $type, $amount, $note) {
            $sp = $this->getAgentRowForUpdate($userId);
            $pk = $this->periodKey();

            $before = (float) $sp->balance;
            // payment/refund تُنقص؛ adjustment ترفع (يمكنك تغيير المنطق لو أردت العكس)
            if ($type === 'payment' || $type === 'refund') {
                $after = max(0, $before - $amount);
            } else { // adjustment
                $after = max(0, $before + $amount);
            }

            $docCode = $this->nextDocCode();

            DB::table('sales_transactions')->insert([
                'user_id'        => $userId,
                'voucher_id'     => null,
                'doc_code'       => $docCode,
                'type'           => $type,
                'amount'         => $amount,
                'balance_before' => $before,
                'balance_after'  => $after,
                'note'           => $note,
                'created_at'     => now(),
                'period_key'     => $pk,
            ]);

            DB::table('sales_points')->where('id',$sp->id)->update([
                'balance' => $after, 'balance_month_key'=>$pk, 'updated_at'=>now(),
            ]);

            return compact('docCode','before','after');
        });
    }
        /**
     * تسجيل دفعة مرتبطة (اختياريًا) بمطالبة claim_id
     * ترجع tx_id + docCode + before/after، وتملأ applies_claim_id و method.
     */
    public function recordPaymentWithClaim(int $userId, float $amount, ?string $method=null, ?int $appliesClaimId=null, ?string $note=null): array
    {
        if ($amount <= 0) abort(422, 'المبلغ يجب أن يكون أكبر من صفر.');

        return DB::transaction(function () use ($userId, $amount, $method, $appliesClaimId, $note) {
            $sp = $this->getAgentRowForUpdate($userId);
            $pk = $this->periodKey();

            $before = (float) $sp->balance;
            $after  = max(0, $before - $amount);

            $docCode = $this->nextDocCode('PAY');

            $txId = DB::table('sales_transactions')->insertGetId([
                'user_id'        => $userId,
                'voucher_id'     => null,
                'applies_claim_id'=> $appliesClaimId,
                'doc_code'       => $docCode,
                'type'           => 'payment',
                'method'         => $method,
                'amount'         => $amount,
                'balance_before' => $before,
                'balance_after'  => $after,
                'note'           => $note,
                'created_at'     => now(),
                'period_key'     => $pk,
            ]);

            DB::table('sales_points')->where('id',$sp->id)->update([
                'balance' => $after, 'balance_month_key'=>$pk, 'updated_at'=>now(),
            ]);

            return [
                'tx_id'    => $txId,
                'docCode'  => $docCode,
                'before'   => $before,
                'after'    => $after,
            ];
        });
    }

}

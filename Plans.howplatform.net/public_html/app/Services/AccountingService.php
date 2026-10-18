<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * محاسبة البائعين في Plans — مُرحَّلة من www POS ومكيّفة على بطاقات Plans.
 * كل العمليات ذرّية مع قفل صفّ نقطة البيع (lockForUpdate) لمنع السباق على الرصيد.
 * الرصيد يُصفَّر تلقائيًا عند بداية شهر جديد (balance_month_key).
 */
class AccountingService
{
    private function periodKey(): string
    {
        return Carbon::now()->format('Y-m');
    }

    /** عدّاد أكواد المستندات الذرّي: PREFIX-000001 */
    public function nextDocCode(string $prefix = 'TX'): string
    {
        return DB::transaction(function () use ($prefix) {
            $table = DB::table('doc_counters');
            $row = $table->where('prefix', $prefix)->lockForUpdate()->first();
            if (! $row) {
                $table->insert(['prefix' => $prefix, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
                $row = (object) ['last_number' => 0];
            }
            $next = (int) $row->last_number + 1;
            $table->where('prefix', $prefix)->update(['last_number' => $next, 'updated_at' => now()]);

            return sprintf('%s-%06d', $prefix, $next);
        });
    }

    /** صفّ نقطة البيع مع قفل + تصفير الرصيد عند تغيّر الشهر. */
    private function getAgentRowForUpdate(int $userId)
    {
        $sp = DB::table('sales_points')->where('user_id', $userId)->orderByDesc('id')->lockForUpdate()->first();
        if (! $sp) {
            abort(422, 'لا توجد نقطة بيع لهذا المندوب. أضِفه أولًا من تبويب نقاط البيع.');
        }
        $pk = $this->periodKey();
        if ((string) $sp->balance_month_key !== $pk) {
            DB::table('sales_points')->where('id', $sp->id)->update([
                'balance' => 0, 'balance_month_key' => $pk, 'updated_at' => now(),
            ]);
            $sp->balance = 0;
            $sp->balance_month_key = $pk;
        }
        return $sp;
    }

    private function getMonthlyLimit(int $userId): float
    {
        return (float) (DB::table('sales_limits')->where('user_id', $userId)->value('monthly_limit') ?? 0.0);
    }

    /** قيد بيع بطاقة (صافي مستحق بعد عمولة المندوب) + تحقّق السقف الشهري. */
    public function recordCardSale(int $userId, int $cardId, float $priceAmount): array
    {
        return DB::transaction(function () use ($userId, $cardId, $priceAmount) {
            $sp = $this->getAgentRowForUpdate($userId);
            $pk = $this->periodKey();

            $discount = (float) ($sp->discount_percent ?? 0);
            $netDue   = round($priceAmount * (1 - $discount / 100), 2);

            $limit     = $this->getMonthlyLimit($userId);
            $projected = (float) $sp->balance + $netDue;
            if ($limit > 0 && $projected > $limit) {
                abort(422, 'تجاوز السقف الشهري. المتاح: ' . number_format(max(0, $limit - $sp->balance), 2) . ' JOD');
            }

            $before  = (float) $sp->balance;
            $after   = $before + $netDue;
            $docCode = $this->nextDocCode('TX');

            DB::table('sales_transactions')->insert([
                'user_id'        => $userId,
                'card_id'        => $cardId,
                'doc_code'       => $docCode,
                'type'           => 'card_sale',
                'amount'         => $netDue,
                'balance_before' => $before,
                'balance_after'  => $after,
                'note'           => 'صافي مستحق عن بطاقة',
                'created_at'     => now(),
                'period_key'     => $pk,
            ]);

            DB::table('sales_points')->where('id', $sp->id)->update([
                'balance' => $after, 'balance_month_key' => $pk, 'updated_at' => now(),
            ]);

            return compact('docCode', 'netDue', 'before', 'after', 'limit');
        });
    }

    /** دفعة/تعديل/ردّ عام (payment/refund يُنقصان، adjustment يرفع). */
    public function recordTransaction(int $userId, string $type, float $amount, ?string $note = null): array
    {
        if (! in_array($type, ['payment', 'adjustment', 'refund'], true)) {
            abort(422, 'نوع عملية غير مدعوم.');
        }
        if ($amount <= 0) {
            abort(422, 'المبلغ يجب أن يكون أكبر من صفر.');
        }

        return DB::transaction(function () use ($userId, $type, $amount, $note) {
            $sp = $this->getAgentRowForUpdate($userId);
            $pk = $this->periodKey();

            $before = (float) $sp->balance;
            $after  = ($type === 'adjustment') ? max(0, $before + $amount) : max(0, $before - $amount);

            $docCode = $this->nextDocCode($type === 'payment' ? 'PAY' : 'TX');

            DB::table('sales_transactions')->insert([
                'user_id'        => $userId,
                'card_id'        => null,
                'doc_code'       => $docCode,
                'type'           => $type,
                'amount'         => $amount,
                'balance_before' => $before,
                'balance_after'  => $after,
                'note'           => $note,
                'created_at'     => now(),
                'period_key'     => $pk,
            ]);

            DB::table('sales_points')->where('id', $sp->id)->update([
                'balance' => $after, 'balance_month_key' => $pk, 'updated_at' => now(),
            ]);

            return compact('docCode', 'before', 'after');
        });
    }

    /** دفعة مرتبطة (اختياريًا) بمطالبة claim_id — تُرجِع tx_id + docCode. */
    public function recordPaymentWithClaim(int $userId, float $amount, ?string $method = null, ?int $appliesClaimId = null, ?string $note = null): array
    {
        if ($amount <= 0) {
            abort(422, 'المبلغ يجب أن يكون أكبر من صفر.');
        }

        return DB::transaction(function () use ($userId, $amount, $method, $appliesClaimId, $note) {
            $sp = $this->getAgentRowForUpdate($userId);
            $pk = $this->periodKey();

            $before  = (float) $sp->balance;
            $after   = max(0, $before - $amount);
            $docCode = $this->nextDocCode('PAY');

            $txId = DB::table('sales_transactions')->insertGetId([
                'user_id'          => $userId,
                'card_id'          => null,
                'applies_claim_id' => $appliesClaimId,
                'doc_code'         => $docCode,
                'type'             => 'payment',
                'method'           => $method,
                'amount'           => $amount,
                'balance_before'   => $before,
                'balance_after'    => $after,
                'note'             => $note,
                'created_at'       => now(),
                'period_key'       => $pk,
            ]);

            DB::table('sales_points')->where('id', $sp->id)->update([
                'balance' => $after, 'balance_month_key' => $pk, 'updated_at' => now(),
            ]);

            return ['tx_id' => $txId, 'docCode' => $docCode, 'before' => $before, 'after' => $after];
        });
    }
}

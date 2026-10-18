<?php

namespace App\Services;

use App\Models\WalletAccount;
use App\Models\WalletLedger;
use App\Models\IdempotencyRecord;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * قلب المحفظة المركزية.
 *
 * الثوابت (غير قابلة للتفاوض):
 *  - دفتر القيود (wallet_ledger) هو الحقيقة؛ wallet_accounts.balance نسخة مخزّنة تُحدَّث في نفس المعاملة.
 *  - كل تحوّل ذرّي بقفل صفّ المحفظة (SELECT ... FOR UPDATE) → لا سباق على نفس المستخدم.
 *  - منع ازدواج بطبقتين: UNIQUE(idempotency_key) على الدفتر (الحاجز المالي) + idempotency_records (إعادة الاستجابة).
 *  - الصرف يفشل مغلقًا عند نقص الرصيد (402) ولا يترك أثرًا في الدفتر.
 */
class WalletService
{
    /** كسب نقاط (موجب). */
    public function earn(int $userId, int $points, array $meta): array
    {
        return $this->apply('earn', +abs($points), $userId, $meta);
    }

    /** صرف نقاط (يُمرَّر موجبًا، يُطبَّق سالبًا). */
    public function spend(int $userId, int $points, array $meta): array
    {
        return $this->apply('spend', -abs($points), $userId, $meta);
    }

    /** استرجاع نقاط (موجب). */
    public function refund(int $userId, int $points, array $meta): array
    {
        return $this->apply('refund', +abs($points), $userId, $meta);
    }

    /** تعديل إداري يدوي (موجب أو سالب) — نفس حارس عدم النزول تحت الصفر. */
    public function adjust(int $userId, int $signedPoints, array $meta): array
    {
        return $this->apply('adjust', $signedPoints, $userId, $meta);
    }

    /** الرصيد الحالي (0 إن لم يوجد حساب بعد). */
    public function balance(int $userId): int
    {
        return (int) (WalletAccount::where('user_id', $userId)->value('balance') ?? 0);
    }

    /**
     * المحرّك الموحّد: ذرّي، idempotent، fail-closed.
     * يُعيد ['code'=>int, 'body'=>array].
     */
    private function apply(string $kind, int $signedPoints, int $userId, array $meta): array
    {
        $client   = (string) $meta['service_client'];
        $key      = (string) $meta['idempotency_key'];
        $endpoint = (string) ($meta['endpoint'] ?? $kind);

        return DB::transaction(function () use ($kind, $signedPoints, $userId, $client, $key, $endpoint, $meta) {

            // 1) إعادة الاستجابة: لو الطلب نُفِّذ سابقًا بنفس المفتاح، أعِد نفس النتيجة.
            $rec = IdempotencyRecord::where('service_client', $client)
                ->where('idempotency_key', $key)
                ->lockForUpdate()
                ->first();
            if ($rec && $rec->response_code !== null) {
                $body = json_decode((string) $rec->response_body, true) ?: [];
                $body['replayed'] = true;
                return ['code' => (int) $rec->response_code, 'body' => $body];
            }

            // 2) اقفل (أو أنشئ) صفّ المحفظة — يُسلسِل كل عمليات نفس المستخدم.
            $acct = WalletAccount::where('user_id', $userId)->lockForUpdate()->first();
            if (!$acct) {
                try {
                    WalletAccount::create(['user_id' => $userId, 'balance' => 0, 'version' => 0]);
                } catch (QueryException $e) {
                    // إنشاء متزامن من طلب آخر — تجاهل ثم أعد القراءة بقفل.
                }
                $acct = WalletAccount::where('user_id', $userId)->lockForUpdate()->first();
            }

            // 3) حاجز ازدواج تجاري: نفس مفتاح idempotency في الدفتر = حدث منفّذ مسبقًا.
            $existing = WalletLedger::where('idempotency_key', $key)->first();
            if ($existing) {
                $body = ['ok' => true, 'duplicate' => true, 'balance' => (int) $acct->balance, 'ledger_id' => (int) $existing->id];
                $this->storeIdem($client, $key, $endpoint, 200, $body);
                return ['code' => 200, 'body' => $body];
            }

            // 4) احسب وتحقّق — الصرف يفشل مغلقًا.
            $newBalance = (int) $acct->balance + $signedPoints;
            if ($signedPoints < 0 && $newBalance < 0) {
                // لا نُخزّن 402 في سجل الـ idempotency: العملية لم تترك أثرًا في الدفتر،
                // وإعادة المحاولة لاحقًا (بعد شحن الرصيد) يجب أن تُعاد تقييمها لا أن تُرفض من الكاش.
                $body = ['ok' => false, 'error' => 'insufficient_balance', 'balance' => (int) $acct->balance];
                return ['code' => 402, 'body' => $body];
            }

            // 5) ألحِق قيدًا (append-only) ثم حدّث النسخة المخزّنة في نفس المعاملة.
            //    لو سبق إدراج نفس المفتاح (سباق نادر بين عمليات)، يلتقطه قيد UNIQUE
            //    فنُعيد "مكرّر" (200) بدل خطأ 500.
            try {
                $ledger = WalletLedger::create([
                    'user_id'        => $userId,
                    'points'         => $signedPoints,
                    'balance_after'  => $newBalance,
                    'kind'           => $kind,
                    'idempotency_key' => $key,
                    'event_type'     => $meta['event_type'] ?? null,
                    'event_ref_type' => $meta['event_ref_type'] ?? null,
                    'event_ref_id'   => $meta['event_ref_id'] ?? null,
                    'service_client' => $client,
                    'description'    => $meta['description'] ?? null,
                ]);
            } catch (QueryException $e) {
                if ($e->getCode() === '23000') { // انتهاك قيد فريد = منفّذ مسبقًا
                    $dupe = WalletLedger::where('idempotency_key', $key)->first();
                    $body = [
                        'ok' => true, 'duplicate' => true,
                        'balance' => (int) ($dupe->balance_after ?? $acct->balance),
                        'ledger_id' => (int) ($dupe->id ?? 0),
                    ];
                    $this->storeIdem($client, $key, $endpoint, 200, $body);
                    return ['code' => 200, 'body' => $body];
                }
                throw $e;
            }

            $acct->balance = $newBalance;
            $acct->version = (int) $acct->version + 1;
            $acct->save();

            $body = ['ok' => true, 'duplicate' => false, 'balance' => $newBalance, 'ledger_id' => (int) $ledger->id];
            $this->storeIdem($client, $key, $endpoint, 200, $body);
            return ['code' => 200, 'body' => $body];
        }, 3); // أعد المحاولة حتى 3 مرات عند deadlock
    }

    private function storeIdem(string $client, string $key, string $endpoint, int $code, array $body): void
    {
        IdempotencyRecord::updateOrCreate(
            ['service_client' => $client, 'idempotency_key' => $key],
            ['endpoint' => $endpoint, 'response_code' => $code, 'response_body' => json_encode($body, JSON_UNESCAPED_UNICODE)]
        );
    }
}

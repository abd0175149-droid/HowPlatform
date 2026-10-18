<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    private string $key = 'test-secret-key';

    protected function setUp(): void
    {
        parent::setUp();
        // عميل خدمة اختباري
        config(['plans.clients' => ['diploma' => $this->key]]);
    }

    private function headers(string $idem = null): array
    {
        $h = ['X-Api-Key' => $this->key, 'Accept' => 'application/json'];
        if ($idem !== null) {
            $h['Idempotency-Key'] = $idem;
        }
        return $h;
    }

    public function test_rejects_without_api_key(): void
    {
        $this->postJson('/api/v1/wallet/earn', ['user_id' => 1, 'points' => 10])
            ->assertStatus(401);
    }

    public function test_earn_then_balance(): void
    {
        $this->postJson('/api/v1/wallet/earn', ['user_id' => 7, 'points' => 100], $this->headers('diploma:earn:test:1'))
            ->assertStatus(200)
            ->assertJson(['ok' => true, 'balance' => 100]);

        $this->getJson('/api/v1/wallet?user_id=7', $this->headers())
            ->assertStatus(200)
            ->assertJson(['ok' => true, 'balance' => 100]);
    }

    public function test_spend_reduces_balance(): void
    {
        $this->postJson('/api/v1/wallet/earn', ['user_id' => 7, 'points' => 100], $this->headers('k1'))->assertStatus(200);

        $this->postJson('/api/v1/wallet/spend', ['user_id' => 7, 'points' => 30], $this->headers('diploma:spend:job:55'))
            ->assertStatus(200)
            ->assertJson(['ok' => true, 'balance' => 70]);
    }

    public function test_spend_insufficient_fails_closed(): void
    {
        $this->postJson('/api/v1/wallet/earn', ['user_id' => 7, 'points' => 10], $this->headers('k1'))->assertStatus(200);

        $this->postJson('/api/v1/wallet/spend', ['user_id' => 7, 'points' => 50], $this->headers('k2'))
            ->assertStatus(402)
            ->assertJson(['ok' => false, 'error' => 'insufficient_balance', 'balance' => 10]);

        // الرصيد لم يتغيّر، ولا قيد في الدفتر للعملية الفاشلة
        $this->assertDatabaseHas('wallet_accounts', ['user_id' => 7, 'balance' => 10]);
        $this->assertDatabaseMissing('wallet_ledger', ['idempotency_key' => 'k2']);
    }

    public function test_spend_idempotent_no_double_charge(): void
    {
        $this->postJson('/api/v1/wallet/earn', ['user_id' => 7, 'points' => 100], $this->headers('k1'))->assertStatus(200);

        // نفس مفتاح idempotency مرّتين = خصم واحد فقط
        $first = $this->postJson('/api/v1/wallet/spend', ['user_id' => 7, 'points' => 30], $this->headers('diploma:spend:job:99'));
        $first->assertStatus(200)->assertJson(['balance' => 70]);

        $second = $this->postJson('/api/v1/wallet/spend', ['user_id' => 7, 'points' => 30], $this->headers('diploma:spend:job:99'));
        $second->assertStatus(200)->assertJson(['balance' => 70, 'replayed' => true]);

        // الرصيد 70 لا 40، وقيد واحد فقط في الدفتر
        $this->assertDatabaseHas('wallet_accounts', ['user_id' => 7, 'balance' => 70]);
        $this->assertSame(1, \App\Models\WalletLedger::where('idempotency_key', 'diploma:spend:job:99')->count());
    }

    public function test_earn_idempotent_no_double_credit(): void
    {
        $this->postJson('/api/v1/wallet/earn', ['user_id' => 8, 'points' => 100], $this->headers('diploma:earn:friend:5'))->assertStatus(200);
        $this->postJson('/api/v1/wallet/earn', ['user_id' => 8, 'points' => 100], $this->headers('diploma:earn:friend:5'))->assertStatus(200);

        $this->assertDatabaseHas('wallet_accounts', ['user_id' => 8, 'balance' => 100]);
        $this->assertSame(1, \App\Models\WalletLedger::where('idempotency_key', 'diploma:earn:friend:5')->count());
    }

    public function test_refund_restores_points(): void
    {
        $this->postJson('/api/v1/wallet/earn', ['user_id' => 9, 'points' => 100], $this->headers('k1'))->assertStatus(200);
        $this->postJson('/api/v1/wallet/spend', ['user_id' => 9, 'points' => 40], $this->headers('diploma:spend:job:1'))->assertStatus(200);

        $this->postJson('/api/v1/wallet/refund', ['user_id' => 9, 'points' => 40], $this->headers('diploma:refund:job:1'))
            ->assertStatus(200)
            ->assertJson(['ok' => true, 'balance' => 100]);
    }

    public function test_ledger_balance_after_matches_account(): void
    {
        $this->postJson('/api/v1/wallet/earn', ['user_id' => 10, 'points' => 200], $this->headers('a'))->assertStatus(200);
        $this->postJson('/api/v1/wallet/spend', ['user_id' => 10, 'points' => 70], $this->headers('b'))->assertStatus(200);

        $sum = (int) \App\Models\WalletLedger::where('user_id', 10)->sum('points');
        $balance = (int) \App\Models\WalletAccount::where('user_id', 10)->value('balance');
        $this->assertSame($sum, $balance, 'الثابت: balance == SUM(ledger.points)');
        $this->assertSame(130, $balance);
    }
}

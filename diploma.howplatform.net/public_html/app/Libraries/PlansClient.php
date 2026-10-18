<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\HTTP\CURLRequest;
use Config\Plans as PlansConfig;
use Throwable;

/**
 * عميل رفيع لخدمة المحفظة المركزية Plans.
 * كل الدوال غير حاجبة (non-blocking) وتبتلع الأخطاء: لا يجوز أن يكسر استدعاءُ Plans
 * المسارَ المحلّي الحيّ (الصرف/الكسب). تُرجِع مصفوفة نتيجة دائمًا.
 */
class PlansClient
{
    private PlansConfig $config;
    private ?CURLRequest $http = null;

    public function __construct(?PlansConfig $config = null)
    {
        $this->config = $config ?? config(PlansConfig::class);
    }

    /** هل التكامل مُفعّل (shadow أو api)؟ */
    public function enabled(): bool
    {
        return in_array($this->config->walletMode, ['shadow', 'api'], true)
            && $this->config->baseUrl !== ''
            && $this->config->apiKey !== '';
    }

    public function mode(): string
    {
        return $this->config->walletMode;
    }

    public function spend(int $userId, int $points, string $idempotencyKey, array $meta = []): array
    {
        return $this->post('spend', $userId, $points, $idempotencyKey, $meta);
    }

    public function earn(int $userId, int $points, string $idempotencyKey, array $meta = []): array
    {
        return $this->post('earn', $userId, $points, $idempotencyKey, $meta);
    }

    public function refund(int $userId, int $points, string $idempotencyKey, array $meta = []): array
    {
        return $this->post('refund', $userId, $points, $idempotencyKey, $meta);
    }

    /** الرصيد الحالي في المحفظة المركزية (أو null عند التعذّر). */
    public function balance(int $userId): ?int
    {
        if (! $this->enabled()) {
            return null;
        }
        try {
            $resp = $this->client()->get($this->config->baseUrl . '/wallet?user_id=' . $userId, [
                'headers' => $this->headers(),
            ]);
            if ($resp->getStatusCode() === 200) {
                $data = json_decode((string) $resp->getBody(), true);
                if (is_array($data) && isset($data['balance'])) {
                    return (int) $data['balance'];
                }
            }
        } catch (Throwable $e) {
            log_message('warning', 'PlansClient::balance failed: ' . $e->getMessage());
        }
        return null;
    }

    public function entitlementsMode(): string
    {
        return $this->config->entitlementsMode;
    }

    public function entitlementsEnabled(): bool
    {
        return in_array($this->config->entitlementsMode, ['shadow', 'api'], true)
            && $this->config->baseUrl !== '' && $this->config->apiKey !== '';
    }

    /** GET /entitlements?user_id= — الخطّة الفعّالة + المزايا (أو null عند التعذّر). */
    public function entitlements(int $userId): ?array
    {
        if (! $this->entitlementsEnabled() || $userId <= 0) {
            return null;
        }
        try {
            $resp = $this->client()->get($this->config->baseUrl . '/entitlements?user_id=' . $userId, [
                'headers' => $this->headers(),
            ]);
            if ($resp->getStatusCode() === 200) {
                $data = json_decode((string) $resp->getBody(), true);
                return is_array($data) ? $data : null;
            }
        } catch (Throwable $e) {
            log_message('warning', 'PlansClient::entitlements failed: ' . $e->getMessage());
        }
        return null;
    }

    public function earningMode(): string
    {
        return $this->config->earningMode;
    }

    public function earningEnabled(): bool
    {
        return $this->config->earningMode === 'api'
            && $this->config->baseUrl !== '' && $this->config->apiKey !== '';
    }

    /** POST /earn/login — تتابع الدخول (Plans يحسب ويمنح). */
    public function earnLogin(int $userId): ?array
    {
        return $this->postEarn('/earn/login', ['user_id' => $userId], $userId);
    }

    /** POST /earn/referral/complete — منح مكافأة الإحالة عند إكمال الملف. */
    public function completeReferral(int $referredId): ?array
    {
        return $this->postEarn('/earn/referral/complete', ['referred_id' => $referredId], $referredId);
    }

    /** POST /earn/referral — تسجيل دعوة pending. */
    public function recordReferral(int $referrerId, int $referredId): ?array
    {
        if ($referrerId <= 0) return null;
        return $this->postEarn('/earn/referral', ['referrer_id' => $referrerId, 'referred_id' => $referredId], $referredId);
    }

    private function postEarn(string $path, array $json, int $guardId): ?array
    {
        if (! $this->earningEnabled() || $guardId <= 0) {
            return null;
        }
        try {
            $resp = $this->client()->post($this->config->baseUrl . $path, [
                'headers' => $this->headers(),
                'json'    => $json,
            ]);
            if ($resp->getStatusCode() === 200) {
                $d = json_decode((string) $resp->getBody(), true);
                return is_array($d) ? $d : null;
            }
        } catch (Throwable $e) {
            log_message('warning', 'PlansClient earn ' . $path . ' failed: ' . $e->getMessage());
        }
        return null;
    }

    public function cardsMode(): string
    {
        return $this->config->cardsMode;
    }

    public function cardsEnabled(): bool
    {
        return $this->config->cardsMode === 'api'
            && $this->config->baseUrl !== '' && $this->config->apiKey !== '';
    }

    /**
     * POST /cards/redeem — استبدال بطاقة عبر Plans (مصدر الحقيقة).
     * يُرجِع المصفوفة المفكوكة من الرد، أو null عند تعذّر الاتصال/الاستثناء (fail-closed).
     */
    public function redeemCard(int $userId, string $code): ?array
    {
        if (! $this->cardsEnabled() || $userId <= 0) {
            return null;
        }
        try {
            $resp = $this->client()->post($this->config->baseUrl . '/cards/redeem', [
                'headers' => $this->headers(),
                'json'    => ['code' => $code, 'user_id' => $userId],
            ]);
            $d = json_decode((string) $resp->getBody(), true);
            return is_array($d) ? $d : null;
        } catch (Throwable $e) {
            log_message('warning', 'PlansClient::redeemCard failed: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * GET /cards/verify?code= — التحقّق من بطاقة عبر Plans.
     * يُرجِع المصفوفة المفكوكة من الرد، أو null عند التعذّر.
     */
    public function verifyCard(string $code): ?array
    {
        if (! $this->cardsEnabled()) {
            return null;
        }
        try {
            $resp = $this->client()->get($this->config->baseUrl . '/cards/verify?code=' . urlencode($code), [
                'headers' => $this->headers(),
            ]);
            $d = json_decode((string) $resp->getBody(), true);
            return is_array($d) ? $d : null;
        } catch (Throwable $e) {
            log_message('warning', 'PlansClient::verifyCard failed: ' . $e->getMessage());
        }
        return null;
    }

    /* ================= قراءات (read-only) خلف أعلام الأوضاع ================= */

    /** earning.mode=api — قراءات الإحصائيات/الإحالات من Plans. */
    public function statsEnabled(): bool
    {
        return $this->config->earningMode === 'api'
            && $this->config->baseUrl !== '' && $this->config->apiKey !== '';
    }

    /** wallet.mode=api — قراءة سجل المعاملات من Plans. */
    public function txEnabled(): bool
    {
        return $this->config->walletMode === 'api'
            && $this->config->baseUrl !== '' && $this->config->apiKey !== '';
    }

    /** entitlements.mode=api — قراءة قائمة الخطط من Plans. */
    public function plansEnabled(): bool
    {
        return $this->config->entitlementsMode === 'api'
            && $this->config->baseUrl !== '' && $this->config->apiKey !== '';
    }

    /** entitlements.mode=api — قراءة اشتراكات المستخدم من Plans. */
    public function subsEnabled(): bool
    {
        return $this->plansEnabled();
    }

    /** GET /wallet/stats?user_id= — إجماليات النقاط/التتابع/الإحالات (أو null). */
    public function stats(int $userId): ?array
    {
        if (! $this->statsEnabled() || $userId <= 0) {
            return null;
        }
        return $this->getJson('/wallet/stats?user_id=' . $userId, 'stats');
    }

    /** GET /referrals?user_id=&limit=&offset= — قائمة الإحالات (أو null). */
    public function referrals(int $userId, int $limit = 20, int $offset = 0): ?array
    {
        if (! $this->statsEnabled() || $userId <= 0) {
            return null;
        }
        return $this->getJson(
            '/referrals?user_id=' . $userId . '&limit=' . $limit . '&offset=' . $offset,
            'referrals'
        );
    }

    /** GET /wallet/transactions?user_id=&limit=&offset= — سجل المعاملات (أو null). */
    public function transactions(int $userId, int $limit = 20, int $offset = 0): ?array
    {
        if (! $this->txEnabled() || $userId <= 0) {
            return null;
        }
        return $this->getJson(
            '/wallet/transactions?user_id=' . $userId . '&limit=' . $limit . '&offset=' . $offset,
            'transactions'
        );
    }

    /** GET /plans?active=1&platform= — قائمة الخطط القابلة للشراء (أو null). */
    public function plansList(string $platform = 'diploma'): ?array
    {
        if (! $this->plansEnabled()) {
            return null;
        }
        return $this->getJson('/plans?active=1&platform=' . urlencode($platform), 'plansList');
    }

    /** GET /subscriptions?user_id= — الاشتراك الفعّال + المجدول (أو null). */
    public function subscriptions(int $userId): ?array
    {
        if (! $this->subsEnabled() || $userId <= 0) {
            return null;
        }
        return $this->getJson('/subscriptions?user_id=' . $userId, 'subscriptions');
    }

    /** GET /admin/subscription-stats — توزيع الاشتراكات للإدارة (أو null). */
    public function adminSubscriptionStats(): ?array
    {
        if (! $this->plansEnabled()) {
            return null;
        }
        return $this->getJson('/admin/subscription-stats', 'adminSubscriptionStats');
    }

    /* ================= عدّادات الاستهلاك (usage) ================= */

    public function usageMode(): string
    {
        return $this->config->usageMode;
    }

    public function usageEnabled(): bool
    {
        return $this->usageMode() === 'api'
            && $this->config->baseUrl !== ''
            && $this->config->apiKey !== '';
    }

    /**
     * GET /usage — لقطة قراءة فقط: used/limit/remaining/allowed (أو null عند التعذّر).
     * $defaultLimit = الحد الافتراضي لمن لا خطة له (null = بلا حد).
     */
    public function usageSnapshot(int $userId, string $featureKey, ?int $defaultLimit = null): ?array
    {
        if (! $this->usageEnabled() || $userId <= 0) {
            return null;
        }
        $q = '/usage?user_id=' . $userId . '&feature_key=' . urlencode($featureKey);
        if ($defaultLimit !== null) {
            $q .= '&default_limit=' . $defaultLimit;
        }
        return $this->getJson($q, 'usageSnapshot');
    }

    /**
     * POST /usage/consume — استهلاك ذرّي check-and-increment.
     * يُرجِع body الكامل (فيه allowed=true|false) أو null عند تعذّر الاتصال.
     */
    public function usageConsume(int $userId, string $featureKey, int $amount = 1, ?int $defaultLimit = null): ?array
    {
        if (! $this->usageEnabled() || $userId <= 0) {
            return null;
        }
        $payload = [
            'user_id'     => $userId,
            'feature_key' => $featureKey,
            'amount'      => $amount,
        ];
        if ($defaultLimit !== null) {
            $payload['default_limit'] = $defaultLimit;
        }
        try {
            $resp = $this->client()->post($this->config->baseUrl . '/usage/consume', [
                'headers' => $this->headers(),
                'json'    => $payload,
            ]);
            if ($resp->getStatusCode() === 200) {
                $data = json_decode((string) $resp->getBody(), true);
                return is_array($data) ? $data : null;
            }
        } catch (Throwable $e) {
            log_message('warning', 'PlansClient::usageConsume failed: ' . $e->getMessage());
        }
        return null;
    }

    /* ================= إغلاق حساب مركزيًا ================= */

    /**
     * POST /account/close — عند حذف حساب من الدبلوم: يلغي اشتراكاته ويصفّر محفظته
     * مركزيًا (تصفير تعويضي يحافظ على الدفتر). best-effort: يُرجِع true عند النجاح.
     */
    public function accountClose(int $userId): bool
    {
        if (! ($this->enabled() || $this->entitlementsEnabled()) || $userId <= 0) {
            return false;
        }
        try {
            $resp = $this->client()->post($this->config->baseUrl . '/account/close', [
                'headers' => $this->headers(),
                'json'    => ['user_id' => $userId],
            ]);
            $ok = $resp->getStatusCode() >= 200 && $resp->getStatusCode() < 300;
            if (! $ok) {
                log_message('error', 'PlansClient::accountClose non-2xx for user ' . $userId . ': ' . $resp->getStatusCode());
            }
            return $ok;
        } catch (Throwable $e) {
            log_message('error', 'PlansClient::accountClose failed for user ' . $userId . ': ' . $e->getMessage());
            return false;
        }
    }

    /* ================= داخلي ================= */

    /**
     * GET مساعد للقراءات: http_errors مُعطّل، try/catch، يُرجِع المصفوفة المفكوكة أو null.
     */
    private function getJson(string $path, string $label): ?array
    {
        try {
            $resp = $this->client()->get($this->config->baseUrl . $path, [
                'headers' => $this->headers(),
            ]);
            if ($resp->getStatusCode() === 200) {
                $data = json_decode((string) $resp->getBody(), true);
                return is_array($data) ? $data : null;
            }
        } catch (Throwable $e) {
            log_message('warning', 'PlansClient::' . $label . ' failed: ' . $e->getMessage());
        }
        return null;
    }


    private function post(string $kind, int $userId, int $points, string $idempotencyKey, array $meta): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'error' => 'disabled'];
        }

        $payload = array_merge([
            'user_id' => $userId,
            'points'  => abs($points),
        ], array_intersect_key($meta, array_flip(['event_type', 'event_ref_type', 'event_ref_id', 'description'])));

        try {
            $resp = $this->client()->post($this->config->baseUrl . '/wallet/' . $kind, [
                'headers' => array_merge($this->headers(), ['Idempotency-Key' => $idempotencyKey]),
                'json'    => $payload,
            ]);

            $status = $resp->getStatusCode();
            $body   = json_decode((string) $resp->getBody(), true);

            return [
                'ok'      => $status >= 200 && $status < 300,
                'status'  => $status,
                'balance' => is_array($body) ? ($body['balance'] ?? null) : null,
                'body'    => is_array($body) ? $body : null,
            ];
        } catch (Throwable $e) {
            log_message('warning', "PlansClient::{$kind} failed (key={$idempotencyKey}): " . $e->getMessage());
            return ['ok' => false, 'error' => 'http_exception'];
        }
    }

    private function client(): CURLRequest
    {
        if ($this->http === null) {
            $this->http = service('curlrequest', [
                'timeout'        => $this->config->timeout,
                'connectTimeout' => $this->config->connectTimeout,
                'verify'         => $this->config->verify,
                'http_errors'    => false, // لا ترمِ استثناءً على 4xx/5xx
            ]);
        }
        return $this->http;
    }

    private function headers(): array
    {
        return [
            'Accept'                    => 'application/json',
            'Content-Type'              => 'application/json',
            $this->config->apiKeyHeader => $this->config->apiKey,
        ];
    }
}

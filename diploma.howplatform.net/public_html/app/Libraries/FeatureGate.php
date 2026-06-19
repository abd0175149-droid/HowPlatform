<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Config\FeatureFlags;

class FeatureGate
{
    public function __construct(private ?FeatureFlags $config = null)
    {
        $this->config ??= config(FeatureFlags::class);
    }

    /**
     * تحقّق إن كانت الميزة مُمكّنة لهذا المستخدم
     *
     * @param string      $feature اسم الميزة
     * @param int|null    $userId  المعرّف إن توفر
     * @param string|null $email   البريد إن توفر
     */
    public function isEnabled(string $feature, ?int $userId = null, ?string $email = null): bool
    {
        $def = $this->config->features[$feature] ?? null;
        if (!$def) {
            return false; // ميزة غير معرّفة
        }

        $mode = $def['mode'] ?? 'off';
        if ($mode === 'off') {
            return false;
        }
        if ($mode === 'public') {
            return true;
        }

        // ====== وضع Beta ======
        $allowIds    = (array) ($def['allowlist_ids']    ?? []);
        $allowEmails = (array) ($def['allowlist_emails'] ?? []);

        if ($userId && in_array((string) $userId, $allowIds, true)) {
            return true;
        }
        if ($email && in_array($email, $allowEmails, true)) {
            return true;
        }

        $rollout = (int) ($def['rollout'] ?? 0);
        return $this->inRollout($feature, $userId, $email, $rollout);
    }

    /** تُفيد الواجهة لإخفاء شارة Beta إن كانت Public */
    public function isPublic(string $feature): bool
    {
        $def = $this->config->features[$feature] ?? null;
        return $def && (($def['mode'] ?? 'off') === 'public');
    }

    /** توزيع ثابت لكل مستخدم داخل نسبة الطرح */
    private function inRollout(string $feature, ?int $userId, ?string $email, int $rollout): bool
    {
        if ($rollout >= 100) {
            return true;
        }
        if ($rollout <= 0) {
            return false;
        }

        // تجزئة ثابتة بناءً على المتاح (userId أو email)
        $key = $userId ? ('u:' . $userId) : ($email ? ('e:' . strtolower(trim($email))) : 'anon:0');
        $hash   = crc32($feature . ':' . $key);
        $bucket = $hash % 100;

        return $bucket < $rollout;
    }
}

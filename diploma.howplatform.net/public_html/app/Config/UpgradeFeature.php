<?php

declare(strict_types=1);

namespace Config;

/**
 * إعدادات ميزة ترقية الطالب/تفعيل البطاقات.
 * - تقرأ من .env:
 *   STUDENT_UPGRADE_UI_ENABLED=true|false
 *   STUDENT_UPGRADE_ALLOWLIST_IDS=1,2,3
 *   STUDENT_UPGRADE_ALLOWLIST_EMAILS=a@b.com,c@d.com
 */
class UpgradeFeature
{
    public bool $enabled;
    public bool $checkByEmail = true;
    public bool $checkById    = true;

    /** @var int[] */
    public array $allowedIds = [];

    /** @var string[] */
    public array $allowedEmails = [];

    public function __construct()
    {
        $this->enabled = $this->envBool('STUDENT_UPGRADE_UI_ENABLED', true);

        $idsCsv = getenv('STUDENT_UPGRADE_ALLOWLIST_IDS') ?: '';
        $emailsCsv = getenv('STUDENT_UPGRADE_ALLOWLIST_EMAILS') ?: '';

        $this->allowedIds    = $this->parseIdsCsv($idsCsv);
        $this->allowedEmails = $this->parseEmailsCsv($emailsCsv);
    }

    private function envBool(string $key, bool $default): bool
    {
        $val = getenv($key);
        if ($val === false || $val === null || $val === '') {
            return $default;
        }
        $val = strtolower(trim((string) $val));
        return \in_array($val, ['1', 'true', 'yes', 'on'], true);
    }

    /** يحوّل "1, 2, 3" إلى [1,2,3] */
    private function parseIdsCsv(string $csv): array
    {
        $out = [];
        foreach (explode(',', $csv) as $part) {
            $part = trim($part);
            if ($part !== '' && ctype_digit($part)) {
                $out[] = (int) $part;
            }
        }
        return $out;
    }

    /** يحوّل "A@x.com, b@y.com" إلى مصفوفة lowercase بدون فراغات */
    private function parseEmailsCsv(string $csv): array
    {
        $out = [];
        foreach (explode(',', $csv) as $part) {
            $part = mb_strtolower(trim($part));
            if ($part !== '') {
                $out[] = $part;
            }
        }
        return $out;
    }

    /** فحص سريع إن كان المستخدم مخوّل */
    public function isUserAllowed(?int $userId, ?string $email): bool
    {
        if (!$this->enabled) {
            return false;
        }
        $byId    = $userId !== null && $this->checkById && \in_array($userId, $this->allowedIds, true);
        $byEmail = false;

        if ($this->checkByEmail && $email) {
            $byEmail = \in_array(mb_strtolower($email), $this->allowedEmails, true);
        }
        return $byId || $byEmail;
    }
}

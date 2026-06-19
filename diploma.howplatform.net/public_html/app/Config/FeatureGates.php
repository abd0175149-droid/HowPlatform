<?php

declare(strict_types=1);

namespace App\Config;

use CodeIgniter\Config\BaseConfig;

class FeatureGates extends BaseConfig
{
    /**
     * هل واجهة ترقية الطالب مفعّلة أساساً؟
     */
    public bool $studentUpgradeEnabled = true;

    /**
     * قوائم السماح (IDs / Emails) تُقرأ من البيئة.
     */
    public array $studentUpgradeAllowIds = [];
    public array $studentUpgradeAllowEmails = [];

    public function __construct()
    {
        parent::__construct();

        // قراءة من .env
        $this->studentUpgradeEnabled = filter_var(
            getenv('STUDENT_UPGRADE_UI_ENABLED') ?: 'false',
            FILTER_VALIDATE_BOOL
        );

        $ids = trim((string) (getenv('STUDENT_UPGRADE_ALLOWLIST_IDS') ?: ''));
        $this->studentUpgradeAllowIds = $ids !== '' ? array_filter(array_map('trim', explode(',', $ids))) : [];

        $emails = trim((string) (getenv('STUDENT_UPGRADE_ALLOWLIST_EMAILS') ?: ''));
        $this->studentUpgradeAllowEmails = $emails !== '' ? array_filter(array_map('trim', explode(',', $emails))) : [];
    }
}

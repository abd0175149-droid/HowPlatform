<?php

declare(strict_types=1);

namespace App\Config;

use CodeIgniter\Config\BaseConfig;

class FeatureFlags extends BaseConfig
{
    /**
     * تعريف الميزات وأوضاعها:
     * mode: off | beta | public
     * rollout: نسبة طرح (0-100) لوضع beta
     * allowlist_ids / allowlist_emails: قوائم سماح في وضع beta
     */
    public array $features = [
        // ميزة صفحة ترقية الطالب
        'upgrade_page' => [
            'mode'             => 'beta', // سنحوّلها لـ public من .env
            'rollout'          => 0,
            'allowlist_ids'    => [],
            'allowlist_emails' => [],
        ],

        // مثال لميزة مستقبلية
        'some_future_feature' => [
            'mode'             => 'beta',
            'rollout'          => 10,
            'allowlist_ids'    => [],
            'allowlist_emails' => [],
        ],

        // نظام المكافآت
        'rewards_system' => [
            'mode'             => 'beta',
            'rollout'          => 0,
            'allowlist_ids'    => ['500'],
            'allowlist_emails' => [],
        ],
    ];

    public function __construct()
    {
        parent::__construct();

        /* ====== توافق خلفي مع البيئة القديمة ====== */
        // STUDENT_UPGRADE_UI_ENABLED=true ⇒ نجعلها public للجميع
        $legacyEnabled = env('STUDENT_UPGRADE_UI_ENABLED', getenv('STUDENT_UPGRADE_UI_ENABLED'));
        if ($legacyEnabled !== false && $legacyEnabled !== null && $legacyEnabled !== '') {
            $enabled = filter_var($legacyEnabled, FILTER_VALIDATE_BOOL);
            $this->features['upgrade_page']['mode'] = $enabled ? 'public' : 'beta';
        }

        // لوائح السماح القديمة (IDs, Emails)
        $ids = trim((string) (env('STUDENT_UPGRADE_ALLOWLIST_IDS', getenv('STUDENT_UPGRADE_ALLOWLIST_IDS')) ?: ''));
        if ($ids !== '') {
            $this->features['upgrade_page']['allowlist_ids'] = array_filter(array_map('trim', explode(',', $ids)));
        }

        $emails = trim((string) (env('STUDENT_UPGRADE_ALLOWLIST_EMAILS', getenv('STUDENT_UPGRADE_ALLOWLIST_EMAILS')) ?: ''));
        if ($emails !== '') {
            $this->features['upgrade_page']['allowlist_emails'] = array_filter(array_map('trim', explode(',', $emails)));
        }

        /* ====== مفاتيح تحكّم جديدة ====== */
        $mode = env('FEATURE_UPGRADE_PAGE_MODE', getenv('FEATURE_UPGRADE_PAGE_MODE'));
        if (is_string($mode) && $mode !== '') {
            $this->features['upgrade_page']['mode'] = strtolower(trim($mode)); // off | beta | public
        }

        $rollout = env('FEATURE_UPGRADE_PAGE_ROLLOUT', getenv('FEATURE_UPGRADE_PAGE_ROLLOUT'));
        if ($rollout !== false && $rollout !== null && $rollout !== '') {
            $this->features['upgrade_page']['rollout'] = (int) $rollout;
        }

        /* ====== نظام المكافآت ====== */
        $rewardsMode = env('FEATURE_REWARDS_SYSTEM_MODE', getenv('FEATURE_REWARDS_SYSTEM_MODE'));
        if (is_string($rewardsMode) && $rewardsMode !== '') {
            $this->features['rewards_system']['mode'] = strtolower(trim($rewardsMode)); // off | beta | public
        }

        $rewardsRollout = env('FEATURE_REWARDS_SYSTEM_ROLLOUT', getenv('FEATURE_REWARDS_SYSTEM_ROLLOUT'));
        if ($rewardsRollout !== false && $rewardsRollout !== null && $rewardsRollout !== '') {
            $this->features['rewards_system']['rollout'] = (int) $rewardsRollout;
        }

        $rewardsIds = trim((string) (env('FEATURE_REWARDS_SYSTEM_ALLOWLIST_IDS', getenv('FEATURE_REWARDS_SYSTEM_ALLOWLIST_IDS')) ?: ''));
        if ($rewardsIds !== '') {
            $this->features['rewards_system']['allowlist_ids'] = array_filter(array_map('trim', explode(',', $rewardsIds)));
        }

        $rewardsEmails = trim((string) (env('FEATURE_REWARDS_SYSTEM_ALLOWLIST_EMAILS', getenv('FEATURE_REWARDS_SYSTEM_ALLOWLIST_EMAILS')) ?: ''));
        if ($rewardsEmails !== '') {
            $this->features['rewards_system']['allowlist_emails'] = array_filter(array_map('trim', explode(',', $rewardsEmails)));
        }
    }
}

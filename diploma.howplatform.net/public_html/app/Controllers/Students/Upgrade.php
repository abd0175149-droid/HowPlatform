<?php

declare(strict_types=1);

namespace App\Controllers\Students;

use App\Controllers\BaseController;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * كنترولر واجهة "الترقية/تفعيل البطاقة" للطالب
 * - page(): يُرجع HTML جزئي لتحميله ديناميكيًا.
 * - state(): يُرجع JSON بحالة الاشتراك + حالة العلم (الميزة).
 *
 * ⚙️ المنطق:
 *  FeatureGate (عام) → .env (FEATURE_UPGRADE_PAGE_MODE) → FeatureGates (قديم) → UpgradeFeature (قديم جداً)
 */
class Upgrade extends BaseController
{
    /**
     * GET /students/pages/upgrade
     * يُستخدم للتحميل الديناميكي من السايدبار.
     * لا نُرجع 403 هنا؛ بل نعرض رسالة داخل الصفحة إن لم يكن مخوّلًا.
     */
    public function page(): string
    {
        $session   = session();
        $userId    = (int) ($session->get('user_id') ?? 0);

        // نجلب البريد من السيشن أو من جدول users كاحتياط
        $userEmail = (string) ($session->get('user_email') ?? '');
        if ($userEmail === '' && $userId > 0) {
            $u = (new UserModel())->find($userId);
            $userEmail = (string) ($u['email'] ?? '');
        }

        // ✅ فحص حالة العلم (الميزة)
        $status = $this->upgradeGateStatus($userId, $userEmail);

        // نجلب حالة الاشتراك لعرضها فورًا — وضع api: من Plans (المصدر الموثوق)،
        // وإلا من علم is_premium المحلي القديم (fallback).
        $userRow      = null;
        $isPremium    = false;
        $premiumUntil = null;

        if ($userId > 0) {
            $fromPlans = false;
            try {
                $client = new \App\Libraries\PlansClient();
                if ($client->entitlementsMode() === 'api') {
                    $subs = $client->subscriptions($userId);
                    if (is_array($subs) && ($subs['ok'] ?? false)) {
                        $cur          = (is_array($subs['current'] ?? null)) ? $subs['current'] : null;
                        $isPremium    = (bool) $cur;
                        $premiumUntil = $cur ? ($cur['end_at'] ?? null) : null;
                        $fromPlans    = true;
                    }
                }
            } catch (\Throwable $e) {
                $fromPlans = false;
            }

            if (! $fromPlans) {
                $userRow = (new UserModel())->find($userId);
                if ($userRow) {
                    $isPremium    = (int) ($userRow['is_premium'] ?? 0) === 1;
                    $premiumUntil = $userRow['premium_until'] ?? null; // نوع DATE
                }
            }
        }

        // إن كانت الميزة متاحة لهذا المستخدم نعرض الصفحة الفعلية
        if ($status['enabled'] === true) {
            return view('students/pages/upgrade', [
                'allowed'       => true,                   // توافقًا مع الواجهة القديمة
                'isPremium'     => $isPremium,
                'premiumUntil'  => $premiumUntil,
                'userName'      => (string) ($session->get('user_name') ?? ''),
                // معلومات مفيدة للواجهة إن رغبت بإخفاء شارة BETA:
                'isPublic'      => $status['isPublic'],
                'showBeta'      => ! $status['isPublic'],
                'featureDebug'  => $status,                // للديبَغ الاختياري
            ]);
        }

        // ❌ غير متاحة: أعد شاشة "تحت التطوير"
        // لو عندك View مخصص، عدّل المسار أدناه
        return view('students/partials/feature_unavailable', [
            'title'   => 'الميزة تحت التطوير',
            'message' => 'هذه الميزة متاحة حاليًا لمجموعة محدودة من الطلاب. تابعنا قريبًا!',
            'feature' => 'upgrade_page',
        ]);
    }

    /**
     * GET /students/pages/upgrade/state
     * تعيد JSON لتستهلكه الواجهة لمعرفة الحالة الحالية + حالة العلم.
     */
    public function state(): ResponseInterface
    {
        $session   = session();
        $userId    = (int) ($session->get('user_id') ?? 0);
        if ($userId <= 0) {
            return $this->response->setStatusCode(401)->setJSON(['status' => 'unauthenticated']);
        }

        // البريد (اختياري)
        $userEmail = (string) ($session->get('user_email') ?? '');
        if ($userEmail === '') {
            $u = (new UserModel())->find($userId);
            $userEmail = (string) ($u['email'] ?? '');
        }

        // ✅ حالة العلم
        $gate = $this->upgradeGateStatus($userId, $userEmail);

        // وضع api: بناء الحالة من Plans (المصدر الموثوق) مع fail-open للـ SQL المحلّي.
        try {
            $client = new \App\Libraries\PlansClient();
            if ($client->entitlementsMode() === 'api') {
                $subs = $client->subscriptions($userId);
                if (is_array($subs) && ($subs['ok'] ?? false)) {
                    $cur = (is_array($subs['current'] ?? null)) ? $subs['current'] : null;
                    $upc = (is_array($subs['upcoming'] ?? null)) ? $subs['upcoming'] : null;

                    // المزايا من features_flat (نحوّلها لشكل [feature_key, limits_json] المتوقع في الواجهة)
                    $features = [];
                    if ($cur) {
                        $ent = $client->entitlements($userId);
                        if (is_array($ent) && is_array($ent['features_flat'] ?? null)) {
                            foreach ($ent['features_flat'] as $fk => $fv) {
                                $features[] = [
                                    'feature_key' => (string) $fk,
                                    'limits_json' => is_scalar($fv) ? (string) $fv : json_encode($fv),
                                ];
                            }
                        }
                    }

                    return $this->response->setJSON([
                        'status'          => 'ok',
                        'feature'         => 'upgrade_page',
                        'feature_enabled' => $gate['enabled'],
                        'is_public'       => $gate['isPublic'],
                        'mode'            => $gate['mode'],
                        'rollout'         => $gate['rollout'],
                        'reason'          => $gate['reason'],

                        'is_premium'     => (bool) $cur,
                        'premium_until'  => $cur ? ($cur['end_at'] ?? null) : null,
                        'current_plan'   => $cur ? [
                            'id'     => (int) ($cur['plan_id'] ?? 0),
                            'name'   => (string) ($cur['name'] ?? ''),
                            'level'  => (int) ($cur['level'] ?? 0),
                            'end_at' => $cur['end_at'] ?? null,
                        ] : null,
                        'upcoming_plan'  => $upc ? [
                            'id'       => (int) ($upc['plan_id'] ?? 0),
                            'name'     => (string) ($upc['name'] ?? ''),
                            'level'    => (int) ($upc['level'] ?? 0),
                            'start_at' => $upc['start_at'] ?? null,
                            'end_at'   => $upc['end_at'] ?? null,
                        ] : null,
                        'features'       => $features,
                    ]);
                }
                // Plans غير متاح → تابع للقراءة المحلّية (fail-open)
            }
        } catch (\Throwable $e) {
            log_message('warning', 'Upgrade::state Plans failed: ' . $e->getMessage());
        }

        // ✅ حالة الاشتراك
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');

        // الاشتراك الفعّال
        $current = $db->query(
            'SELECT us.*, p.name AS plan_name, p.level
               FROM user_subscriptions us
               JOIN plans p ON p.id = us.plan_id
              WHERE us.user_id = ?
                AND us.status = "active"
                AND us.end_at > ?
              ORDER BY us.end_at DESC
              LIMIT 1',
            [$userId, $now]
        )->getRowArray();

        // أقرب مجدول
        $upcoming = $db->query(
            'SELECT us.*, p.name AS plan_name, p.level
               FROM user_subscriptions us
               JOIN plans p ON p.id = us.plan_id
              WHERE us.user_id = ?
                AND us.status = "scheduled"
              ORDER BY us.start_at ASC
              LIMIT 1',
            [$userId]
        )->getRowArray();

        // المزايا الحالية (إن وُجد اشتراك فعّال)
        $features = [];
        if ($current) {
            $features = $db->query(
                'SELECT feature_key, limits_json
                   FROM plan_features
                  WHERE plan_id = ?',
                [(int) $current['plan_id']]
            )->getResultArray();
        }

        return $this->response->setJSON([
            'status'          => 'ok',
            // حالة العلم (الميزة)
            'feature'         => 'upgrade_page',
            'feature_enabled' => $gate['enabled'],
            'is_public'       => $gate['isPublic'],
            'mode'            => $gate['mode'],
            'rollout'         => $gate['rollout'],
            'reason'          => $gate['reason'], // للمساعدة في الديبَغ

            // حالة الاشتراك كما كانت
            'is_premium'     => (bool) $current,
            'premium_until'  => $current ? $current['end_at'] : null,
            'current_plan'   => $current ? [
                'id'     => (int) $current['plan_id'],
                'name'   => $current['plan_name'],
                'level'  => (int) $current['level'],
                'end_at' => $current['end_at'],
            ] : null,
            'upcoming_plan'  => $upcoming ? [
                'id'       => (int) $upcoming['plan_id'],
                'name'     => $upcoming['plan_name'],
                'level'    => (int) $upcoming['level'],
                'start_at' => $upcoming['start_at'],
                'end_at'   => $upcoming['end_at'],
            ] : null,
            'features'       => $features,
        ]);
    }

    /* =========================================================
     * Helpers
     * =======================================================*/

    /**
     * منطق التحقق الموحد: FeatureGate → .env → FeatureGates (القديم) → UpgradeFeature (الأقدم)
     * @return array{enabled: bool, isPublic: bool, mode: string, rollout: int, reason: string}
     */
    private function upgradeGateStatus(int $userId, string $email): array
    {
        // القيم الافتراضية
        $enabled  = false;
        $isPublic = false;
        $mode     = 'off';
        $rollout  = 0;
        $reason   = 'no-gate';

        // ① FeatureGate العام (إن وُجد)
        if (class_exists(\App\Libraries\FeatureGate::class)) {
            /** @var \App\Libraries\FeatureGate $gate */
            $gate = service('featureGate');

            $enabled  = $gate->isEnabled('upgrade_page', $userId ?: null, $email ?: null);
            $isPublic = $gate->isPublic('upgrade_page');

            // قراءة تفاصيل من FeatureFlags لأغراض الديبَغ
            if (class_exists(\App\Config\FeatureFlags::class)) {
                /** @var \App\Config\FeatureFlags $flags */
                $flags = config(\App\Config\FeatureFlags::class);
                $def   = $flags->features['upgrade_page'] ?? null;
                if (is_array($def)) {
                    $mode    = (string) ($def['mode'] ?? 'off');
                    $rollout = (int) ($def['rollout'] ?? 0);
                }
            }
            $reason = 'feature-gate';
        }

        // ② دعم مباشر للبيئة (.env) إن لم يُحسم القرار
        if ($enabled === false) {
            $envModeRaw = function_exists('env')
                ? env('FEATURE_UPGRADE_PAGE_MODE', getenv('FEATURE_UPGRADE_PAGE_MODE'))
                : getenv('FEATURE_UPGRADE_PAGE_MODE');
            $envMode = is_string($envModeRaw) ? strtolower(trim($envModeRaw)) : null;

            if ($envMode === 'public') {
                $enabled  = true;
                $isPublic = true;
                $mode     = 'public';
                $reason   = 'env-mode-public';
            } elseif ($envMode === 'beta') {
                // في حال أردت لاحقًا بيتا عبر env فقط
                $mode   = 'beta';
                $reason = 'env-mode-beta';
            }
        }

        // ③ توافق خلفي مع FeatureGates (الملف القديم)
        if ($enabled === false && class_exists(\App\Config\FeatureGates::class)) {
            /** @var \App\Config\FeatureGates $g */
            $g = config(\App\Config\FeatureGates::class);

            if ($g->studentUpgradeEnabled) {
                $allowIds    = (array) ($g->studentUpgradeAllowIds    ?? []);
                $allowEmails = (array) ($g->studentUpgradeAllowEmails ?? []);

                // لا قوائم ⇒ اعتبرها Public
                if (empty($allowIds) && empty($allowEmails)) {
                    $enabled  = true;
                    $isPublic = true;
                    $mode     = 'public';
                    $reason   = 'legacy-public';
                } else {
                    $okById = $userId > 0 && in_array((string) $userId, $allowIds, true);

                    $okByEmail = false;
                    if ($email !== '') {
                        $needle = mb_strtolower(trim($email));
                        foreach ($allowEmails as $em) {
                            if ($needle === mb_strtolower(trim((string) $em))) {
                                $okByEmail = true;
                                break;
                            }
                        }
                    }

                    if ($okById || $okByEmail) {
                        $enabled  = true;
                        $isPublic = false; // لأنها via allowlist
                        $mode     = 'beta';
                        $reason   = 'legacy-allowlist';
                    }
                }
            }
        }

        // ④ أقدمية: Config\UpgradeFeature (إن كان موجوداً ويحتوي isUserAllowed)
        if ($enabled === false && class_exists(\Config\UpgradeFeature::class)) {
            /** @var object $cfg */
            $cfg = new \Config\UpgradeFeature();
            if (method_exists($cfg, 'isUserAllowed')) {
                $enabled = (bool) $cfg->isUserAllowed($userId ?: null, $email ?: null);
                if ($enabled) {
                    $isPublic = false; // عادة هذه السماحية فردية (بيتا)
                    $mode     = 'beta';
                    $reason   = 'cfg-upgradefeature';
                }
            }
        }

        return [
            'enabled'  => $enabled,
            'isPublic' => $isPublic,
            'mode'     => $mode,
            'rollout'  => $rollout,
            'reason'   => $reason,
        ];
    }
}

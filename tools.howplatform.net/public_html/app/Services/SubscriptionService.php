<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use DateTimeImmutable;
use DateTimeZone;

final class SubscriptionService
{
    private const APP_TZ = 'Asia/Amman';

    public function __construct() {}

    /**
     * تُعيد الخطة الفعّالة + كل الخصائص (limits_json مفكوكة).
     */
    public function getActivePlanForUser(int $userId): ?array
    {
        if ($userId <= 0) return null;

        // وضع api: Plans هو المصدر. fail-open للقراءة المحلّية عند تعذّر Plans.
        $client = new \App\Services\PlansClient();
        if ($client->mode() === 'api') {
            $p = $client->entitlements($userId);
            if ($p !== null) {
                if (! ($p['has_plan'] ?? false)) return null;
                $features = [];
                if (isset($p['features_flat']['classes_allowed'])) {
                    $features['عدد الصفوف المسموح'] = ['Number_of_classes' => (string) $p['features_flat']['classes_allowed']];
                }
                return [
                    'plan_id'  => (int) ($p['plan']['id'] ?? 0),
                    'name'     => (string) ($p['plan']['name'] ?? ''),
                    'level'    => (int) ($p['plan']['level'] ?? 0),
                    'features' => $features,
                ];
            }
            // Plans غير متاح → تابع للقراءة المحلّية (fail-open)
        }

        $db  = DB::connection('diploma'); // ✅ قاعدة الدبلوم
        $now = (new DateTimeImmutable('now', new DateTimeZone(self::APP_TZ)))->format('Y-m-d H:i:s');

        // خطة فعّالة واحدة (الأحدث)
        $row = $db->selectOne("
            SELECT p.id AS plan_id, p.name, p.level
              FROM user_subscriptions us
              JOIN plans p ON p.id = us.plan_id
             WHERE us.user_id = ?
               AND us.status = 'active'
               AND us.start_at <= ?
               AND us.end_at   >= ?
             ORDER BY us.end_at DESC
             LIMIT 1
        ", [$userId, $now, $now]);

        if (!$row) {
            $this->shadowCompare($userId, null);
            return null;
        }

        // جميع الخصائص (feature_key => decoded limits_json)
        $features = $db->table('plan_features')
            ->where('plan_id', $row->plan_id)
            ->pluck('limits_json', 'feature_key');

        $parsed = [];
        foreach ($features as $key => $json) {
            $parsed[$key] = json_decode((string)$json, true) ?: [];
        }

        $result = [
            'plan_id'  => (int)$row->plan_id,
            'name'     => (string)$row->name,
            'level'    => (int)$row->level,
            'features' => $parsed,
        ];

        $this->shadowCompare($userId, $result);
        return $result;
    }

    /**
     * ظلّ صلاحيات Plans: يستدعي Plans ويسجّل المقارنة فقط (لا يغيّر النتيجة المحلّية).
     * مغلَّف بالكامل — أي خطأ لا يؤثّر على القراءة الحيّة.
     */
    private function shadowCompare(int $userId, ?array $local): void
    {
        try {
            $client = new \App\Services\PlansClient();
            if (! $client->enabled()) return;

            $p = $client->entitlements($userId);
            if ($p === null) return;

            // الحدّ المحلّي للصفوف من limits_json (feature_key عربي → {Number_of_classes:N})
            $localClasses = null;
            foreach (($local['features'] ?? []) as $lim) {
                if (is_array($lim) && isset($lim['Number_of_classes'])) { $localClasses = (string) $lim['Number_of_classes']; break; }
            }

            \Illuminate\Support\Facades\Log::info(sprintf(
                '[PlansEntitle][tools] user=%d local=%s(L%s) classes=%s | plans=%s(L%s) classes=%s has_plan=%s',
                $userId,
                $local['name'] ?? '-', $local['level'] ?? '-', $localClasses ?? '-',
                $p['plan']['slug'] ?? '-', $p['plan']['level'] ?? '-',
                $p['features_flat']['classes_allowed'] ?? '-',
                ($p['has_plan'] ?? false) ? '1' : '0'
            ));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('PlansEntitle shadow err: ' . $e->getMessage());
        }
    }
}

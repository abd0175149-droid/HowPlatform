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

        if (!$row) return null;

        // جميع الخصائص (feature_key => decoded limits_json)
        $features = $db->table('plan_features')
            ->where('plan_id', $row->plan_id)
            ->pluck('limits_json', 'feature_key');

        $parsed = [];
        foreach ($features as $key => $json) {
            $parsed[$key] = json_decode((string)$json, true) ?: [];
        }

        return [
            'plan_id'  => (int)$row->plan_id,
            'name'     => (string)$row->name,
            'level'    => (int)$row->level,
            'features' => $parsed,
        ];
    }
}

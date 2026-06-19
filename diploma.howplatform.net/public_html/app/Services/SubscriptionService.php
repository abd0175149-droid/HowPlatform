<?php
declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;

/**
 * تُعيد الخطة الفعّالة للمستخدم (إن وجدت) + daily_download_cap من plan_features.
 */
final class SubscriptionService
{
    private const APP_TZ = 'Asia/Amman';

    public function __construct(private BaseConnection $db) {}

    /**
     * @return array|null ['plan_id','name','level','daily_download_cap']
     */
    public function getActivePlanForUser(int $userId): ?array
    {
        if (\App\Helpers\StudentViewHelper::isAdminViewingAsStudent()) {
            return [
                'plan_id'            => 999,
                'name'               => 'Admin Preview Plan',
                'level'              => 3, // Premium
                'daily_download_cap' => 9999,
                'messages_per_day'   => 9999,
            ];
        }

        if ($userId <= 0) return null;

        $now = (new DateTimeImmutable('now', new DateTimeZone(self::APP_TZ)))->format('Y-m-d H:i:s');

        // اختر اشتراك فعّال واحد (الأحدث زمنياً)
        $row = $this->db->query("
            SELECT p.id AS plan_id, p.name, p.level
              FROM user_subscriptions us
              JOIN plans p ON p.id = us.plan_id
             WHERE us.user_id = ?
               AND us.status = 'active'
               AND us.start_at <= ?
               AND us.end_at   >= ?
             ORDER BY us.end_at DESC
             LIMIT 1
        ", [$userId, $now, $now])->getRowArray();

        if (!$row) return null;

        // اقرأ حدّ اليوم من plan_features('عدد التحميلات لكل يوم')
        $feat = $this->db->query("
            SELECT limits_json
              FROM plan_features
             WHERE plan_id = ?
               AND feature_key = 'عدد التحميلات لكل يوم'
             LIMIT 1
        ", [$row['plan_id']])->getRowArray();

        $dailyCap = 0;
        if ($feat && !empty($feat['limits_json'])) {
            $json = json_decode((string)$feat['limits_json'], true);
            if (is_array($json) && isset($json['limit'])) {
                $dailyCap = (int)$json['limit'];
            }
        }
        
        
        
        // app/Services/SubscriptionService.php

        // داخل getActivePlanForUser
        // بعد قراءة limit من "عدد التحميلات لكل يوم"، نضيف:
        
        $messagesPerDay = null;
        $featMsg = $this->db->query("
            SELECT limits_json
              FROM plan_features
             WHERE plan_id = ?
               AND feature_key = 'استخدام فَطين'
             LIMIT 1
        ", [$row['plan_id']])->getRowArray();
        
        if ($featMsg && !empty($featMsg['limits_json'])) {
            $json = json_decode((string)$featMsg['limits_json'], true);
            if (is_array($json) && isset($json['messages_per_day'])) {
                $messagesPerDay = (int)$json['messages_per_day'];
            }
        }


        return [
            'plan_id'            => (int)$row['plan_id'],
            'name'               => (string)$row['name'],
            'level'              => (int)$row['level'],
            'daily_download_cap' => $dailyCap,
            'messages_per_day'   => $messagesPerDay, // ✨ سقف فَطين
        ];
    }
}

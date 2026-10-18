<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * توليد إشعارات داخلية (جدول notifications) — غير قاتل.
 * (لاحقاً: يُضاف هنا دفع FCM للمستخدم نفسه.)
 */
class NotificationService
{
    public static function push(int $userId, string $title, string $message, ?string $url = null, ?int $typeId = null): void
    {
        if ($userId <= 0) {
            return;
        }
        try {
            $db = \Config\Database::connect();
            $db->table('notifications')->insert([
                'user_id'              => $userId,
                'notification_type_id' => $typeId,
                'title'                => mb_substr($title, 0, 255),
                'message'              => mb_substr($message, 0, 1000),
                'is_read'              => 0,
                'redirect_url'         => $url,
                'created_at'           => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'NotificationService::push failed: ' . $e->getMessage());
        }
        self::fcmFanout([$userId], $title, $message, $url);
    }

    /** إرسال FCM push لأجهزة المستلمين النشطة (غير قاتل). يُعطّل التوكنات غير الصالحة. */
    private static function fcmFanout(array $userIds, string $title, string $message, ?string $url): void
    {
        try {
            $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn ($v) => $v > 0)));
            if (empty($userIds)) {
                return;
            }
            $db = \Config\Database::connect();
            if (! $db->fieldExists('push_token', 'user_devices')) {
                return;
            }
            $rows = $db->table('user_devices')
                ->select('push_token')
                ->whereIn('user_id', $userIds)
                ->where('is_active', 1)
                ->where('push_token IS NOT NULL')
                ->where("push_token !=", '')
                ->get()->getResultArray();
            $tokens = array_values(array_unique(array_filter(array_column($rows, 'push_token'))));
            if (empty($tokens)) {
                return;
            }

            $fb  = new FirebaseAdmin();
            $res = $fb->fcmSendToTokens($tokens, $title, $message, array_filter(['link' => $url]));

            if (! empty($res['invalid'])) {
                $db->table('user_devices')->whereIn('push_token', $res['invalid'])->update(['is_active' => 0]);
            }
        } catch (\Throwable $e) {
            log_message('error', 'NotificationService::fcmFanout failed: ' . $e->getMessage());
        }
    }

    /** إرسال إشعار واحد لعدة مستخدمين دفعة واحدة. */
    public static function pushMany(array $userIds, string $title, string $message, ?string $url = null, ?int $typeId = null): int
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn ($v) => $v > 0)));
        if (empty($userIds)) {
            return 0;
        }
        try {
            $db  = \Config\Database::connect();
            // ملاحظة: لا نفلتر حسب جدول users المحلي — الطلاب بعيدون وليسوا فيه.
            // (أُسقِط قيد FK على notifications.user_id لهذا السبب.)
            $now = date('Y-m-d H:i:s');
            $t   = mb_substr($title, 0, 255);
            $m   = mb_substr($message, 0, 1000);
            $rows = [];
            foreach ($userIds as $uid) {
                $rows[] = [
                    'user_id'              => $uid,
                    'notification_type_id' => $typeId,
                    'title'                => $t,
                    'message'              => $m,
                    'is_read'              => 0,
                    'redirect_url'         => $url,
                    'created_at'           => $now,
                ];
            }
            $db->table('notifications')->insertBatch($rows);
            $n = (int) $db->affectedRows();
            self::fcmFanout($userIds, $title, $message, $url);
            return $n;
        } catch (\Throwable $e) {
            log_message('error', 'NotificationService::pushMany failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * إشعار كل الآدمن (admin=1، teacher=2).
     * المصدر: diploma_users.account_type_id (مصدر الحقيقة للأدوار في الدبلوم)؛
     * user_id فيه = نفس معرّف الجلسة الذي يقرأ به جرس الإشعارات.
     */
    public static function pushToAdmins(string $title, string $message, ?string $url = null): int
    {
        try {
            $db  = \Config\Database::connect();
            $ids = array_column(
                $db->table('diploma_users')->select('user_id')
                    ->whereIn('account_type_id', [1, 2])->get()->getResultArray(),
                'user_id'
            );
            return self::pushMany($ids, $title, $message, $url);
        } catch (\Throwable $e) {
            log_message('error', 'NotificationService::pushToAdmins failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * إشعار الطلاب المستهدفين بمهمة (حسب الجامعة/السنة/التخصص).
     * يتخطّى المهام المجدوَلة للمستقبل (visible_from_date > now) ما لم يُطلب التجاهل.
     */
    public static function pushToTaskStudents(int $taskId, string $title, string $message, ?string $url = null, bool $respectVisibility = true): int
    {
        if ($taskId <= 0) {
            return 0;
        }
        try {
            $db   = \Config\Database::connect();
            $task = $db->table('tasks t')
                ->select('t.id, t.is_for_all_specialties, t.visible_from_date, co.id AS offering_id, wc.week_number, co.university_id, s.academic_year_id')
                ->join('weekly_contents wc', 'wc.id = t.weekly_content_id')
                ->join('course_offerings co', 'co.id = wc.course_offering_id')
                ->join('semesters s', 's.id = co.semester_id')
                ->where('t.id', $taskId)
                ->get()->getRowArray();
            if (! $task) {
                return 0;
            }

            // رابط عميق يفتح المهمة المحددة مباشرة
            $deeplink = '#/task-details?task=' . $taskId
                . '&co=' . (int) ($task['offering_id'] ?? 0)
                . '&week=' . (int) ($task['week_number'] ?? 0);
            $url = $deeplink;

            // لا نُشعر عن مهمة مجدوَلة لم يحن موعد ظهورها
            $vf = $task['visible_from_date'] ?? null;
            if ($respectVisibility && $vf !== null && $vf !== '' && strtotime((string) $vf) > time()) {
                return 0;
            }

            $uni   = (int) ($task['university_id'] ?? 0);
            $year  = (int) ($task['academic_year_id'] ?? 0);
            $isAll = (int) ($task['is_for_all_specialties'] ?? 0);
            if ($uni <= 0 || $year <= 0) {
                return 0;
            }

            $specs = array_column(
                $db->table('task_specialties')->where('task_id', $taskId)->get()->getResultArray(),
                'specialty_id'
            );

            $q = $db->table('diploma_users')->select('user_id')
                ->where('university_id', $uni)->where('academic_year_id', $year);
            if (! $isAll && ! empty($specs)) {
                $q->whereIn('specialty_id', $specs);
            }
            $ids = array_column($q->get()->getResultArray(), 'user_id');

            return self::pushMany($ids, $title, $message, $url);
        } catch (\Throwable $e) {
            log_message('error', 'NotificationService::pushToTaskStudents failed: ' . $e->getMessage());
            return 0;
        }
    }

    /** مقتطف قصير من نص رسالة (لعرضه في الإشعار). */
    public static function snippet(?string $body, int $len = 90): string
    {
        $b = trim((string) $body);
        if ($b === '') {
            return 'مرفق/رسالة جديدة';
        }
        return mb_strlen($b) > $len ? (mb_substr($b, 0, $len) . '…') : $b;
    }
}

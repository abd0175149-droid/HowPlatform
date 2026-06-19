<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/**
 * DiplomaStatsService
 * خدمة لجلب إحصاءات قسم الدبلوم.
 * - اتصال "default" لقاعدة الدبلوم
 * - اتصال "remote" لجلب أسماء المستخدمين (users.name)
 */
class DiplomaStatsService
{
    /** @var BaseConnection */
    private BaseConnection $db;

    /** @var BaseConnection اتصال قاعدة التسجيل (remote) لجلب أسماء المستخدمين */
    private BaseConnection $dbRemote;

    /** التوقيت المحلي للعرض والتفسير */
    private DateTimeZone $tzLocal;

    public function __construct()
    {
        // ✅ قاعدة الدبلوم
        $this->db = Database::connect('default');

        // ✅ قاعدة التسجيل (فيها جدول users)
        $this->dbRemote = Database::connect('remote');

        // ✅ التوقيت المحلي (عمّان)
        $this->tzLocal = new DateTimeZone('Asia/Amman');
    }

    /* ----------------------------------------------------------------------
     * 1) النشطون (DAU / WAU / MAU)
     * -------------------------------------------------------------------- */

    /** إرجاع أعداد DAU/WAU/MAU (آخر 1/7/30 يومًا) */
    public function getActiveUsersSummary(): array
    {
        $now   = new DateTimeImmutable('now');
        $dauAt = $now->sub(new DateInterval('P1D'))->format('Y-m-d H:i:s');
        $wauAt = $now->sub(new DateInterval('P7D'))->format('Y-m-d H:i:s');
        $mauAt = $now->sub(new DateInterval('P30D'))->format('Y-m-d H:i:s');

        $dau = (int) ($this->db->query(
            'SELECT COUNT(*) AS c FROM diploma_users WHERE last_login_at IS NOT NULL AND last_login_at >= ?',
            [$dauAt]
        )->getRowArray()['c'] ?? 0);

        $wau = (int) ($this->db->query(
            'SELECT COUNT(*) AS c FROM diploma_users WHERE last_login_at IS NOT NULL AND last_login_at >= ?',
            [$wauAt]
        )->getRowArray()['c'] ?? 0);

        $mau = (int) ($this->db->query(
            'SELECT COUNT(*) AS c FROM diploma_users WHERE last_login_at IS NOT NULL AND last_login_at >= ?',
            [$mauAt]
        )->getRowArray()['c'] ?? 0);

        $stickiness = $mau > 0 ? round(($dau / $mau) * 100, 2) : null;

        return [
            'dau'        => $dau,
            'wau'        => $wau,
            'mau'        => $mau,
            'stickiness' => $stickiness,
        ];
    }

    /** النشطون حسب الجامعة (day|week|month) */
    public function getActiveByUniversity(string $window = 'day', int $limit = 10): array
    {
        $win = $this->windowToDays($window); // 1/7/30
        $startAt = (new DateTimeImmutable('now'))
            ->sub(new DateInterval('P' . $win . 'D'))
            ->format('Y-m-d H:i:s');

        $sql = "
            SELECT
                COALESCE(u.name, 'غير مُحدّد') AS university,
                COUNT(*) AS active_users
            FROM diploma_users du
            LEFT JOIN universities u ON u.id = du.university_id
            WHERE du.last_login_at IS NOT NULL
              AND du.last_login_at >= ?
            GROUP BY u.name
            ORDER BY active_users DESC
            LIMIT ?
        ";

        $rows = $this->db->query($sql, [$startAt, $limit])->getResultArray();

        return [
            'window' => $window,
            'since'  => $startAt,
            'items'  => $rows,
        ];
    }

    /* ----------------------------------------------------------------------
     * 2) اكتمال التسجيل
     * -------------------------------------------------------------------- */

    public function getRegistrationCompletion(): array
    {
        $rows = $this->db->query("SELECT is_complete, COUNT(*) AS c FROM diploma_users GROUP BY is_complete")
            ->getResultArray();

        $completed = 0;
        $uncompleted = 0;
        foreach ($rows as $r) {
            if ((int) ($r['is_complete'] ?? 0) === 1) {
                $completed = (int) $r['c'];
            } else {
                $uncompleted = (int) $r['c'];
            }
        }

        return [
            'completed'   => $completed,
            'uncompleted' => $uncompleted,
        ];
    }

    /* ----------------------------------------------------------------------
     * 3) توزيع الخطط
     * -------------------------------------------------------------------- */

    public function getPlanDistribution(): array
    {
        $sql = "
            WITH active_sub AS (
                SELECT
                    us.user_id,
                    us.plan_id,
                    us.voucher_code,
                    p.name AS plan_name,
                    ROW_NUMBER() OVER (
                        PARTITION BY us.user_id
                        ORDER BY COALESCE(us.updated_at, us.created_at, NOW()) DESC, us.id DESC
                    ) AS rn
                FROM user_subscriptions us
                INNER JOIN plans p ON p.id = us.plan_id
                WHERE us.status = 'active'
                  AND us.end_at > NOW()
            ),
            one_active AS (
                SELECT
                    user_id,
                    CASE
                        WHEN plan_id = 2 AND voucher_code = 'FreeMonth2025' THEN 'شهر مجاني'
                        ELSE plan_name
                    END AS label
                FROM active_sub
                WHERE rn = 1
            )
            SELECT
                COALESCE(oa.label, 'بدون اشتراك') AS label,
                COUNT(DISTINCT du.id) AS users
            FROM diploma_users du
            LEFT JOIN one_active oa
                   ON oa.user_id = du.user_id
            GROUP BY COALESCE(oa.label, 'بدون اشتراك')
            ORDER BY users DESC, label ASC
        ";

        $rows = $this->db->query($sql)->getResultArray();

        return ['items' => $rows];
    }

    /* ----------------------------------------------------------------------
     * 4) توزيع المستخدمين على الجامعات (إجمالي)
     * -------------------------------------------------------------------- */

    public function getUniversitiesDistribution(int $limit = 50): array
    {
        $sql = "
            SELECT
                COALESCE(u.name, 'غير مُحدّد') AS university,
                COUNT(*) AS users
            FROM diploma_users du
            LEFT JOIN universities u ON u.id = du.university_id
            GROUP BY u.name
            ORDER BY users DESC
            LIMIT ?
        ";

        $rows = $this->db->query($sql, [$limit])->getResultArray();

        return ['items' => $rows];
    }

    /* ----------------------------------------------------------------------
     * 🔵 نشِط/غير نشِط حسب الفترة: day|week|month (تقويمياً)
     * -------------------------------------------------------------------- */
    public function getActiveBreakdown(string $window = 'day'): array
    {
        $window = in_array($window, ['day', 'week', 'month'], true) ? $window : 'day';
        [$startAt, $endAt] = $this->calendarWindowBounds($window);

        $total = (int) ($this->db->query('SELECT COUNT(*) AS c FROM diploma_users')->getRowArray()['c'] ?? 0);

        $active = (int) ($this->db->query(
            'SELECT COUNT(*) AS c
             FROM diploma_users
             WHERE last_login_at IS NOT NULL
               AND last_login_at >= ? AND last_login_at < ?',
            [$startAt, $endAt]
        )->getRowArray()['c'] ?? 0);

        $inactive = max(0, $total - $active);

        return [
            'window'   => $window,
            'start_at' => $startAt,
            'end_at'   => $endAt,
            'total'    => $total,
            'active'   => $active,
            'inactive' => $inactive,
        ];
    }

    /* ----------------------------------------------------------------------
     * 🟣 فَطين: اليوم/الشهر/السنة
     * -------------------------------------------------------------------- */
    public function getFateenUsage(string $bucket = 'day'): array
    {
        $bucket = in_array($bucket, ['day', 'month', 'year'], true) ? $bucket : 'day';

        $now = new DateTimeImmutable('now');
        if ($bucket === 'day') {
            $startAt = $now->setTime(0, 0, 0)->format('Y-m-d H:i:s');
            $endAt   = $now->setTime(0, 0, 0)->modify('+1 day')->format('Y-m-d H:i:s');

            $sql = "
                SELECT
                    HOUR(ful.used_at) AS k,
                    SUM(CASE WHEN ful.success = 1 THEN 1 ELSE 0 END) AS success_count,
                    SUM(CASE WHEN ful.success = 0 THEN 1 ELSE 0 END) AS fail_count
                FROM fateen_usage_logs ful
                WHERE ful.used_at >= ? AND ful.used_at < ?
                GROUP BY k ORDER BY k ASC
            ";
            $rows = $this->db->query($sql, [$startAt, $endAt])->getResultArray();

            $map = [];
            foreach ($rows as $r) { $map[(int) $r['k']] = $r; }

            $series = [];
            for ($h = 0; $h <= 23; $h++) {
                $series[] = [
                    'bucket'        => sprintf('%02d:00', $h),
                    'success_count' => (int) ($map[$h]['success_count'] ?? 0),
                    'fail_count'    => (int) ($map[$h]['fail_count'] ?? 0),
                ];
            }
        } elseif ($bucket === 'month') {
            $start = $now->modify('first day of this month')->setTime(0, 0, 0);
            $end   = $start->modify('first day of next month');

            $startAt = $start->format('Y-m-d H:i:s');
            $endAt   = $end->format('Y-m-d H:i:s');

            $sql = "
                SELECT
                    DATE(ful.used_at) AS k,
                    SUM(CASE WHEN ful.success = 1 THEN 1 ELSE 0 END) AS success_count,
                    SUM(CASE WHEN ful.success = 0 THEN 1 ELSE 0 END) AS fail_count
                FROM fateen_usage_logs ful
                WHERE ful.used_at >= ? AND ful.used_at < ?
                GROUP BY k ORDER BY k ASC
            ";
            $rows = $this->db->query($sql, [$startAt, $endAt])->getResultArray();

            $map = [];
            foreach ($rows as $r) { $map[$r['k']] = $r; }

            $series = [];
            $cursor = $start;
            while ($cursor < $end) {
                $dKey = $cursor->format('Y-m-d');
                $series[] = [
                    'bucket'        => $cursor->format('d'),
                    'success_count' => (int) ($map[$dKey]['success_count'] ?? 0),
                    'fail_count'    => (int) ($map[$dKey]['fail_count'] ?? 0),
                ];
                $cursor = $cursor->modify('+1 day');
            }
        } else {
            $start = $now->setDate((int) $now->format('Y'), 1, 1)->setTime(0, 0, 0);
            $end   = $start->modify('first day of january next year');

            $startAt = $start->format('Y-m-d H:i:s');
            $endAt   = $end->format('Y-m-d H:i:s');

            $sql = "
                SELECT
                    MONTH(ful.used_at) AS k,
                    SUM(CASE WHEN ful.success = 1 THEN 1 ELSE 0 END) AS success_count,
                    SUM(CASE WHEN ful.success = 0 THEN 1 ELSE 0 END) AS fail_count
                FROM fateen_usage_logs ful
                WHERE ful.used_at >= ? AND ful.used_at < ?
                GROUP BY k ORDER BY k ASC
            ";
            $rows = $this->db->query($sql, [$startAt, $endAt])->getResultArray();

            $map = [];
            foreach ($rows as $r) { $map[(int) $r['k']] = $r; }

            $series = [];
            for ($m = 1; $m <= 12; $m++) {
                $series[] = [
                    'bucket'        => (string) $m,
                    'success_count' => (int) ($map[$m]['success_count'] ?? 0),
                    'fail_count'    => (int) ($map[$m]['fail_count'] ?? 0),
                ];
            }
        }

        return [
            'bucket' => $bucket,
            'series' => $series,
            'totals' => [
                'success' => array_sum(array_column($series, 'success_count')),
                'fail'    => array_sum(array_column($series, 'fail_count')),
            ],
        ];
    }

    /* ----------------------------------------------------------------------
     * ✅ قائمة المستخدمين النشطين: الاسم + الجامعة + التخصص (مع باجينيشن)
     * -------------------------------------------------------------------- */

    /**
     * - display_name من remote.users.name
     * - university من universities.name
     * - specialty من specialties.name
     * - النافذة الزمنية تقويمية + Pagination
     */
    public function getActiveUsersList(string $window = 'day', int $page = 1, int $perPage = 5): array
    {
        $window = in_array($window, ['day', 'week', 'month'], true) ? $window : 'day';
        [$startAt, $endAt] = $this->calendarWindowBounds($window);

        $page    = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset  = ($page - 1) * $perPage;

        // إجمالي النشطين ضمن النافذة
        $total = (int) ($this->db->query(
            'SELECT COUNT(*) AS c
               FROM diploma_users
              WHERE last_login_at IS NOT NULL
                AND last_login_at >= ? AND last_login_at < ?',
            [$startAt, $endAt]
        )->getRowArray()['c'] ?? 0);

        if ($total === 0) {
            return [
                'window'   => $window,
                'start_at' => $startAt,
                'end_at'   => $endAt,
                'page'     => $page,
                'per_page' => $perPage,
                'total'    => 0,
                'items'    => [],
            ];
        }

        // صفحة مع الجامعة والتخصص
        $rows = $this->db->query(
            'SELECT
                 du.user_id,
                 du.last_login_at,
                 u.name AS university_name,
                 s.name AS specialty_name
             FROM diploma_users du
             LEFT JOIN universities u ON u.id = du.university_id
             LEFT JOIN specialties  s ON s.id = du.specialty_id
             WHERE du.last_login_at IS NOT NULL
               AND du.last_login_at >= ? AND du.last_login_at < ?
             ORDER BY du.last_login_at DESC
             LIMIT ? OFFSET ?',
            [$startAt, $endAt, $perPage, $offset]
        )->getResultArray();

        // جلب أسماء المستخدمين من قاعدة remote
        $userIds = array_values(array_unique(array_map(
            static fn(array $r) => (int) ($r['user_id'] ?? 0),
            $rows
        )));
        $namesMap = [];
        if (!empty($userIds)) {
            $usersRes = $this->dbRemote->table('users')
                ->select('id, name')
                ->whereIn('id', $userIds)
                ->get()
                ->getResultArray();
            foreach ($usersRes as $u) {
                $namesMap[(int)$u['id']] = (string)($u['name'] ?? '');
            }
        }

        // تنسيق الوقت المحلي (بدون انحراف +3)
        $items = [];
        foreach ($rows as $r) {
            $uid   = (int)($r['user_id'] ?? 0);
            $name  = trim($namesMap[$uid] ?? '');
            $disp  = $name !== '' ? $name : ('مستخدم #' . $uid);

            $rawTime   = (string)($r['last_login_at'] ?? '');
            $localStr  = null;
            $display   = null;

            if ($rawTime !== '') {
                $dtLocal = $this->toLocalAmman($rawTime);
                if ($dtLocal) {
                    $localStr = $dtLocal->format('Y-m-d H:i:s');           // Asia/Amman
                    $display  = $this->formatLocalAmmanToDisplay($dtLocal); // مثال: "19 أكتوبر 11:13 ص"
                }
            }

            $items[] = [
                'id'                 => $uid,
                'display_name'       => $disp,
                'university_name'    => (string)($r['university_name'] ?? 'غير مُحدّد'),
                'specialty_name'     => (string)($r['specialty_name']  ?? 'غير مُحدّد'),
                'last_login_at'      => $rawTime,
                'last_login_local'   => $localStr,
                'last_login_display' => $display,
            ];
        }

        return [
            'window'   => $window,
            'start_at' => $startAt,
            'end_at'   => $endAt,
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
            'items'    => $items,
        ];
    }

    /* ----------------------------------------------------------------------
     * 🤖 إحصائيات ai_jobs (مهام الذكاء الاصطناعي للتاسك)
     * -------------------------------------------------------------------- */

    /**
     * إجمالي الاستخدامات + المستخدمون الفريدون من جدول ai_jobs
     * مع إمكانية الفلترة حسب type_key من ai_job_types
     */
    public function getAiJobStats(?string $typeKey = null): array
    {
        // إجمالي كل الطلبات (أو للنوع المحدد)
        $sql = '
            SELECT
                COUNT(*)                                AS total_uses,
                COUNT(DISTINCT aj.user_id)              AS unique_users,
                SUM(CASE WHEN aj.status = \'done\' THEN 1 ELSE 0 END)       AS done_count,
                SUM(CASE WHEN aj.status = \'failed\' THEN 1 ELSE 0 END)     AS failed_count,
                SUM(CASE WHEN aj.status IN (\'pending\',\'queued\',\'processing\') THEN 1 ELSE 0 END) AS active_count
            FROM ai_jobs aj
        ';

        $params = [];
        if ($typeKey !== null && $typeKey !== '') {
            $sql .= '
                INNER JOIN ai_job_types jt ON jt.id = aj.job_type_id
                WHERE jt.type_key = ?
            ';
            $params[] = $typeKey;
        }

        $row = $this->db->query($sql, $params)->getRowArray();

        // توزيع حسب النوع (top 10)
        $bySql = '
            SELECT
                jt.type_key,
                jt.title        AS type_name,
                COUNT(*)                            AS total_uses,
                COUNT(DISTINCT aj.user_id)          AS unique_users
            FROM ai_jobs aj
            INNER JOIN ai_job_types jt ON jt.id = aj.job_type_id
            GROUP BY jt.type_key, jt.title
            ORDER BY total_uses DESC
            LIMIT 10
        ';
        $byType = $this->db->query($bySql)->getResultArray();

        // تحويل الأعداد
        foreach ($byType as &$r) {
            $r['total_uses']   = (int) $r['total_uses'];
            $r['unique_users'] = (int) $r['unique_users'];
        }
        unset($r);

        return [
            'total_uses'   => (int) ($row['total_uses']   ?? 0),
            'unique_users' => (int) ($row['unique_users'] ?? 0),
            'done_count'   => (int) ($row['done_count']   ?? 0),
            'failed_count' => (int) ($row['failed_count'] ?? 0),
            'active_count' => (int) ($row['active_count'] ?? 0),
            'by_type'      => $byType,
            'type_key'     => $typeKey,
        ];
    }

    /* ----------------------------------------------------------------------
     * أدوات مساعدة
     * -------------------------------------------------------------------- */

    /** يبني DateTimeImmutable محليًا لعمّان من قيمة قاعدة البيانات */
    private function toLocalAmman(string $dbValue): ?DateTimeImmutable
    {
        $dbValue = trim($dbValue);
        if ($dbValue === '') return null;

        try {
            // إذا كان فيها منطقة زمنية صريحة (Z أو +HH:MM/-HH:MM) نحترمها ثم نُحوّل لعمّان
            if (preg_match('/(Z|[+\-]\d{2}:\d{2})$/', $dbValue)) {
                $dt = new DateTimeImmutable($dbValue);
                return $dt->setTimezone($this->tzLocal);
            }

            // بدون TZ: نفسّرها مباشرة كوقت محلي لعمّان (لا نضيف +3)
            return new DateTimeImmutable($dbValue, $this->tzLocal);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** حدود النافذة التقويمية للفترات day|week|month */
    private function calendarWindowBounds(string $window): array
    {
        $now = new DateTimeImmutable('now');
        if ($window === 'day') {
            $start = $now->setTime(0, 0, 0);
            $end   = $start->modify('+1 day');
        } elseif ($window === 'week') {
            // الإثنين بداية الأسبوع (ISO)
            $dow   = (int) $now->format('N'); // 1..7
            $start = $now->modify('-' . ($dow - 1) . ' days')->setTime(0, 0, 0);
            $end   = $start->modify('+7 days');
        } else { // month
            $start = $now->modify('first day of this month')->setTime(0, 0, 0);
            $end   = $start->modify('first day of next month');
        }
        return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
    }

    /** تحويل window النصي إلى عدد الأيام المناظرة */
    private function windowToDays(string $window): int
    {
        switch ($window) {
            case 'week':  return 7;
            case 'month': return 30;
            case 'day':
            default:      return 1;
        }
    }

    /** تنسيق محلي لعمان بنمط 12 ساعة واسم شهر عربي بدون سنة (مثال: "19 أكتوبر 11:13 ص") */
    private function formatLocalAmmanToDisplay(DateTimeImmutable $dtLocal): string
    {
        static $months = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];

        $day    = $dtLocal->format('j');
        $monN   = (int) $dtLocal->format('n');
        $mon    = $months[$monN] ?? $dtLocal->format('F');
        $hour12 = (int) $dtLocal->format('g'); // 1..12
        $min    = $dtLocal->format('i');
        $ampm   = ($dtLocal->format('A') === 'AM') ? 'ص' : 'م';

        return sprintf('%s %s %02d:%s %s', $day, $mon, $hour12, $min, $ampm);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateInterval;
use DateTimeImmutable;

/**
 * LibraryStatsService
 * قراءة إحصاءات "المكتبة" من مجموعة اتصال 'library' فقط.
 * الجداول المعنيّة: files, downloads, visits, file_classifications, grades
 */
class LibraryStatsService
{
    /** @var BaseConnection */
    private BaseConnection $db;

    /** @var string نمط فلترة البوت (case-insensitive) */
    private string $botsRegex = 'bot|crawler|spider|googleother|bingbot';

    public function __construct()
    {
        // ✅ تأكيد الاتصال بمجموعة قاعدة "المكتبة"
        $this->db = Database::connect('library');
    }

    /* --------------------------------------------------------------------
     * أدوات مساعدة
     * ------------------------------------------------------------------ */

    /**
     * يحوّل window (day|week|month|year) إلى بداية نطاق التاريخ.
     * day = آخر 24 ساعة، week = آخر 7 أيام، month = آخر 30 يوم، year = آخر 365 يوم.
     */
    private function windowStart(string $window): string
    {
        $now = new DateTimeImmutable('now');
        switch ($window) {
            case 'week':
                return $now->sub(new DateInterval('P7D'))->format('Y-m-d H:i:s');
            case 'month':
                return $now->sub(new DateInterval('P30D'))->format('Y-m-d H:i:s');
            case 'year':
                return $now->sub(new DateInterval('P365D'))->format('Y-m-d H:i:s');
            case 'day':
            default:
                return $now->sub(new DateInterval('P1D'))->format('Y-m-d H:i:s');
        }
    }

    /** يبني شرط فلترة البوت مع بارامتراته */
    private function botWhere(string $alias = 'd', bool $filterBots = true): array
    {
        if (!$filterBots) {
            return ['sql' => '1=1', 'params' => []];
        }
        // نستخدم LOWER(...) مع REGEXP لتكون الفلترة case-insensitive
        return [
            'sql'    => "({$alias}.user_agent IS NULL OR LOWER({$alias}.user_agent) NOT REGEXP ?)",
            'params' => [$this->botsRegex],
        ];
    }

    /* --------------------------------------------------------------------
     * 1) عدد مرات التحميل
     * ------------------------------------------------------------------ */

    /**
     * إحصائية التنزيلات:
     * - overall: إجمالي كل التنزيلات (سريع عبر SUM(files.downloads_count))
     * - window : إجمالي ضمن نافذة زمنية من جدول downloads (اختياري)
     */
    public function getDownloadsSummary(?string $window = null, bool $filterBots = true): array
    {
        // إجمالي سريع عبر files.downloads_count
        $overall = (int) ($this->db->query("SELECT COALESCE(SUM(downloads_count), 0) AS c FROM files")
            ->getRowArray()['c'] ?? 0);

        $windowTotal = null;
        if ($window) {
            $start = $this->windowStart($window);
            $bot   = $this->botWhere('d', $filterBots);
            $sql   = "
                SELECT COUNT(*) AS c
                FROM downloads d
                WHERE d.created_at >= ?
                  AND {$bot['sql']}
            ";
            $row   = $this->db->query($sql, array_merge([$start], $bot['params']))->getRowArray();
            $windowTotal = (int) ($row['c'] ?? 0);
        }

        return [
            'overall'      => $overall,
            'window'       => $window,
            'window_total' => $windowTotal,
        ];
    }

    /* --------------------------------------------------------------------
     * 2) الصفوف ذات أعلى معدل تحميل
     * ------------------------------------------------------------------ */

    /**
     * أعلى الصفوف تحميلًا.
     * - $normalized = false ⇒ ترتيب حسب "إجمالي التنزيلات".
     * - $normalized = true  ⇒ ترتيب حسب "معدل التنزيل لكل ملف" (downloads / عدد الملفات).
     */
    public function getTopGradesByDownloads(
        int $limit = 10,
        string $window = 'month',
        bool $normalized = false,
        bool $filterBots = true
    ): array {
        $start = $this->windowStart($window);
        $bot   = $this->botWhere('d', $filterBots);

        if (!$normalized) {
            // إجمالي التنزيلات لكل صف (RAW)
            $sql = "
                SELECT
                    COALESCE(g.name_ar, 'غير مُحدّد') AS grade,
                    COUNT(*) AS downloads
                FROM downloads d
                JOIN file_classifications fc ON fc.file_id = d.file_id
                LEFT JOIN grades g           ON g.id = fc.grade_id
                WHERE d.created_at >= ?
                  AND {$bot['sql']}
                GROUP BY g.id
                ORDER BY downloads DESC
                LIMIT ?
            ";
            $rows = $this->db->query($sql, array_merge([$start], $bot['params'], [$limit]))->getResultArray();

            return [
                'window' => $window,
                'mode'   => 'raw',
                'items'  => $rows,
            ];
        }

        // معدل التنزيل = (downloads / عدد الملفات ضمن الصف)
        $sql = "
            WITH d_by_grade AS (
                SELECT
                    fc.grade_id,
                    COUNT(*) AS downloads
                FROM downloads d
                JOIN file_classifications fc ON fc.file_id = d.file_id
                WHERE d.created_at >= ?
                  AND {$bot['sql']}
                GROUP BY fc.grade_id
            ),
            files_by_grade AS (
                SELECT
                    fc.grade_id,
                    COUNT(DISTINCT fc.file_id) AS files_count
                FROM file_classifications fc
                GROUP BY fc.grade_id
            )
            SELECT
                COALESCE(g.name_ar, 'غير مُحدّد') AS grade,
                COALESCE(dg.downloads, 0)         AS downloads,
                COALESCE(fg.files_count, 0)       AS files_count,
                CASE WHEN COALESCE(fg.files_count, 0) = 0
                     THEN 0
                     ELSE ROUND(dg.downloads / fg.files_count, 3)
                END AS rate_per_file
            FROM grades g
            LEFT JOIN d_by_grade     dg ON dg.grade_id = g.id
            LEFT JOIN files_by_grade fg ON fg.grade_id = g.id
            WHERE COALESCE(dg.downloads, 0) > 0
            ORDER BY rate_per_file DESC, downloads DESC
            LIMIT ?
        ";
        $rows = $this->db->query($sql, array_merge([$start], $bot['params'], [$limit]))->getResultArray();

        return [
            'window' => $window,
            'mode'   => 'normalized',
            'items'  => $rows,
        ];
    }

    /* --------------------------------------------------------------------
     * 3) مقارنة تنزيلات بمستخدم (user_id) مقابل بدون
     * ------------------------------------------------------------------ */

    /**
     * مقارنة عدد التنزيلات الموثّقة (user_id IS NOT NULL) مقابل المجهولة.
     */
    public function getDownloadsAuthVsAnon(string $window = 'month', bool $filterBots = true): array
    {
        $start = $this->windowStart($window);
        $bot   = $this->botWhere('d', $filterBots);

        $sql = "
            SELECT
                SUM(CASE WHEN d.user_id IS NOT NULL THEN 1 ELSE 0 END) AS authenticated,
                SUM(CASE WHEN d.user_id IS NULL     THEN 1 ELSE 0 END) AS anonymous
            FROM downloads d
            WHERE d.created_at >= ?
              AND {$bot['sql']}
        ";
        $row = $this->db->query($sql, array_merge([$start], $bot['params']))->getRowArray();

        $auth = (int) ($row['authenticated'] ?? 0);
        $anon = (int) ($row['anonymous'] ?? 0);
        $sum  = $auth + $anon;

        return [
            'window'        => $window,
            'authenticated' => $auth,
            'anonymous'     => $anon,
            'share'         => [
                'authenticated_pct' => $sum > 0 ? round(($auth / $sum) * 100, 2) : 0.0,
                'anonymous_pct'     => $sum > 0 ? round(($anon / $sum) * 100, 2) : 0.0,
            ],
        ];
    }

    /* --------------------------------------------------------------------
     * 4) إحصائية عدد زيارات الموقع
     * ------------------------------------------------------------------ */

    /**
     * إجمالي الزيارات ضمن نافذة زمنية. يمكن فلترة البوت (اختياري).
     */
    public function getVisitsSummary(string $window = 'month', bool $filterBots = true): array
    {
        $start = $this->windowStart($window);

        // فلترة بوت محتملة من جدول visits أيضاً
        $where = '1=1';
        $params = [$start];

        if ($filterBots) {
            $where   = '(v.user_agent IS NULL OR LOWER(v.user_agent) NOT REGEXP ?)';
            $params  = [$start, $this->botsRegex];
        }

        $sql = "
            SELECT COUNT(*) AS visits
            FROM visits v
            WHERE v.created_at >= ?
              AND {$where}
        ";

        $row = $this->db->query($sql, $params)->getRowArray();

        return [
            'window' => $window,
            'visits' => (int) ($row['visits'] ?? 0),
        ];
    }
}

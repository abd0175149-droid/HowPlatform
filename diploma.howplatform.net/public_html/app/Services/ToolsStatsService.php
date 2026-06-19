<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateTimeImmutable;
use DateTimeZone;

/**
 * ToolsStatsService
 * - القراءة من اتصال "tools" فقط
 * - يعتمد على: tools, tool_stats
 * - يعيد بطاقات لكل أداة: uses_count, unique_users_count, rating_avg, last_used_at (محلي)
 *
 * ⚙️ التحويل الزمني:
 *   - افتراضيًا لا نقوم بأي تحويل حتى لا يحصل انزياح.
 *   - يمكن التفعيل عبر .env:
 *       timezone.tools_convert = true
 *       timezone.tools_source  = UTC | Asia/Amman | ...
 *       timezone.display       = Asia/Amman | ...
 */
class ToolsStatsService
{
    /** @var BaseConnection */
    private BaseConnection $db;

    /** إعدادات التحويل (اختيارية) */
    private bool $doConvert = false;
    private ?DateTimeZone $srcTz = null;
    private DateTimeZone $dstTz;

    public function __construct()
    {
        // ✅ اتصال قاعدة الأدوات فقط (قراءة)
        $this->db = Database::connect('tools');

        // ✅ إعداد التحويل من .env (افتراضيًا مُعطّل لتجنّب أي انزياح)
        $this->doConvert = (bool) env('timezone.tools_convert', false);

        $dst = (string) env('timezone.display', 'Asia/Amman');
        $this->dstTz = new DateTimeZone($dst);

        if ($this->doConvert) {
            $src       = (string) env('timezone.tools_source', 'UTC');
            $this->srcTz = new DateTimeZone($src);
        }
    }

    /**
     * إرجاع بطاقات الأدوات:
     * - يمكن تصفية الأدوات المفعّلة فقط (only_active=1)
     * - ترتيب افتراضي: الأكثر استخدامًا ثم الاسم
     *
     * @param bool     $onlyActive تصفية الأدوات where tools.is_active=1
     * @param int|null $limit      حد أقصى لعدد البطاقات (اختياري)
     * @return array<int, array<string, mixed>>
     */
    public function getToolCards(bool $onlyActive = true, ?int $limit = null): array
    {
        // ⚙️ شروط WHERE ديناميكية
        $wheres = ['ts.tool_id IS NOT NULL'];
        if ($onlyActive) {
            $wheres[] = 't.is_active = 1';
        }
        $whereSql = 'WHERE ' . implode(' AND ', $wheres);

        $limitSql = (is_int($limit) && $limit > 0) ? ('LIMIT ' . (int) $limit) : '';

        // نقرأ الوقت كما هو من DB (raw)
        $sql = "
            SELECT
                t.id                   AS tool_id,
                t.name                 AS tool_name,
                ts.uses_count          AS uses_count,
                ts.unique_users_count  AS unique_users_count,
                ts.rating_avg          AS rating_avg,
                ts.rating_count        AS rating_count,
                ts.last_used_at        AS last_used_at_raw
            FROM tool_stats ts
            INNER JOIN tools t ON t.id = ts.tool_id
            {$whereSql}
            ORDER BY ts.uses_count DESC, t.name ASC
            {$limitSql}
        ";

        $rows = $this->db->query($sql)->getResultArray();

        foreach ($rows as &$r) {
            // ✅ Casting صريح
            $r['tool_id']             = (int) ($r['tool_id'] ?? 0);
            $r['uses_count']          = (int) ($r['uses_count'] ?? 0);
            $r['unique_users_count']  = (int) ($r['unique_users_count'] ?? 0);
            $r['rating_count']        = (int) ($r['rating_count'] ?? 0);
            $r['rating_avg']          = (isset($r['rating_avg']) && is_numeric($r['rating_avg']))
                ? (float) $r['rating_avg']
                : null;

            // ✅ الاسم
            $r['name'] = (string) ($r['tool_name'] ?? 'Tool');
            unset($r['tool_name']);

            // الوقت الخام من DB
            $raw = $r['last_used_at_raw'] ?? null;

            // سننتج الحقول التالية دائمًا:
            // - last_used_at_local       (كامل Y-m-d H:i:s)
            // - last_used_at_local_short (مختصر Y-m-d H:i)
            // - last_used_date_label     (اليوم رقم + اسم الشهر بالعربية، بدون سنة)
            // - last_used_time_label     (وقت 12 ساعة + صباحًا/مساءً)
            // - last_used_display        (دمج بسيط: "<يوم> <شهر> - <وقت 12h>")
            if (is_string($raw) && $raw !== '') {
                try {
                    if ($this->doConvert && $this->srcTz) {
                        // تحويل من src → dst
                        $dtSrc   = new DateTimeImmutable($raw, $this->srcTz);
                        $dtLocal = $dtSrc->setTimezone($this->dstTz);
                    } else {
                        // بدون تحويل: اعتبر النص في منطقة العرض نفسها (dst)
                        $dtLocal = new DateTimeImmutable($raw, $this->dstTz);
                    }

                    // 1) تنسيقات قديمة (للتوافق)
                    $r['last_used_at_local']       = $dtLocal->format('Y-m-d H:i:s');
                    $r['last_used_at_local_short'] = $dtLocal->format('Y-m-d H:i');

                    // 2) تنسيق التاريخ: اليوم رقم + اسم الشهر بالكلمات (بدون سنة)
                    $monthFormatter = new \IntlDateFormatter(
                        'ar_JO',
                        \IntlDateFormatter::NONE,
                        \IntlDateFormatter::NONE,
                        $this->dstTz->getName(),
                        \IntlDateFormatter::GREGORIAN,
                        'MMMM'
                    );
                    $monthName = $monthFormatter->format($dtLocal);
                    if ($monthName === false) {
                        $monthName = $this->fallbackArabicMonth((int) $dtLocal->format('n'));
                    }
                    $dayNumber = ltrim($dtLocal->format('j'), '0');
                    $r['last_used_date_label'] = $dayNumber . ' ' . $monthName;

                    // 3) تنسيق الوقت: 12 ساعة + (صباحًا/مساءً)
                    $hour12  = $dtLocal->format('g:i');           // 1..12
                    $isAm    = ((int) $dtLocal->format('G') < 12);
                    $suffix  = $isAm ? 'صباحًا' : 'مساءً';
                    $r['last_used_time_label'] = $hour12 . ' ' . $suffix;

                    // 4) دمج نهائي للاستخدام في الواجهة بسهولة
                    $r['last_used_display'] = $r['last_used_date_label'] . ' - ' . $r['last_used_time_label'];
                } catch (\Throwable $e) {
                    $r['last_used_at_local']        = null;
                    $r['last_used_at_local_short']  = null;
                    $r['last_used_date_label']      = null;
                    $r['last_used_time_label']      = null;
                    $r['last_used_display']         = null;
                }
            } else {
                $r['last_used_at_local']        = null;
                $r['last_used_at_local_short']  = null;
                $r['last_used_date_label']      = null;
                $r['last_used_time_label']      = null;
                $r['last_used_display']         = null;
            }

            // تنظيف
            unset($r['last_used_at_raw']);
        }
        unset($r); // فك المرجع

        return $rows;
    }

    /**
     * Fallback لأسماء الشهور العربية إذا تعذّر IntlDateFormatter
     */
    private function fallbackArabicMonth(int $m): string
    {
        static $months = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];
        return $months[$m] ?? '';
    }
}

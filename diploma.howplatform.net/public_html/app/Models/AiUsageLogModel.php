<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;

class AiUsageLogModel extends Model
{
    /** جدول السجلات */
    protected $table            = 'ai_usage_logs';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    /** الأعمدة المسموحة (للتوثيق فقط) */
    protected $allowedFields    = ['user_id', 'used_at', 'success'];

    /**
     * دالة تجميع الاستخدام حسب النطاق (اليوم/الشهر/السنة).
     * تُعيد مصفوفة بالملصقات (labels) وقيم ناجحة/فاشلة لكل خانة زمنية.
     *
     * @param string $range  one of: day|month|year
     * @return array{labels: string[], success: int[], fail: int[]}
     */
    public function aggregateByRange(string $range = 'day'): array
    {
        $range = in_array($range, ['day', 'month', 'year'], true) ? $range : 'day';

        // 🟦 تحديد الإطار الزمني وحدات التجميع (bucket) وتنسيق الملصقات
        $now = new DateTimeImmutable('now'); // ملاحظة: اعتماد توقيت الخادم
        switch ($range) {
            case 'year':
                // السنة الحالية بالشهور
                $start = $now->modify('first day of january midnight');
                $end   = $start->modify('first day of january next year'); // حصري
                $interval = new DateInterval('P1M');
                $formatDb = '%Y-%m'; // للتجميع SQL
                $labels   = $this->arabicMonths(); // أسماء أشهر عربية
                $buckets  = $this->initMonthBuckets($start);
                break;

            case 'month':
                // الشهر الحالي بالأيام
                $start = $now->modify('first day of this month midnight');
                $end   = $start->modify('first day of next month'); // حصري
                $interval = new DateInterval('P1D');
                $formatDb = '%Y-%m-%d';
                $labels   = $this->daysOfMonthLabels($start);
                $buckets  = $this->initDayBuckets($start);
                break;

            case 'day':
            default:
                // آخر 24 ساعة بالساعات
                $end   = $now->modify('this hour'); // إلى بداية الساعة الحالية
                $start = $end->sub(new DateInterval('P1D')); // 24 ساعة مضت
                $interval = new DateInterval('PT1H');
                $formatDb = '%Y-%m-%d %H:00:00';
                $labels   = $this->last24hHourLabels($start, $end);
                $buckets  = $this->initHourBuckets($start, $end);
                break;
        }

        // 🟦 جلب القيم مجمعة من DB
        // مخرجات SQL: bucket, success_count, fail_count
        $builder = $this->builder();
        $builder->select("
            DATE_FORMAT(used_at, '{$formatDb}') AS bucket,
            SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) AS success_count,
            SUM(CASE WHEN success = 1 THEN 0 ELSE 1 END) AS fail_count
        ");
        $builder->where('used_at >=', $start->format('Y-m-d H:i:s'));
        $builder->where('used_at <',  $end->format('Y-m-d H:i:s'));
        $builder->groupBy('bucket');
        $builder->orderBy('bucket', 'ASC');
        $rows = $builder->get()->getResultArray();

        // 🟦 دمج النتائج في القوالب (zero-fill للأوقات التي لا بيانات لها)
        foreach ($rows as $r) {
            $bucket = $r['bucket'];
            if (!array_key_exists($bucket, $buckets)) {
                // في حال اختلاف تنسيقات طفيفة
                $buckets[$bucket] = ['success' => 0, 'fail' => 0];
            }
            $buckets[$bucket]['success'] = (int) ($r['success_count'] ?? 0);
            $buckets[$bucket]['fail']    = (int) ($r['fail_count'] ?? 0);
        }

        // 🟦 إخراج نهائي متوافق مع الواجهة
        $success = [];
        $fail    = [];
        $orderedLabels = array_keys($buckets);
        foreach ($orderedLabels as $idx => $key) {
            $success[] = $buckets[$key]['success'];
            $fail[]    = $buckets[$key]['fail'];
        }

        // استبدال labels الخام بأخرى عربية مناسبة عند الحاجة
        switch ($range) {
            case 'year':
                // labels = الأشهر العربية (يناير..ديسمبر) حتى الشهر الحالي
                $monthCount = (int) $now->format('n'); // 1..12
                $labels = array_slice($labels, 0, $monthCount);
                break;

            case 'month':
                // labels = 1..عدد أيام الشهر
                // تم توليدها أعلاه بالفعل
                break;

            case 'day':
                // labels = ساعات (24 خانة) "HH:00"
                $labels = $this->humanHourLabels($orderedLabels);
                break;
        }

        return [
            'labels'  => $labels,
            'success' => $success,
            'fail'    => $fail,
        ];
    }

    /* ===================== مساعدات إنشاء الملصقات والحاويات ===================== */

    /** أسماء الأشهر بالعربية */
    private function arabicMonths(): array
    {
        return ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
    }

    /** تهيئة حاويات الشهور (YYYY-mm) من بداية السنة وحتى الشهر الحالي */
    private function initMonthBuckets(DateTimeImmutable $yearStart): array
    {
        $buckets = [];
        for ($m = 1; $m <= 12; $m++) {
            $key = $yearStart->setDate((int) $yearStart->format('Y'), $m, 1)->format('Y-m');
            $buckets[$key] = ['success' => 0, 'fail' => 0];
        }
        return $buckets;
    }

    /** ملصقات أيام الشهر: [1, 2, ..., N] كنصوص */
    private function daysOfMonthLabels(DateTimeImmutable $monthStart): array
    {
        $days = (int) $monthStart->format('t');
        $labels = [];
        for ($d = 1; $d <= $days; $d++) {
            $labels[] = (string) $d;
        }
        return $labels;
    }

    /** تهيئة حاويات الأيام (YYYY-mm-dd) */
    private function initDayBuckets(DateTimeImmutable $monthStart): array
    {
        $days = (int) $monthStart->format('t');
        $buckets = [];
        for ($d = 1; $d <= $days; $d++) {
            $key = $monthStart->setDate((int) $monthStart->format('Y'), (int) $monthStart->format('m'), $d)
                ->format('Y-m-d');
            $buckets[$key] = ['success' => 0, 'fail' => 0];
        }
        return $buckets;
    }

    /** إنشاء ملصقات الساعات الإنسانية "HH:00" من مفاتيح buckets */
    private function humanHourLabels(array $bucketKeys): array
    {
        $labels = [];
        foreach ($bucketKeys as $k) {
            // k بالشكل "YYYY-mm-dd HH:00:00"
            $labels[] = substr($k, 11, 2) . ':00';
        }
        return $labels;
    }

    /** تهيئة حاويات الساعات لآخر 24 ساعة (مفاتيح على شكل "YYYY-mm-dd HH:00:00") */
    private function initHourBuckets(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $period = new DatePeriod($start, new DateInterval('PT1H'), $end->add(new DateInterval('PT1H')));
        $buckets = [];
        foreach ($period as $dt) {
            $key = $dt->format('Y-m-d H:00:00');
            $buckets[$key] = ['success' => 0, 'fail' => 0];
        }
        return $buckets;
    }

    /** إنشاء ملصقات الساعات لآخر 24 ساعة (مرجع فقط) */
    private function last24hHourLabels(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $period = new DatePeriod($start, new DateInterval('PT1H'), $end->add(new DateInterval('PT1H')));
        $labels = [];
        foreach ($period as $dt) {
            $labels[] = $dt->format('H:00');
        }
        return $labels;
    }
}

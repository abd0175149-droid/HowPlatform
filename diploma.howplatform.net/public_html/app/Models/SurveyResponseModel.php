<?php

namespace App\Models;

use CodeIgniter\Model;
use DateTime;
use DateTimeZone;

class SurveyResponseModel extends Model
{
    protected $table      = 'survey_responses';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'user_id',
        'how_known_source',
        'other_source_text',
        'join_reasons',
        'other_reason_text',
        'suggested_services',
        'device_used',
        'satisfaction_level',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /* ======================= مساعد حدود الفترة ======================= */
    private function getRangeBounds(string $range): array
    {
        $range = in_array($range, ['day', 'week', 'month'], true) ? $range : 'day';
        $tz  = new DateTimeZone('Asia/Amman');
        $now = new DateTime('now', $tz);

        if ($range === 'day') {
            $start = (clone $now)->setTime(0, 0, 0);
            $end   = (clone $start)->modify('+1 day');
        } elseif ($range === 'week') {
            // بداية الأسبوع: الأحد
            $start  = (clone $now)->setTime(0, 0, 0);
            $phpDow = (int) $start->format('w'); // 0=الأحد .. 6=السبت
            $start->modify("-{$phpDow} day");
            $end = (clone $start)->modify('+1 week');
        } else { // month
            $start = new DateTime($now->format('Y-m-01 00:00:00'), $tz);
            $end   = (clone $start)->modify('+1 month');
        }
        return [$start, $end];
    }

    /* ======================= الرضا: API المعتمدة من الواجهة ======================= */
    /**
     * تُعيد:
     *  - day   : labels=['اليوم'], satisfaction[avg], counts[total], total_responses
     *  - week  : labels[7 أيام من الأحد→السبت], satisfaction[7], counts[7], total_responses
     *  - month : labels=['الأسبوع 1..n داخل الشهر'], satisfaction[], counts[], total_responses
     */
    public function countSatisfactionByRange(string $range = 'day'): array
    {
        $range = in_array($range, ['day', 'week', 'month'], true) ? $range : 'day';

        if ($range === 'day') {
            [$start, $end] = $this->getRangeBounds('day');
            return $this->aggregateDay($start, $end);
        }

        if ($range === 'week') {
            [$start, $end] = $this->getRangeBounds('week');
            return $this->aggregateWeek($start, $end);
        }

        // month
        [$start, $end] = $this->getRangeBounds('month');
        return $this->aggregateMonthWeeks($start, $end);
    }

    /** 🔹 اليوم: عمود واحد */
    private function aggregateDay(DateTime $start, DateTime $end): array
    {
        $qb = $this->builder();
        $qb->select('COUNT(*) AS cnt, AVG(satisfaction_level) AS avg_val', false)
           ->where('created_at >=', $start->format('Y-m-d H:i:s'))
           ->where('created_at <',  $end->format('Y-m-d H:i:s'));

        $q = $qb->get();
        $row = $q ? $q->getRowArray() : null;

        $cnt = (int) ($row['cnt'] ?? 0);
        $avg = $row['avg_val'];
        $avg = is_null($avg) ? null : (float) $avg;

        return [
            'labels'          => ['اليوم'],
            'satisfaction'    => [$avg],
            'counts'          => [$cnt],
            'total_responses' => $cnt,
        ];
    }

    /** 🔹 الأسبوع: 7 أعمدة (الأحد..السبت) */
    private function aggregateWeek(DateTime $start, DateTime $end): array
    {
        $qb = $this->builder();
        $qb->select('DATE(created_at) AS d, DAYOFWEEK(created_at) AS dow, COUNT(*) AS cnt, AVG(satisfaction_level) AS avg_val', false)
           ->where('created_at >=', $start->format('Y-m-d H:i:s'))
           ->where('created_at <',  $end->format('Y-m-d H:i:s'))
           ->groupBy('d, dow')
           ->orderBy('dow', 'ASC');

        $q = $qb->get();
        $rows = $q ? $q->getResultArray() : [];

        // DAYOFWEEK: 1=الأحد .. 7=السبت
        $labels       = ['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'];
        $satisfaction = array_fill(0, 7, null);
        $counts       = array_fill(0, 7, 0);

        foreach ($rows as $r) {
            $idx = max(1, min(7, (int) $r['dow'])) - 1; // 0..6
            $counts[$idx]       += (int) ($r['cnt'] ?? 0);
            $satisfaction[$idx]  = is_null($r['avg_val']) ? null : (float) $r['avg_val'];
        }

        return [
            'labels'          => $labels,
            'satisfaction'    => $satisfaction,
            'counts'          => $counts,
            'total_responses' => array_sum($counts),
        ];
    }

    /** 🔹 الشهر: أسابيع الشهر (1..n) بداية الأسبوع الأحد */
    private function aggregateMonthWeeks(DateTime $start, DateTime $end): array
    {
        // week_in_month = ((DAY(date) + (DAYOFWEEK(first_of_month)-1) - 1) DIV 7) + 1
        $weekExpr = "(((DAY(created_at) + (DAYOFWEEK(DATE_FORMAT(created_at,'%Y-%m-01')) - 1) - 1) DIV 7) + 1)";
        $qb = $this->builder();
        $qb->select("$weekExpr AS wim, COUNT(*) AS cnt, AVG(satisfaction_level) AS avg_val", false)
           ->where('created_at >=', $start->format('Y-m-d H:i:s'))
           ->where('created_at <',  $end->format('Y-m-d H:i:s'))
           ->groupBy('wim')
           ->orderBy('wim', 'ASC');

        $q = $qb->get();
        $rows = $q ? $q->getResultArray() : [];

        $labels       = [];
        $satisfaction = [];
        $counts       = [];

        foreach ($rows as $r) {
            $w   = (int) ($r['wim'] ?? 0);
            $cnt = (int) ($r['cnt'] ?? 0);
            $avg = $r['avg_val'];
            $labels[]       = 'الأسبوع ' . $w;
            $counts[]       = $cnt;
            $satisfaction[] = is_null($avg) ? null : (float) $avg;
        }

        return [
            'labels'          => $labels,
            'satisfaction'    => $satisfaction,
            'counts'          => $counts,
            'total_responses' => array_sum($counts),
        ];
    }

    /* ======================= الأجهزة المستخدمة (كارد جديد) ======================= */
    public function devicesBreakdown(string $range = 'month'): array
    {
        [$start, $end] = $this->getRangeBounds($range);

        $qb = $this->builder();
        $qb->select("COALESCE(NULLIF(TRIM(device_used), ''), 'غير محدد') AS dev, COUNT(*) AS c", false)
           ->where('created_at >=', $start->format('Y-m-d H:i:s'))
           ->where('created_at <',  $end->format('Y-m-d H:i:s'))
           ->groupBy('dev')
           ->orderBy('c', 'DESC');

        $q = $qb->get();
        $rows = $q ? $q->getResultArray() : [];

        $labels = [];
        $counts = [];
        $total  = 0;

        foreach ($rows as $r) {
            $label    = $r['dev'] ?? 'غير محدد';
            $count    = (int) ($r['c'] ?? 0);
            $labels[] = $label;
            $counts[] = $count;
            $total   += $count;
        }

        return [
            'labels'          => $labels,
            'counts'          => $counts,
            'total_responses' => $total,
        ];
    }

    /* ======================= مصادر التعرّف (كارد جديد) ======================= */
    public function awarenessBreakdown(string $range = 'month'): array
    {
        [$start, $end] = $this->getRangeBounds($range);

        $qb = $this->builder();
        $qb->select("COALESCE(NULLIF(TRIM(how_known_source), ''), 'أخرى') AS src, COUNT(*) AS c", false)
           ->where('created_at >=', $start->format('Y-m-d H:i:s'))
           ->where('created_at <',  $end->format('Y-m-d H:i:s'))
           ->groupBy('src')
           ->orderBy('c', 'DESC');

        $q = $qb->get();
        $rows = $q ? $q->getResultArray() : [];

        $labels = [];
        $counts = [];
        $total  = 0;

        foreach ($rows as $r) {
            $label    = $r['src'] ?? 'أخرى';
            $count    = (int) ($r['c'] ?? 0);
            $labels[] = $label;
            $counts[] = $count;
            $total   += $count;
        }

        return [
            'labels'          => $labels,
            'counts'          => $counts,
            'total_responses' => $total,
        ];
    }
}

<?php
declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/**
 * FileAccessPolicy
 * - يحسم منطق الوصول (عرض/تنزيل) + حدود التنزيل اليومية/الشهرية
 * - يعتمد حدود الزمن على Asia/Amman محسوبة في PHP لتجنّب اختلافات إعدادات MySQL
 *
 * ملاحظات:
 * - الصوت/الفيديو: عرض/بث يتطلب خطة level >= 2 مالم يكن allow_public_view=1
 * - الملفات المدفوعة غير AV: تتطلب أي اشتراك فعّال
 * - سقف يومي إجمالي حسب الخطة (limits_json -> "عدد التحميلات لكل يوم")
 * - سقف شهري لكل ملف = 3 مرات/شهر ميلادي
 */
final class FileAccessPolicy
{
    /** ثبات المنطقة الزمنية المعتمدة للأحكام التجارية */
    private const APP_TZ = 'Asia/Amman';

    public function __construct(
        private BaseConnection $db,
        private SubscriptionService $subs
    ) {}

    /**
     * فحص السماحية للعرض/التنزيل
     *
     * @param int   $userId     رقم المستخدم (0 للضيف)
     * @param array $file       سجل الملف: id, mime_type, is_premium, allow_public_view
     * @param bool  $isDownload هل الإجراء تنزيل (true) أم عرض/بث (false)
     * @return array{allowed:bool, reason?:string, caps?:array|null}
     */
    public function canAccess(int $userId = 0, array $file = [], bool $isDownload = false): array
    {
        $mime = strtolower((string)($file['mime_type'] ?? ''));
        $isAV = str_starts_with($mime, 'audio/') || str_starts_with($mime, 'video/');
        $allowPublic = $file['allow_public_view'] ?? null;

        // ⏱ الآن + حدود اليوم/الشهر وفق Asia/Amman
        $now = $this->nowInTz();
        [$dayStart, $dayEnd]     = $this->dayBounds($now);
        [$monthStart, $monthEnd] = $this->monthBounds($now);

        // 1) الصوت/الفيديو
        if ($isAV) {
            if ((int)$allowPublic === 1) {
                // عام للعرض للجميع
                return $isDownload
                    ? $this->enforceCapsDownload($userId, (int)$file['id'], $dayStart, $dayEnd, $monthStart, $monthEnd, null)
                    : ['allowed' => true, 'reason' => 'public_av', 'caps' => null];
            }
            // غير عام → يتطلب level >= 2
            $plan = $this->subs->getActivePlanForUser($userId);
            if (!$plan || (int)$plan['level'] < 2) {
                return ['allowed' => false, 'reason' => 'plan_level_lt_2', 'caps' => null];
            }
            // عرض فقط
            if (!$isDownload) {
                return ['allowed' => true, 'reason' => 'ok_stream', 'caps' => null];
            }
            // تنزيل مع سقوف
            return $this->enforceCapsDownload(
                $userId,
                (int)$file['id'],
                $dayStart,
                $dayEnd,
                $monthStart,
                $monthEnd,
                $plan
            );
        }

        // 2) غير AV
        $isPremium = (int)($file['is_premium'] ?? 0) === 1;
        if ($isPremium) {
            // يحتاج أي اشتراك فعّال (basic يكفي)
            $plan = $this->subs->getActivePlanForUser($userId);
            if (!$plan) {
                return ['allowed' => false, 'reason' => 'no_active_sub', 'caps' => null];
            }
            if (!$isDownload) {
                return ['allowed' => true, 'reason' => 'ok_premium_view', 'caps' => null];
            }
            return $this->enforceCapsDownload(
                $userId,
                (int)$file['id'],
                $dayStart,
                $dayEnd,
                $monthStart,
                $monthEnd,
                $plan
            );
        }

        // 3) مجاني تمامًا
        if (!$isDownload) {
            return ['allowed' => true, 'reason' => 'ok_free', 'caps' => null];
        }
        // للملفات المجانية نطبّق فقط الحد الشهري لكل ملف (=3)
        return $this->enforceCapsFreeDownload(
            $userId,
            (int)$file['id'],
            $monthStart,
            $monthEnd
        );
    }

    /**
     * فرض السقوف عند التنزيل لملف يتطلب خطة (أو AV غير عام)
     * - حد يومي إجمالي للحساب (قد يكون غير محدود إذا daily_cap <= 0)
     * - حد شهري لكل ملف = 3
     *
     * @param array|null $plan   مصفوفة خطة المستخدم (قد تكون null للـ AV العام)
     */
    private function enforceCapsDownload(
        int $userId,
        int $fileId,
        DateTimeImmutable $dayStart,
        DateTimeImmutable $dayEnd,
        DateTimeImmutable $monthStart,
        DateTimeImmutable $monthEnd,
        ?array $plan
    ): array {
        if ($userId <= 0) {
            // تنزيل دوماً يتطلب تسجيل الدخول لنربط العدّ بالمستخدم
            return ['allowed' => false, 'reason' => 'login_required', 'caps' => null];
        }

        // حد شهري لكل ملف = 3 (ساري دائماً)
        $monthCount = $this->countUserFileInRange($userId, $fileId, $monthStart, $monthEnd);
        if ($monthCount >= 3) {
            return [
                'allowed' => false,
                'reason'  => 'per_file_monthly_cap_3',
                'caps'    => ['per_file_month' => 3, 'file_month_used' => $monthCount],
            ];
        }

        // حد يومي إجمالي حسب الخطة (إن وُجدت)
        $dailyCap = (int)($plan['daily_download_cap'] ?? 0);
        if ($dailyCap > 0) {
            // وضع api: العدّ اليومي من عدّاد Plans المركزي (fail-open للعدّ المحلي)
            $dayCount = null;
            try {
                $snap = (new \App\Libraries\PlansClient())->usageSnapshot($userId, 'downloads_per_day', null);
                if (is_array($snap)) {
                    $dayCount = (int) ($snap['used'] ?? 0);
                }
            } catch (\Throwable $e) {
                $dayCount = null;
            }
            if ($dayCount === null) {
                $dayCount = $this->countUserAllInRange($userId, $dayStart, $dayEnd);
            }
            if ($dayCount >= $dailyCap) {
                return [
                    'allowed' => false,
                    'reason'  => 'daily_cap_reached',
                    'caps'    => [
                        'daily_cap'   => $dailyCap,
                        'daily_used'  => $dayCount,
                        'remaining'   => 0,
                        'file_month_used' => $monthCount,
                        'file_month_cap'  => 3,
                    ],
                ];
            }
            return [
                'allowed' => true,
                'reason'  => 'ok_caps',
                'caps'    => [
                    'daily_cap'   => $dailyCap,
                    'daily_used'  => $dayCount,
                    'remaining'   => max(0, $dailyCap - $dayCount),
                    'file_month_used' => $monthCount,
                    'file_month_cap'  => 3,
                ],
            ];
        }

        // daily_cap <= 0 → لا حد يومي (لكن يبقى حد 3 لكل ملف/شهر)
        return [
            'allowed' => true,
            'reason'  => 'ok_caps_no_daily_limit',
            'caps'    => [
                'daily_cap'   => 0,
                'daily_used'  => 0,
                'remaining'   => null,
                'file_month_used' => $monthCount,
                'file_month_cap'  => 3,
            ],
        ];
    }

    /**
     * فرض السقف الشهري فقط لملف مجاني (لا حد يومي)
     */
    private function enforceCapsFreeDownload(
        int $userId,
        int $fileId,
        DateTimeImmutable $monthStart,
        DateTimeImmutable $monthEnd
    ): array {
        if ($userId <= 0) {
            return ['allowed' => false, 'reason' => 'login_required', 'caps' => null];
        }

        $monthCount = $this->countUserFileInRange($userId, $fileId, $monthStart, $monthEnd);
        if ($monthCount >= 3) {
            return [
                'allowed' => false,
                'reason'  => 'per_file_monthly_cap_3',
                'caps'    => ['per_file_month' => 3, 'file_month_used' => $monthCount],
            ];
        }

        return [
            'allowed' => true,
            'reason'  => 'ok_free_caps',
            'caps'    => ['file_month_used' => $monthCount, 'file_month_cap' => 3],
        ];
    }

    // ------------------------------
    //       Helpers (time/range)
    // ------------------------------

    /** الآن في Asia/Amman ككائن غير قابل للتغيير */
    private function nowInTz(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(self::APP_TZ));
    }

    /**
     * حدود اليوم المحلي [start, end)
     * start = 00:00:00 محلي، end = start + 1 يوم
     */
    private function dayBounds(DateTimeImmutable $now): array
    {
        $startLocal = $now->setTime(0, 0, 0);
        $endLocal   = $startLocal->add(new DateInterval('P1D'));
        return [$startLocal, $endLocal];
    }

    /**
     * حدود الشهر المحلي [start, end)
     * start = اليوم الأول 00:00:00، end = start + 1 شهر
     */
    private function monthBounds(DateTimeImmutable $now): array
    {
        $startLocal = $now->setDate((int)$now->format('Y'), (int)$now->format('m'), 1)->setTime(0, 0, 0);
        $endLocal   = $startLocal->add(new DateInterval('P1M'));
        return [$startLocal, $endLocal];
    }

    // ------------------------------
    //         Helpers (DB)
    // ------------------------------

    /**
     * عدّ تنزيلات المستخدم في مدى زمني (جميع الملفات)
     */
    private function countUserAllInRange(
        int $userId,
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): int {
        // نحفظ downloads بـ Asia/Amman أيضاً (انظر تعديل FileGateway بالأسفل)
        $sql = "SELECT COUNT(*) AS c
                  FROM file_downloads
                 WHERE user_id = ?
                   AND downloaded_at >= ?
                   AND downloaded_at < ?";

        return (int) $this->db->query($sql, [
            $userId,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
        ])->getRow('c');
    }

    /**
     * عدّ تنزيلات المستخدم لملف محدد في مدى زمني
     */
    private function countUserFileInRange(
        int $userId,
        int $fileId,
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): int {
        $sql = "SELECT COUNT(*) AS c
                  FROM file_downloads
                 WHERE user_id = ?
                   AND file_id = ?
                   AND downloaded_at >= ?
                   AND downloaded_at < ?";

        return (int) $this->db->query($sql, [
            $userId,
            $fileId,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
        ])->getRow('c');
    }
}

<?php
// app/Helpers/PriorityHelper.php

declare(strict_types=1);

namespace App\Helpers;

/**
 * محوّل مستوى الخطة إلى قيمة ENUM للأولوية في service_requests
 * الخريطة حسب طلبك:
 * - level ≥ 3  → urgent
 * - level = 2  → high
 * - level = 1  → medium
 * - لا خطة     → low
 */
final class PriorityHelper
{
    public static function fromLevel(int $level): string
    {
        if ($level >= 3) {
            return 'urgent';
        }
        if ($level === 2) {
            return 'high';
        }
        if ($level === 1) {
            return 'medium';
        }
        return 'low';
    }
}

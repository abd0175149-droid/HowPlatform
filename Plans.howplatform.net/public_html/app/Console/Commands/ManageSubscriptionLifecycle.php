<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

/**
 * دورة حياة الاشتراكات: تفعيل المجدول الذي حان وقته، وانتهاء الفعّال الذي مرّ تاريخه.
 *
 * EntitlementService يفلتر بـ end_at>now() وقت القراءة، فالصلاحيات صحيحة دونه؛
 * لكن حالة الجدول (status) تبقى قديمة بلا هذا الأمر → إحصاءات "المشتركون النشطون"
 * (AdminController/diploma) تَعُدّ منتهين. هذا الأمر يجعل الحالة المخزّنة تطابق الواقع.
 *
 * يُشغَّل عبر المجدول (Schedule في routes/console.php) أو يدويًا:
 *   php artisan subscriptions:lifecycle
 */
class ManageSubscriptionLifecycle extends Command
{
    protected $signature = 'subscriptions:lifecycle';

    protected $description = 'تفعيل الاشتراكات المجدولة وانتهاء الاشتراكات التي مرّ تاريخها';

    public function handle(): int
    {
        $now = now();

        // (1) تفعيل المجدول الذي بدأ ولم ينتهِ.
        $activated = Subscription::where('status', 'scheduled')
            ->where(function ($q) use ($now) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at', '>', $now);
            })
            ->update(['status' => 'active']);

        // (2) انتهاء الفعّال الذي مرّ تاريخه (end_at غير فارغ وفي الماضي).
        $expired = Subscription::where('status', 'active')
            ->whereNotNull('end_at')
            ->where('end_at', '<', $now)
            ->update(['status' => 'expired']);

        $this->info("activated={$activated} expired={$expired}");

        return self::SUCCESS;
    }
}

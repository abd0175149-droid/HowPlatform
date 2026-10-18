<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;
use App\Models\PlanFeature;

/**
 * الخطط الفعلية (مُستخرَجة من diploma.plans + plan_features الحيّة).
 * مفاتيح المزايا نظيفة (تطابق feature_catalog). idempotent بالـ slug.
 */
class RealPlansSeeder extends Seeder
{
    public function run(): void
    {
        // slug, name, level, price, duration_days, grant_points,
        //   [fateen, content, downloads, classes, support]
        $plans = [
            ['basic',      'Basic',     1, 7.99,  30,  500,  [30,  'basic', 10, 3,  'standard']],
            ['pro',        'Pro',       2, 29.99, 120, 1650, [50,  'pro',   25, 5,  'priority']],
            ['elite',      'Elite',     3, 44.99, 260, 3300, [100, 'pro',   50, 10, 'priority']],
            ['free-month', 'FreeMonth', 3, 0.00,  30,  250,  [20,  'pro',   5,  2,  'standard']],
        ];

        foreach ($plans as [$slug, $name, $level, $price, $days, $points, $f]) {
            $plan = Plan::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name, 'level' => $level, 'price' => $price, 'currency' => 'JOD',
                    'duration_days' => $days, 'grant_points' => $points, 'is_active' => 1,
                    'sort_order' => $level,
                ]
            );

            $feats = [
                ['diploma', 'fateen_messages_per_day', 'استخدام فطين — الرسائل اليومية', (string) $f[0]],
                ['diploma', 'content_tier',            'الوصول إلى المحتوى',            (string) $f[1]],
                ['library', 'downloads_per_day',       'عدد التحميلات لكل يوم',          (string) $f[2]],
                ['tools',   'classes_allowed',         'عدد الصفوف المسموح',            (string) $f[3]],
                ['all',     'support_priority',        'أولوية الدعم',                  (string) $f[4]],
            ];
            foreach ($feats as [$platform, $key, $label, $value]) {
                PlanFeature::updateOrCreate(
                    ['plan_id' => $plan->id, 'platform' => $platform, 'feature_key' => $key],
                    ['label' => $label, 'value' => $value]
                );
            }
        }
    }
}

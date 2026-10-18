<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FeatureCatalog;

/**
 * كتالوج المزايا — مُستخرَج من كود المنصّات الفعلي (live plan_features).
 * مفاتيح نظيفة (ASCII) تُطبَّع للمنصّات لاحقًا عبر API الصلاحيات.
 */
class FeatureCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            // platform, key, label, type, unit, options, default, desc, sort
            ['diploma', 'fateen_messages_per_day', 'استخدام فطين — الرسائل اليومية', 'int', 'رسالة/يوم', null, '30', 'حدّ رسائل مساعد فطين يوميًا', 10],
            ['diploma', 'content_tier', 'الوصول إلى المحتوى', 'select', null, ['basic', 'pro', 'elite'], 'basic', 'مستوى المحتوى المتاح', 20],
            ['library', 'downloads_per_day', 'عدد التحميلات لكل يوم', 'int', 'تحميل/يوم', null, '10', 'حدّ التنزيلات اليومية', 30],
            ['tools', 'classes_allowed', 'عدد الصفوف المسموح', 'int', 'صف', null, '3', 'عدد الصفوف الدراسية المسموح إنشاؤها', 40],
            ['all', 'support_priority', 'أولوية الدعم', 'select', null, ['standard', 'priority'], 'standard', 'مستوى أولوية الدعم الفنّي', 50],
        ];

        foreach ($features as [$platform, $key, $label, $type, $unit, $options, $default, $desc, $sort]) {
            FeatureCatalog::updateOrCreate(
                ['platform' => $platform, 'feature_key' => $key],
                [
                    'label'         => $label,
                    'value_type'    => $type,
                    'unit'          => $unit,
                    'options'       => $options,
                    'default_value' => $default,
                    'description'   => $desc,
                    'is_active'     => true,
                    'sort_order'    => $sort,
                ]
            );
        }
    }
}

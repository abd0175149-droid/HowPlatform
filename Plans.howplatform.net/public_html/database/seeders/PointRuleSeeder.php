<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PointRule;

/**
 * كتالوج الاقتصاد — قيم مُجمّدة مطابقة للنظام الحالي (قرار المؤسّس #5: تجميد).
 * الروافع الجديدة (راتب شهري/مضاعِف خصم/انتهاء) غير مُدرَجة هنا = معطّلة.
 */
class PointRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            // الكسب
            ['diploma.referral.completed',          'earn',  100,  'إكمال صديق مُحال لملفّه'],
            ['diploma.streak.day5',                 'earn',  50,   '5 أيام دخول متتالية'],
            ['plans.subscription.granted.30',       'earn',  500,  'منحة تفعيل اشتراك 30 يومًا'],
            ['plans.subscription.granted.90',       'earn',  1650, 'منحة تفعيل اشتراك 90 يومًا'],
            ['plans.subscription.granted.365',      'earn',  3300, 'منحة تفعيل اشتراك 365 يومًا'],

            // الصرف (تكاليف أدوات AI الحالية)
            ['diploma.aitool.spend.default',                       'spend', 30,  'تكلفة أداة AI افتراضية'],
            ['diploma.aitool.spend.formative_reflective_essay_v1', 'spend', 100, 'المقالة التأمّلية البنائية'],
            ['diploma.aitool.spend.rewritten_reflective_essay_v1', 'spend', 150, 'إعادة كتابة المقالة التأمّلية'],
            ['diploma.aitool.spend.inquiry_poster_v1',             'spend', 300, 'بوستر الاستقصاء'],
        ];

        foreach ($rules as [$key, $kind, $points, $desc]) {
            PointRule::updateOrCreate(
                ['event_key' => $key],
                ['kind' => $kind, 'points' => $points, 'is_active' => true, 'description' => $desc]
            );
        }
    }
}

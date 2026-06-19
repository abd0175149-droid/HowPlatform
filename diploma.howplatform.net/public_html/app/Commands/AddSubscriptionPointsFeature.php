<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Command لإضافة ميزة "نقاط الاشتراك" للخطط الموجودة
 * 
 * الاستخدام:
 * php spark add:subscription-points-feature
 */
class AddSubscriptionPointsFeature extends BaseCommand
{
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'Plans';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'add:subscription-points-feature';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'إضافة ميزة "نقاط الاشتراك" للخطط الموجودة حسب مدة الاشتراك';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'add:subscription-points-feature [--force]';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = [];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = [
        '--force' => 'إعادة إضافة الميزة حتى لو كانت موجودة',
    ];

    /**
     * حساب النقاط حسب مدة الاشتراك
     *
     * @param int $durationDays مدة الاشتراك بالأيام
     * @return int عدد النقاط
     */
    private function calculateSubscriptionPoints(int $durationDays): int
    {
        return match($durationDays) {
            30 => 500,      // شهر واحد
            90 => 1650,     // 3 أشهر
            365 => 3300,    // سنة
            default => (int)($durationDays * 16.67) // حساب ديناميكي
        };
    }

    /**
     * تنفيذ الأمر
     *
     * @param array $params
     * @return int
     */
    public function run(array $params)
    {
        CLI::write('بدء إضافة ميزة "نقاط الاشتراك" للخطط...', 'yellow');
        CLI::newLine();

        $db = Database::connect();
        $force = CLI::getOption('force') === true || CLI::getOption('force') === 'true';

        // جلب جميع الخطط الفعّالة
        $plans = $db->table('plans')
            ->select('id, name, default_duration_days')
            ->where('is_active', 1)
            ->get()
            ->getResultArray();

        if (empty($plans)) {
            CLI::write('❌ لا توجد خطط فعّالة في النظام', 'red');
            return EXIT_ERROR;
        }

        CLI::write('تم العثور على ' . count($plans) . ' خطة فعّالة', 'green');
        CLI::newLine();

        $added = 0;
        $skipped = 0;
        $updated = 0;
        $errors = 0;

        foreach ($plans as $plan) {
            $planId = (int)$plan['id'];
            $planName = $plan['name'];
            $durationDays = (int)($plan['default_duration_days'] ?? 30);
            $points = $this->calculateSubscriptionPoints($durationDays);

            // التحقق من وجود الميزة مسبقاً
            $existingFeature = $db->table('plan_features')
                ->where('plan_id', $planId)
                ->where('feature_key', 'نقاط الاشتراك')
                ->get()
                ->getRowArray();

            if ($existingFeature && !$force) {
                CLI::write("⏭️  تم تخطي الخطة: {$planName} (الميزة موجودة بالفعل)", 'yellow');
                $skipped++;
                continue;
            }

            $limitsJson = json_encode(['points' => $points], JSON_UNESCAPED_UNICODE);

            try {
                if ($existingFeature && $force) {
                    // تحديث الميزة الموجودة
                    $db->table('plan_features')
                        ->where('id', (int)$existingFeature['id'])
                        ->update([
                            'limits_json' => $limitsJson,
                        ]);
                    CLI::write("✅ تم تحديث الخطة: {$planName} ({$durationDays} يوم = {$points} نقطة)", 'green');
                    $updated++;
                } else {
                    // إضافة ميزة جديدة
                    $db->table('plan_features')->insert([
                        'plan_id'     => $planId,
                        'feature_key' => 'نقاط الاشتراك',
                        'limits_json' => $limitsJson,
                    ]);
                    CLI::write("✅ تم إضافة الخطة: {$planName} ({$durationDays} يوم = {$points} نقطة)", 'green');
                    $added++;
                }
            } catch (\Throwable $e) {
                CLI::write("❌ خطأ في الخطة: {$planName} - " . $e->getMessage(), 'red');
                $errors++;
            }
        }

        CLI::newLine();
        CLI::write('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'cyan');
        CLI::write('ملخص العملية:', 'yellow');
        CLI::write("  ✅ تمت الإضافة: {$added}", 'green');
        CLI::write("  🔄 تم التحديث: {$updated}", 'cyan');
        CLI::write("  ⏭️  تم التخطي: {$skipped}", 'yellow');
        if ($errors > 0) {
            CLI::write("  ❌ الأخطاء: {$errors}", 'red');
        }
        CLI::write('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'cyan');
        CLI::newLine();

        if ($errors > 0) {
            return EXIT_ERROR;
        }

        CLI::write('✨ تمت العملية بنجاح!', 'green');
        return EXIT_SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * AssessmentPositionSeeder
 * Seeder رئيسي لتشغيل جميع Seeders المطلوبة لأداة الموقف التقييمي
 */
class AssessmentPositionSeeder extends Seeder
{
    public function run()
    {
        echo "🚀 بدء تشغيل Seeders لأداة الموقف التقييمي...\n\n";

        // 1. إضافة نوع الأداة (PromptMaker and Configs)
        echo "1️⃣ تشغيل AiJobTypesSeeder...\n";
        $this->call('AiJobTypesSeeder');
        echo "✅ تم بنجاح\n\n";

        // 2. إضافة مكان عرض الأداة (Button Placement)
        echo "2️⃣ تشغيل AiToolPlacementsSeeder...\n";
        $this->call('AiToolPlacementsSeeder');
        echo "✅ تم بنجاح\n\n";

        echo "🎉 تم تشغيل جميع Seeders لإضافة أداة الموقف التقييمي بنجاح!\n";
    }
}

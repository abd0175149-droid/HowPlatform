<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * ArticleEvaluatorSeeder
 * Seeder رئيسي لتشغيل جميع Seeders المطلوبة لأداة تقييم المقالات
 */
class ArticleEvaluatorSeeder extends Seeder
{
    public function run()
    {
        echo "🚀 بدء تشغيل Seeders لأداة تقييم المقالات...\n\n";

        // 1. إضافة نوع المهمة
        echo "1️⃣ تشغيل TaskTypesSeeder...\n";
        $this->call('TaskTypesSeeder');
        echo "✅ تم بنجاح\n\n";

        // 2. إضافة نوع الأداة
        echo "2️⃣ تشغيل AiJobTypesSeeder...\n";
        $this->call('AiJobTypesSeeder');
        echo "✅ تم بنجاح\n\n";

        // 3. إضافة مكان عرض الأداة
        echo "3️⃣ تشغيل AiToolPlacementsSeeder...\n";
        $this->call('AiToolPlacementsSeeder');
        echo "✅ تم بنجاح\n\n";

        echo "🎉 تم تشغيل جميع Seeders بنجاح!\n";
        echo "📝 لا تنسَ إضافة المتغير التالي في ملف .env:\n";
        echo "ARTICLE_EVALUATOR_ALLOWED_USERS=974,500,501,1021\n";
    }
}


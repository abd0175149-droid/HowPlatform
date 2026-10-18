<?php
declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AiToolPlacementsSeeder extends Seeder
{
    public function run()
    {
        $placements = [
            [
                'type_key' => 'lesson_plan_v1',
                'task_type_id' => 31,
                'university_id' => 2,
                'course_id' => 0, // 0 يعني ينطبق على كل المساقات
                'min_subscription_level' => 1,
                'button_label' => 'توليد خطة الحصة (AI)',
                'button_icon' => 'fa-wand-magic-sparkles',
                'button_order' => 10,
                'is_enabled' => 1,
            ],
            [
                'type_key' => 'forum_reply_v1',
                'task_type_id' => 30,
                'university_id' => 0, // 0 يعني ينطبق على كل الجامعات
                'course_id' => 0, // 0 يعني ينطبق على كل المساقات
                'min_subscription_level' => 0,
                'button_label' => 'مساعد مناقشة المنتدى (AI)',
                'button_icon' => 'fa-comments',
                'button_order' => 20,
                'is_enabled' => 1,
            ],
            [
                'type_key' => 'forum_reply_v1',
                'task_type_id' => 0, // 0 يعني ينطبق على كل أنواع المهام (للاختبار)
                'university_id' => 0,
                'course_id' => 0,
                'min_subscription_level' => 0,
                'button_label' => 'مساعد مناقشة المنتدى (AI)',
                'button_icon' => 'fa-comments',
                'button_order' => 20,
                'is_enabled' => 1,
            ],
            [
                'type_key' => 'reflection_v1',
                'task_type_id' => 33,
                'university_id' => 0, // 0 يعني ينطبق على كل الجامعات
                'course_id' => null, // null يعني ينطبق على كل المساقات
                'min_subscription_level' => 0,
                'button_label' => 'إنشاء تأمل (AI)',
                'button_icon' => 'fa-lightbulb',
                'button_order' => 30,
                'is_enabled' => 1,
            ],
            [
                'type_key' => 'task_matrix_v1',
                'task_type_id' => 36,
                'university_id' => 0,
                'course_id' => 0,
                'min_subscription_level' => 0,
                'button_label' => 'مصفوفة المحتوى (AI)',
                'button_icon' => 'fa-table',
                'button_order' => 40,
                'is_enabled' => 1,
            ],
            [
                'type_key' => 'case_study_v1',
                'task_type_id' => 0, // 0 يعني ينطبق على كل أنواع المهام (يمكن تعديله لاحقاً حسب task_type_id المحدد)
                'university_id' => 0, // 0 يعني ينطبق على كل الجامعات
                'course_id' => 0, // 0 يعني ينطبق على كل المساقات
                'min_subscription_level' => 0,
                'button_label' => 'تحليل دراسة الحالة (AI)',
                'button_icon' => 'fa-file-lines',
                'button_order' => 50,
                'is_enabled' => 1,
            ],
            [
                'type_key' => 'case_integration_v1',
                'task_type_id' => 43, // دمج الحالة
                'university_id' => 0, // 0 يعني ينطبق على كل الجامعات
                'course_id' => 0, // 0 يعني ينطبق على كل المساقات
                'min_subscription_level' => 0,
                'button_label' => 'دمج الحالة (AI)',
                'button_icon' => 'fa-link',
                'button_order' => 60,
                'is_enabled' => 1,
            ],
            [
                'type_key' => 'article_evaluator_v1',
                'task_type_id' => 44, // مقيم المقالات
                'university_id' => 0, // 0 يعني ينطبق على كل الجامعات
                'course_id' => 0, // 0 يعني ينطبق على كل المساقات
                'min_subscription_level' => 0,
                'button_label' => 'مقيم المقالات (AI)',
                'button_icon' => 'fa-clipboard-check',
                'button_order' => 80,
                'is_enabled' => 1,
            ],
            [
                'type_key' => 'assessment_position_v1',
                'task_type_id' => 0, // 0 يعني ينطبق على كل أنواع المهام (يمكن تخصيصه لاحقاً)
                'university_id' => 0,
                'course_id' => 0,
                'min_subscription_level' => 0,
                'button_label' => 'الموقف التقييمي (AI)',
                'button_icon' => 'fa-chalkboard-user',
                'button_order' => 90,
                'is_enabled' => 1,
            ],
            [
                'type_key' => 'inquiry_poster_v1',
                'task_type_id' => 17, // بوستر البحث الاستقصائي
                'university_id' => 0, // كل الجامعات
                'course_id' => 0,     // كل المساقات
                'min_subscription_level' => 0,
                'button_label' => 'إنشاء بوستر البحث (AI)',
                'button_icon' => 'fa-image',
                'button_order' => 100,
                'is_enabled' => 1,
            ]
        ];

        foreach ($placements as $placement) {
            $builder = $this->db->table('ai_tool_placements');
            $builder->where('type_key', $placement['type_key']);
            $builder->where('task_type_id', $placement['task_type_id'] ?? 0);
            $builder->where('university_id', $placement['university_id'] ?? 0);
            
            // التعامل مع course_id بشكل صحيح (قد يكون null)
            $courseId = $placement['course_id'] ?? null;
            if ($courseId === null) {
                $builder->where('course_id IS NULL', null, false);
            } else {
                $builder->where('course_id', $courseId);
            }
            
            $existing = $builder->get()->getRowArray();

            if ($existing) {
                $this->db->table('ai_tool_placements')
                    ->where('id', $existing['id'])
                    ->update($placement);
            } else {
                $this->db->table('ai_tool_placements')->insert($placement);
            }
        }
    }
}



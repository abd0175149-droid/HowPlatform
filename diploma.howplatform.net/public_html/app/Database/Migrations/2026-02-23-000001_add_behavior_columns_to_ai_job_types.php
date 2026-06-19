<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: إضافة أعمدة السلوك لجدول ai_job_types
 *
 * الهدف: نقل منطق "هل تحتاج الأداة تخصص المعلم؟" و "كيف يُضاف task_prompt؟"
 * من الكود الىٰ قاعدة البيانات، ليكون كل شيء مُدار من الإدارة.
 */
class AddBehaviorColumnsToAiJobTypes extends Migration
{
    public function up(): void
    {
        $fields = [
            'needs_task_context' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'null'       => false,
                'comment'    => 'هل تجلب task_title وtask_prompt من جدول tasks تلقائياً؟ (1=نعم, 0=لا)',
                'after'      => 'is_enabled',
            ],
            'needs_specialty' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
                'comment'    => 'هل تحقن تخصص المعلم في الـ payload تلقائياً؟ (1=نعم, 0=لا)',
                'after'      => 'needs_task_context',
            ],
            'task_prompt_mode' => [
                'type'       => 'ENUM',
                'constraint' => ['append', 'prepend', 'replace', 'ignore'],
                'default'    => 'append',
                'null'       => false,
                'comment'    => 'كيفية دمج task_prompt مع system_prompt: append=إضافة بعده, prepend=إضافة قبله, replace=استبدال, ignore=تجاهل',
                'after'      => 'needs_specialty',
            ],
        ];

        $this->forge->addColumn('ai_job_types', $fields);

        // ─── تحديث قيم الأدوات الموجودة بناءً على المنطق الحالي المُرمَّز ───

        // الأدوات التي تحتاج سياق المهمة (task_prompt + task_title)
        $this->db->query("
            UPDATE ai_job_types
            SET needs_task_context = 1
            WHERE type_key IN (
                'forum_reply_v1',
                'forum_reply_v2',
                'reflection_v1',
                'weekly_reflection_v1',
                'instant_feedback_v1',
                'case_study_v1',
                'article_evaluator_v1'
            )
        ");

        // الأدوات التي لا تحتاج سياق المهمة
        $this->db->query("
            UPDATE ai_job_types
            SET needs_task_context = 0
            WHERE type_key IN (
                'lesson_plan_v1',
                'task_matrix_v1',
                'case_integration_v1',
                'matrix_analysis_v1',
                'academic_answer_v1',
                'analytical_article_v1',
                'smart_ingest_v1',
                'smart_analyzer_v1',
                'teacher_feedback_v1'
            )
        ");

        // الأدوات التي تحتاج تخصص المعلم
        $this->db->query("
            UPDATE ai_job_types
            SET needs_specialty = 1
            WHERE type_key IN (
                'reflection_v1',
                'weekly_reflection_v1'
            )
        ");

        // task_prompt_mode: أدوات التأمل والمنتدى تُضيف task_prompt إلى system prompt
        $this->db->query("
            UPDATE ai_job_types
            SET task_prompt_mode = 'append'
            WHERE type_key IN (
                'forum_reply_v1',
                'forum_reply_v2',
                'reflection_v1',
                'weekly_reflection_v1',
                'instant_feedback_v1',
                'case_study_v1',
                'article_evaluator_v1'
            )
        ");
    }

    public function down(): void
    {
        $this->forge->dropColumn('ai_job_types', ['needs_task_context', 'needs_specialty', 'task_prompt_mode']);
    }
}

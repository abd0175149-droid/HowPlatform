<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * إضافة قيمة 'review' لعمود status في جدول ai_jobs
 * مطلوب لخطوة مراجعة التقييم قبل بناء ملفات DOCX
 */
class AddReviewStatusToAiJobs extends Migration
{
    public function up()
    {
        // تعديل عمود status ليدعم قيمة review
        $this->db->query(
            "ALTER TABLE `ai_jobs` MODIFY COLUMN `status` ENUM('queued','processing','done','failed','review') NOT NULL DEFAULT 'queued'"
        );
    }

    public function down()
    {
        // إزالة review من ENUM
        $this->db->query(
            "ALTER TABLE `ai_jobs` MODIFY COLUMN `status` ENUM('queued','processing','done','failed') NOT NULL DEFAULT 'queued'"
        );
    }
}

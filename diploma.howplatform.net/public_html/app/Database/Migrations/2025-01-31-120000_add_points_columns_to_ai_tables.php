<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPointsColumnsToAiTables extends Migration
{
    public function up()
    {
        // إضافة عمود points_cost إلى جدول ai_job_types
        $fields1 = [
            'points_cost' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 30,
                'null'       => false,
                'comment'    => 'تكلفة الأداة بالنقاط (القيمة الافتراضية 30)',
            ],
        ];

        $this->forge->addColumn('ai_job_types', $fields1);

        // إضافة أعمدة points_deducted و points_cost إلى جدول ai_jobs
        $fields2 = [
            'points_deducted' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
                'comment'    => 'هل تم خصم النقاط لهذه المهمة (0=لا, 1=نعم)',
            ],
            'points_cost' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
                'comment'    => 'قيمة النقاط التي تم خصمها (NULL إذا لم يتم خصم نقاط)',
                'after'      => 'points_deducted',
            ],
        ];

        $this->forge->addColumn('ai_jobs', $fields2);

        // تحديث القيم الافتراضية للأدوات الموجودة إلى 30 نقطة
        $this->db->query("UPDATE ai_job_types SET points_cost = 30 WHERE points_cost IS NULL OR points_cost = 0");
    }

    public function down()
    {
        $this->forge->dropColumn('ai_jobs', ['points_cost', 'points_deducted']);
        $this->forge->dropColumn('ai_job_types', 'points_cost');
    }
}


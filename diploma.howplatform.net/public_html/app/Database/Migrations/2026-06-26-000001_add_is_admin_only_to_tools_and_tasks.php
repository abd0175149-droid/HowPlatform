<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * أداة خاصة بالأدمن:
 * - ai_job_types.is_admin_only: تمييز الأداة كخاصّة بالأدمن.
 * - tasks.is_admin_only: المهمة التي أداتها admin-only تُخفى عن الطلاب ولا تُشعرهم.
 * مع تعبئة المهام الموجودة بناءً على أداتها (tasks.ai_tool_version = ai_job_types.type_key).
 */
class AddIsAdminOnlyToToolsAndTasks extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('is_admin_only', 'ai_job_types')) {
            $this->forge->addColumn('ai_job_types', [
                'is_admin_only' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'null'       => false,
                    'default'    => 0,
                    'after'      => 'is_enabled',
                ],
            ]);
        }

        if (! $this->db->fieldExists('is_admin_only', 'tasks')) {
            $this->forge->addColumn('tasks', [
                'is_admin_only' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'null'       => false,
                    'default'    => 0,
                ],
            ]);
        }

        // تعبئة المهام الموجودة: المهمة التي أداتها admin-only تُعلَّم admin-only.
        try {
            $this->db->query(
                "UPDATE `tasks` t
                 JOIN `ai_job_types` j ON j.`type_key` = t.`ai_tool_version`
                 SET t.`is_admin_only` = 1
                 WHERE j.`is_admin_only` = 1"
            );
        } catch (\Throwable $e) {
            log_message('warning', 'backfill tasks.is_admin_only: ' . $e->getMessage());
        }
    }

    public function down()
    {
        foreach (['ai_job_types', 'tasks'] as $tbl) {
            if ($this->db->fieldExists('is_admin_only', $tbl)) {
                $this->forge->dropColumn($tbl, 'is_admin_only');
            }
        }
    }
}

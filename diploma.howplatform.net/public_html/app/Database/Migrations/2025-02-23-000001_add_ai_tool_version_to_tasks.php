<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAiToolVersionToTasks extends Migration
{
    public function up()
    {
        $this->forge->addColumn('tasks', [
            'ai_tool_version' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'default'    => null,
                'after'      => 'task_prompt',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('tasks', 'ai_tool_version');
    }
}

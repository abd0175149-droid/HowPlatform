<?php
declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTaskPromptToTasks extends Migration
{
    public function up()
    {
        $fields = [
            'task_prompt' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'نص السؤال أو السياق للمهام من نوع منتدى - يُستخدم في أدوات الذكاء الاصطناعي',
                'after' => 'description',
            ],
        ];

        $this->forge->addColumn('tasks', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('tasks', 'task_prompt');
    }
}


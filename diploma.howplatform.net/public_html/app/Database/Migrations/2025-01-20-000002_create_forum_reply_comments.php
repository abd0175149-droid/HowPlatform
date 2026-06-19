<?php
declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateForumReplyComments extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'task_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'ai_job_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'معرف طلب AI للرد على التعليق',
            ],
            'comment_text' => [
                'type'    => 'TEXT',
                'comment' => 'نص تعليق الزميل الذي يريد الطالب الرد عليه',
            ],
            'reply_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'default'    => 'agreement',
                'comment'    => 'نوع الرد: agreement, disagreement, question, suggestion, etc.',
            ],
            'reply_instructions' => [
                'type'    => 'TEXT',
                'null'    => true,
                'comment' => 'تعليمات إضافية للرد',
            ],
            'reply_result' => [
                'type'    => 'LONGTEXT',
                'null'    => true,
                'comment' => 'نتيجة الرد من AI (JSON)',
            ],
            'sort_order' => [
                'type'       => 'TINYINT',
                'constraint' => 2,
                'unsigned'   => true,
                'default'    => 1,
                'comment'    => 'ترتيب الرد (1 أو 2)',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'processing', 'done', 'failed'],
                'default'    => 'pending',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['user_id', 'task_id']);
        $this->forge->addKey('ai_job_id');
        $this->forge->createTable('forum_reply_comments');
    }

    public function down()
    {
        $this->forge->dropTable('forum_reply_comments');
    }
}


<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRewardsTables extends Migration
{
    public function up()
    {
        // 1. جدول rewards_users
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'comment'    => 'يرتبط مع diploma_users.user_id',
            ],
            'total_points' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
                'null'       => false,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->createTable('rewards_users', true);

        // 2. جدول rewards_transactions
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'points' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
                'comment'    => 'القيمة الإيجابية أو السلبية',
            ],
            'reward_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
                'comment'    => 'friend_invite, consecutive_days, etc.',
            ],
            'reward_type_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'مرجع للعملية (مثل referral_id)',
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'created_at']);
        $this->forge->addKey(['reward_type', 'reward_type_id']);
        $this->forge->createTable('rewards_transactions', true);

        // 3. جدول rewards_referrals
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'referrer_user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'comment'    => 'صاحب كود الدعوة',
            ],
            'referred_user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'comment'    => 'المستخدم المدعو',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'completed', 'rewarded'],
                'default'    => 'pending',
                'null'       => false,
                'comment'    => 'pending: تمت الدعوة، completed: أكمل البروفايل، rewarded: تم منح النقاط',
            ],
            'referral_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => false,
                'comment'    => 'كود دعوة فريد',
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'completed_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'rewarded_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('referrer_user_id');
        $this->forge->addKey('referred_user_id');
        $this->forge->addKey('referral_code');
        $this->forge->addUniqueKey(['referrer_user_id', 'referred_user_id']);
        $this->forge->createTable('rewards_referrals', true);

        // 4. جدول rewards_consecutive_days
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'current_streak' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
                'null'       => false,
                'comment'    => 'عدد الأيام المتتالية الحالية',
            ],
            'last_login_date' => [
                'type' => 'DATE',
                'null' => true,
                'comment' => 'آخر تاريخ تسجيل دخول',
            ],
            'last_rewarded_streak' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
                'null'       => false,
                'comment'    => 'آخر streak تم مكافأته',
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->createTable('rewards_consecutive_days', true);

        // 5. جدول rewards_daily_logins
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'login_date' => [
                'type' => 'DATE',
                'null' => false,
                'comment' => 'تاريخ تسجيل الدخول',
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'login_date']);
        $this->forge->addUniqueKey(['user_id', 'login_date']);
        $this->forge->createTable('rewards_daily_logins', true);
    }

    public function down()
    {
        $this->forge->dropTable('rewards_daily_logins', true);
        $this->forge->dropTable('rewards_consecutive_days', true);
        $this->forge->dropTable('rewards_referrals', true);
        $this->forge->dropTable('rewards_transactions', true);
        $this->forge->dropTable('rewards_users', true);
    }
}


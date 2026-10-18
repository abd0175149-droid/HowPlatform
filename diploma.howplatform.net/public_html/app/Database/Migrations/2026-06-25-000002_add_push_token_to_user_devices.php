<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** إضافة عمود توكن FCM لأجهزة المستخدم (لإشعارات الدفع في المتصفح). */
class AddPushTokenToUserDevices extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('push_token', 'user_devices')) {
            $this->db->query("ALTER TABLE `user_devices` ADD COLUMN `push_token` VARCHAR(512) NULL DEFAULT NULL AFTER `device_label`");
            $this->db->query("ALTER TABLE `user_devices` ADD INDEX `idx_user_devices_push` (`user_id`,`is_active`,`push_token`(64))");
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('push_token', 'user_devices')) {
            $this->db->query('ALTER TABLE `user_devices` DROP COLUMN `push_token`');
        }
    }
}

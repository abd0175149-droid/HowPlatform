<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * دعم رسائل "فَطين" (AI/النظام) في service_messages:
 *  - sender_user_id يصبح NULLable (FK → ON DELETE SET NULL) كي تُخزَّن رسائل بلا مستخدم بشري.
 *  - عمود sender_role لتمييز نوع المُرسِل ('ai' لرسائل فَطين؛ NULL للبشر يُحدَّد عميلياً).
 */
class ServiceMessagesSupportAiSender extends Migration
{
    public function up()
    {
        $db = $this->db;

        // 1) أسقط الـ FK الحالي على sender_user_id (إن وُجد)
        try {
            $db->query('ALTER TABLE `service_messages` DROP FOREIGN KEY `fk_msg_sender_du`');
        } catch (\Throwable $e) {
            log_message('warning', 'drop fk_msg_sender_du: ' . $e->getMessage());
        }

        // 2) اجعل العمود يقبل NULL
        $db->query('ALTER TABLE `service_messages` MODIFY `sender_user_id` BIGINT(20) NULL');

        // 3) أعد الـ FK بسلوك SET NULL
        try {
            $db->query('ALTER TABLE `service_messages`
                ADD CONSTRAINT `fk_msg_sender_du`
                FOREIGN KEY (`sender_user_id`) REFERENCES `diploma_users` (`user_id`)
                ON DELETE SET NULL ON UPDATE CASCADE');
        } catch (\Throwable $e) {
            log_message('warning', 're-add fk_msg_sender_du: ' . $e->getMessage());
        }

        // 4) عمود sender_role لتمييز رسائل AI
        if (! $db->fieldExists('sender_role', 'service_messages')) {
            $db->query("ALTER TABLE `service_messages`
                ADD COLUMN `sender_role` VARCHAR(16) NULL DEFAULT NULL AFTER `sender_user_id`");
        }
    }

    public function down()
    {
        $db = $this->db;

        if ($db->fieldExists('sender_role', 'service_messages')) {
            $db->query('ALTER TABLE `service_messages` DROP COLUMN `sender_role`');
        }

        // ملاحظة: لا نعيد العمود إلى NOT NULL تلقائياً (قد توجد صفوف NULL لرسائل AI).
    }
}

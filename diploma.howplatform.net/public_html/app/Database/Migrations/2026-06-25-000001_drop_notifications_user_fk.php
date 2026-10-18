<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * إسقاط FK notifications.user_id → users.id.
 * السبب: الطلاب يُصادَقون عبر خدمة بعيدة وليسوا في جدول users المحلي،
 * فأي إشعار لطالب كان يفشل بقيد FK. user_id هنا معرّف على مستوى المنصة (طلاب + آدمن).
 * نُبقي الفهرس idx_notifications_user للأداء.
 */
class DropNotificationsUserFk extends Migration
{
    public function up()
    {
        try {
            $this->db->query('ALTER TABLE `notifications` DROP FOREIGN KEY `fk_notifications_user`');
        } catch (\Throwable $e) {
            log_message('warning', 'drop fk_notifications_user: ' . $e->getMessage());
        }
    }

    public function down()
    {
        // لا نعيد القيد (سيكسر إشعارات الطلاب البعيدين).
    }
}

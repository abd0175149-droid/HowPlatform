<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * جدولة حذف الحسابات:
 * - deletion_scheduled_at: موعد الحذف التلقائي (now + 30d عند إرسال الإشعار). NULL = غير مجدول.
 * - deletion_notified_at:  لحظة إرسال إشعار "سيُحذف خلال 30 يوماً" (لإلغاء الجدولة إن سجّل دخولاً بعدها).
 */
class AddDeletionSchedulingToDiplomaUsers extends Migration
{
    public function up()
    {
        $cols = [];
        if (! $this->db->fieldExists('deletion_scheduled_at', 'diploma_users')) {
            $cols['deletion_scheduled_at'] = ['type' => 'DATETIME', 'null' => true, 'after' => 'last_seen_at'];
        }
        if (! $this->db->fieldExists('deletion_notified_at', 'diploma_users')) {
            $cols['deletion_notified_at'] = ['type' => 'DATETIME', 'null' => true, 'after' => 'deletion_scheduled_at'];
        }
        if (! empty($cols)) {
            $this->forge->addColumn('diploma_users', $cols);
        }
    }

    public function down()
    {
        foreach (['deletion_scheduled_at', 'deletion_notified_at'] as $c) {
            if ($this->db->fieldExists($c, 'diploma_users')) {
                $this->forge->dropColumn('diploma_users', $c);
            }
        }
    }
}

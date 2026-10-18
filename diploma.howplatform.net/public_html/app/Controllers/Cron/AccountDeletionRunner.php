<?php

declare(strict_types=1);

namespace App\Controllers\Cron;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;

/**
 * AccountDeletionRunner
 * يحذف الحسابات المجدولة للحذف (deletion_scheduled_at <= NOW()) ضمن نطاق الدبلوم + بياناته المرتبطة.
 * يُلغي الجدولة تلقائياً لمن سجّل دخولاً/نشاطاً بعد تاريخ الإشعار (عاد للمنصة).
 *
 * HTTP: GET /cron/account-deletion/run?key=SECRET   (CRON_SECRET)
 */
class AccountDeletionRunner extends BaseController
{
    use ResponseTrait;

    /** نفس قائمة الجداول المملوكة للمستخدم في ManageUsers. */
    private const USER_OWNED_TABLES = [
        'user_devices', 'user_active_sessions', 'notifications',
        'rewards_daily_logins', 'rewards_consecutive_days', 'rewards_points_log',
        'rewards_transactions', 'rewards_users', 'ai_jobs',
        'fateen_usage_logs', 'file_downloads',
    ];

    public function run()
    {
        $secret = (string) (getenv('CRON_SECRET') ?: '');
        $key    = (string) ($this->request->getGet('key') ?? '');
        if ($secret === '' || $key !== $secret) {
            return $this->respond(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $db = db_connect();

        // المرشّحون للحذف
        $due = $db->table('diploma_users')
            ->select('user_id, deletion_scheduled_at, deletion_notified_at, last_login_at, last_seen_at')
            ->where('deletion_scheduled_at IS NOT NULL', null, false)
            ->where('deletion_scheduled_at <=', date('Y-m-d H:i:s'))
            ->get()->getResultArray();

        $toDelete = [];
        $cancelled = [];
        foreach ($due as $u) {
            $notifiedTs = strtotime((string) ($u['deletion_notified_at'] ?? '')) ?: 0;
            $loginTs    = strtotime((string) ($u['last_login_at'] ?? '')) ?: 0;
            $seenTs     = strtotime((string) ($u['last_seen_at'] ?? '')) ?: 0;

            // عاد للمنصة بعد الإشعار ⇒ إلغاء الجدولة
            if ($notifiedTs > 0 && (max($loginTs, $seenTs) > $notifiedTs)) {
                $cancelled[] = (int) $u['user_id'];
            } else {
                $toDelete[] = (int) $u['user_id'];
            }
        }

        // إلغاء الجدولة للعائدين
        if (! empty($cancelled)) {
            $db->table('diploma_users')->whereIn('user_id', $cancelled)
                ->update(['deletion_scheduled_at' => null, 'deletion_notified_at' => null]);
        }

        // الحذف الفعلي
        $deleted = 0;
        if (! empty($toDelete)) {
            // إغلاق الحساب مركزيًا في Plans (إلغاء الاشتراكات + تصفير المحفظة) — best-effort
            try {
                $plansClient = new \App\Libraries\PlansClient();
                foreach ($toDelete as $uid) {
                    $plansClient->accountClose($uid);
                }
            } catch (\Throwable $e) {
                log_message('error', 'AccountDeletionRunner: Plans accountClose failed: ' . $e->getMessage());
            }

            foreach (self::USER_OWNED_TABLES as $tbl) {
                try {
                    if ($db->tableExists($tbl) && $db->fieldExists('user_id', $tbl)) {
                        $db->table($tbl)->whereIn('user_id', $toDelete)->delete();
                    }
                } catch (\Throwable $e) {
                    log_message('error', "AccountDeletionRunner: clean {$tbl} failed: " . $e->getMessage());
                }
            }
            $db->table('diploma_users')->whereIn('user_id', $toDelete)->delete();
            $deleted = (int) $db->affectedRows();
            log_message('info', 'AccountDeletionRunner deleted users: ' . implode(',', $toDelete));
        }

        return $this->respond([
            'success'   => true,
            'deleted'   => $deleted,
            'cancelled' => count($cancelled),
            'checked'   => count($due),
        ]);
    }
}

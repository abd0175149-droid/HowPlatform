<?php

declare(strict_types=1);

namespace App\Controllers\Admins;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * مركز إشعارات الأدمن — قراءة من جدول notifications (الجرس في الناف بار).
 * يعتمد على user_id من الجلسة تمامًا كنظام الطالب، لكنه محصور بحارس الأدمن في الراوتر.
 */
class NotificationsController extends BaseController
{
    protected $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    private function uid(): int
    {
        $a = session('auth_user') ?: [];
        return (int) ($a['id'] ?? session('user_id') ?? 0);
    }

    private function unread(int $uid): int
    {
        return (int) $this->db->table('notifications')
            ->where(['user_id' => $uid, 'is_read' => 0])->countAllResults();
    }

    /** GET /admins/notifications/list?limit=15 */
    public function list(): ResponseInterface
    {
        $uid = $this->uid();
        if ($uid <= 0) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false, 'message' => 'unauthorized']);
        }
        $limit  = min(50, max(1, (int) ($this->request->getGet('limit') ?? 15)));
        $offset = max(0, (int) ($this->request->getGet('offset') ?? 0));
        $total  = (int) $this->db->table('notifications')->where('user_id', $uid)->countAllResults();
        $items  = $this->db->table('notifications')
            ->select('id, title, message, is_read, redirect_url, created_at')
            ->where('user_id', $uid)
            ->orderBy('id', 'DESC')->limit($limit, $offset)->get()->getResultArray();

        return $this->response->setJSON([
            'success'  => true,
            'items'    => $items,
            'unread'   => $this->unread($uid),
            'total'    => $total,
            'has_more' => ($offset + $limit) < $total,
        ]);
    }

    /** POST /admins/notifications/delete/{id} */
    public function delete(int $id): ResponseInterface
    {
        $uid = $this->uid();
        if ($uid <= 0) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false]);
        }
        $this->db->table('notifications')->where(['id' => $id, 'user_id' => $uid])->delete();
        return $this->response->setJSON(['success' => true, 'unread' => $this->unread($uid)]);
    }

    /** POST /admins/notifications/clear-all */
    public function clearAll(): ResponseInterface
    {
        $uid = $this->uid();
        if ($uid <= 0) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false]);
        }
        $this->db->table('notifications')->where('user_id', $uid)->delete();
        return $this->response->setJSON(['success' => true, 'unread' => 0]);
    }

    /** GET /admins/notifications/unread-count */
    public function unreadCount(): ResponseInterface
    {
        $uid = $this->uid();
        if ($uid <= 0) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false]);
        }
        return $this->response->setJSON(['success' => true, 'unread' => $this->unread($uid)]);
    }

    /** POST /admins/notifications/mark-read/{id} */
    public function markRead(int $id): ResponseInterface
    {
        $uid = $this->uid();
        if ($uid <= 0) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false]);
        }
        $this->db->table('notifications')->where(['id' => $id, 'user_id' => $uid])->update(['is_read' => 1]);
        return $this->response->setJSON(['success' => true, 'unread' => $this->unread($uid)]);
    }

    /** POST /admins/notifications/mark-all-read */
    public function markAllRead(): ResponseInterface
    {
        $uid = $this->uid();
        if ($uid <= 0) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false]);
        }
        $this->db->table('notifications')->where(['user_id' => $uid, 'is_read' => 0])->update(['is_read' => 1]);
        return $this->response->setJSON(['success' => true, 'unread' => 0]);
    }

    /** POST /admins/notifications/register-device  (FCM web push token) */
    public function registerDevice(): ResponseInterface
    {
        $uid = $this->uid();
        if ($uid <= 0) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false]);
        }
        $token = trim((string) $this->request->getPost('token'));
        if ($token === '') {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'token required']);
        }
        try {
            if (! $this->db->fieldExists('push_token', 'user_devices')) {
                return $this->response->setJSON(['success' => true, 'note' => 'push storage not ready']);
            }
            $fp  = substr(hash('sha256', $token), 0, 64);
            $now = date('Y-m-d H:i:s');
            $ua  = substr((string) ($this->request->getServer('HTTP_USER_AGENT') ?? ''), 0, 255);
            $exists = $this->db->table('user_devices')
                ->where(['user_id' => $uid, 'device_fingerprint' => $fp])->get()->getRowArray();
            if ($exists) {
                $this->db->table('user_devices')->where('id', $exists['id'])
                    ->update(['push_token' => $token, 'is_active' => 1, 'last_used_at' => $now, 'user_agent' => $ua]);
            } else {
                $this->db->table('user_devices')->insert([
                    'user_id' => $uid, 'device_fingerprint' => $fp, 'push_token' => $token,
                    'is_active' => 1, 'last_used_at' => $now, 'user_agent' => $ua, 'created_at' => $now,
                ]);
            }
            return $this->response->setJSON(['success' => true]);
        } catch (\Throwable $e) {
            log_message('error', 'registerDevice(admin) failed: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false]);
        }
    }
}

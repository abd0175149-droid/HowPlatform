<?php
// app/Filters/DeviceSessionFilter.php

namespace App\Filters;

use App\Models\UserDeviceModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class DeviceSessionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        // 🛑 حماية المسارات: إن لم يكن مسجّل الدخول → منع
        if (! $session->has('user_id')) {
            return $this->deny($request, 'الرجاء تسجيل الدخول أولًا.');
        }

        // ===== أساسيات السياق =====
        $userId     = (int) $session->get('user_id');
        $sessionId  = method_exists($session, 'getId') ? $session->getId() : session_id();
        $ip         = $request->getIPAddress();
        $ua         = $request->getUserAgent() ? $request->getUserAgent()->getAgentString() : '';
        $db         = db_connect();

        // ===== تنظيف الجلسات القديمة (كنّاسة) =====
        $aliveSec   = (int) env('security.sessionAliveSeconds', 7200);
        $cutoff     = date('Y-m-d H:i:s', time() - max(300, $aliveSec));
        $db->table('user_active_sessions')
            ->where('last_seen_at <', $cutoff)
            ->delete();

        // ===== جلب جلسات المستخدم النشطة =====
        $rows = $db->table('user_active_sessions')
            ->select('session_id')
            ->where('user_id', $userId)
            ->where('last_seen_at >=', $cutoff)
            ->get()->getResultArray();

        $activeSessionIds = array_column($rows, 'session_id');
        $maxSessions      = (int) env('security.maxConcurrentSessions', 1);

        // هل هذه الجلسة مُسجّلة؟
        $isKnownSession = in_array($sessionId, $activeSessionIds, true);

        if (! $isKnownSession) {
            // جلسة جديدة للمستخدم → تحقّق الحد
            if (count($activeSessionIds) >= $maxSessions) {
                // أسقط الجلسة الحالية ومنع الطلب
                $session->destroy();
                return $this->deny(
                    $request,
                    'حسابك مفتوح على أجهزة أخرى (الحد الأقصى ' . $maxSessions .
                    '). إن لم تكن أنت، تواصَل مع الدعم.'
                );
            }

            // سجّل الجلسة الحالية
            $db->table('user_active_sessions')->insert([
                'user_id'    => $userId,
                'session_id' => $sessionId,
                'ip_address' => $ip,
                'user_agent' => $ua,
                'last_seen_at' => date('Y-m-d H:i:s'),
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
        } else {
            // حدّث آخر نشاط
            $db->table('user_active_sessions')
               ->where(['user_id' => $userId, 'session_id' => $sessionId])
               ->update([
                   'last_seen_at' => date('Y-m-d H:i:s'),
                   'ip_address'   => $ip,
                   'user_agent'   => $ua,
               ]);
        }

        // ===== تحديث جدول الأجهزة (اختياري/تكميلي) =====
        try {
            if (! function_exists('make_device_fingerprint')) {
                helper('device');
            }
            $fingerprint = make_device_fingerprint(service('request'));
            $deviceModel = new UserDeviceModel();

            $deviceRow = $deviceModel->where([
                'user_id'            => $userId,
                'device_fingerprint' => $fingerprint,
            ])->first();

            if ($deviceRow) {
                $deviceModel->update((int) $deviceRow['id'], [
                    'is_active'   => 1,
                    'last_used_at'=> date('Y-m-d H:i:s'),
                    'ip_address'  => $ip,
                    'user_agent'  => $ua,
                ]);
            } else {
                $deviceModel->insert([
                    'user_id'            => $userId,
                    'device_fingerprint' => $fingerprint,
                    'device_label'       => null,
                    'ip_address'         => $ip,
                    'user_agent'         => $ua,
                    'is_active'          => 1,
                    'last_used_at'       => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Throwable $e) {
            log_message('error', 'DeviceSessionFilter device update failed: ' . $e->getMessage());
        }

        return;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }

    private function deny(RequestInterface $request, string $message): ResponseInterface
    {
        $resp = Services::response();

        // 👇 تمييز fetch/partials مقابل التنقل الكامل:
        $accept     = strtolower((string) $request->getHeaderLine('Accept'));
        $xrw        = strtolower((string) $request->getHeaderLine('X-Requested-With'));
        $sfMode     = strtolower((string) $request->getHeaderLine('Sec-Fetch-Mode'));     // navigate | cors | no-cors
        $sfDest     = strtolower((string) $request->getHeaderLine('Sec-Fetch-Dest'));     // document | empty | ...
        $isAjaxLike = $request->isAJAX()
            || strpos($accept, 'json') !== false
            || $xrw === 'xmlhttprequest'
            || in_array($sfMode, ['cors','no-cors'], true)    // fetch() عادة
            || $sfDest !== 'document';                        // ليس تنقّل صفحة كامل

        if ($isAjaxLike) {
            return $resp->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => $message,
            ]);
        }

        return redirect()->to('/auth/login')->with('error', $message);
    }
}

<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class Health extends BaseController
{
    public function session(): ResponseInterface
    {
        $payload = [
            'ok'        => false,
            'driver'    => (string) config('App')->sessionDriver,
            'savePath'  => (string) config('App')->sessionSavePath,
            'sessionId' => null,
            'ci_sessions_hasRow' => false,
            'error'     => null,
        ];

        try {
            // جرّب بدء/تجديد الجلسة وكتابة قيمة
            $sess = session();
            $sess->regenerate(true);
            $sess->set('probe', time());

            $payload['sessionId'] = method_exists($sess, 'getId') ? $sess->getId() : session_id();

            // عدّ صف الجلسة من جدول ci_sessions
            try {
                $db   = db_connect(); // يستخدم مجموعة DB الافتراضية
                $rows = $db->table('ci_sessions')->where('id', $payload['sessionId'])->countAllResults();
                $payload['ci_sessions_hasRow'] = $rows > 0;
                $payload['ok'] = true;
            } catch (\Throwable $e) {
                $payload['error'] = 'DB check failed: ' . $e->getMessage();
            }
        } catch (\Throwable $e) {
            $payload['error'] = 'Session start/regenerate failed: ' . $e->getMessage();
        }

        // نعيد 200 دائمًا مع تفاصيل بدل 500
        return $this->response->setStatusCode(200)->setJSON($payload);
    }
}

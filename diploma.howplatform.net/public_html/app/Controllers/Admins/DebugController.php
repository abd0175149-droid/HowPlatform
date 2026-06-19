<?php

declare(strict_types=1);

namespace App\Controllers\admins;

use App\Controllers\BaseController;

class DebugController extends BaseController
{
    public function sessionDump()
    {
        $session = session();

        // ✅ حماية بسيطة: إن أردتها افتحها/عدّلها حسب نظامك
        if (! $session->has('user_id')) {
            return $this->response->setStatusCode(401)->setJSON([
                'message' => 'Unauthorized',
            ]);
        }

        // ⚠️ هذه تُعيد جميع مفاتيح السشن كما هي — عدّل/استثنِ إن لزم
        $payload = [
            'id'                  => $session->getId(),
            'ip'                  => $this->request->getIPAddress(),
            'user_agent'          => (string) ($this->request->getUserAgent() ?? ''),
            'started_at'          => $session->get('session_start_at'),
            'last_regenerated_at' => $session->get('session_regenerated_at'),
            'data'                => $session->get(), // كل مفاتيح السشن
        ];

        return $this->response->setJSON($payload);
    }
}

<?php
declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthBridge implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        // ✅ قبول جلسة الويب العادية (تم تسجيل الدخول على هذا الساب دومين)
        if ($session && ($session->has('user_id') || $session->has('auth_user'))) {
            return; // مسموح
        }

        // ✅ إن لم توجد جلسة، جرّب فلتر JWT الموجود أصلاً
        if (class_exists(\App\Filters\AuthFromToken::class)) {
            $jwtFilter = new \App\Filters\AuthFromToken();
            return $jwtFilter->before($request, $arguments);
        }

        // ⛔️ لا سيشن ولا JWT
        return service('response')
            ->setStatusCode(401)
            ->setJSON(['status' => 'unauthorized', 'message' => 'Login required.']);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // لا شيء
    }
}

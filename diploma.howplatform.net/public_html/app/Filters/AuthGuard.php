<?php
namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthGuard implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = service('session');

        // مثال بسيط: يعتبر المستخدم مسجلاً إن وُجد user_id في السيشن
        if (! $session->has('user_id')) {

            // لو الطلب AJAX/JSON نعيد 401 JSON
            $accept = strtolower($request->getHeaderLine('Accept') ?? '');
            $wantsJson = str_contains($accept, 'application/json') || $request->isAJAX();

            if ($wantsJson) {
                return service('response')->setStatusCode(401)->setJSON([
                    'ok' => false,
                    'error' => 'UNAUTHENTICATED',
                ]);
            }

            // وإلا نعيد توجيه لصفحة الدخول
            return redirect()->to('/auth/login');
        }

        // مسموح بالمتابعة
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // لا شيء بعد التنفيذ
    }
}

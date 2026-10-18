<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

/**
 * AdminFilter
 * ------------------------------------------------------------------
 * حارس منطقة الإدارة: يضمن المصادقة أولاً (عبر AuthFilter الذي يفكّ
 * التوكن ويملأ الجلسة)، ثم يفرض أن يكون دور المستخدم ضمن أدوار الإدارة.
 *
 * الأدوار (diploma_users.account_type_id):
 *   1 = admin، 2 = teacher، 3 = student، 4 = trial، 5 = POS
 * المسموح لهم بلوحة الإدارة: [1, 2] (admin + teacher).
 *
 * ملاحظة: الفحوص الأدقّ داخل المتحكّمات (مثل isAdminRole()===1) تبقى
 * فعّالة فوق هذا الحارس، فالمعلّم قد يصل المجموعة لكن تحجبه ميزة
 * مخصّصة للأدمن وحده.
 */
class AdminFilter implements FilterInterface
{
    /** الأدوار المسموح لها بدخول منطقة الإدارة. */
    private const ALLOWED_ROLES = [1, 2];

    public function before(RequestInterface $request, $arguments = null)
    {
        // 1) المصادقة وملء الجلسة (مصدر واحد للحقيقة في فكّ التوكن).
        $auth = new AuthFilter();
        $res  = $auth->before($request, $arguments);
        if ($res !== null) {
            // غير مُصادَق → AuthFilter يُعيد توجيهاً لتسجيل الدخول.
            return $res;
        }

        // 2) بوّابة الدور.
        $role = (int) (session('role') ?? 0);
        if (! in_array($role, self::ALLOWED_ROLES, true)) {
            $accept   = strtolower($request->getHeaderLine('Accept'));
            $wantsJson = str_contains($accept, 'application/json') || $request->isAJAX();

            if ($wantsJson) {
                return service('response')->setStatusCode(403)->setJSON([
                    'ok'      => false,
                    'error'   => 'FORBIDDEN',
                    'message' => 'هذه المنطقة مخصّصة للمشرفين فقط.',
                ]);
            }

            return service('response')->setStatusCode(403)
                ->setBody('<h1>403</h1><p>هذه المنطقة مخصّصة للمشرفين فقط.</p>');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // not used
    }
}

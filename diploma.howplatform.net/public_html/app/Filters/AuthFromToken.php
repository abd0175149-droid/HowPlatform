<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\JwtHelper;
use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * ✅ فلتر يحاول "حلّ" هوية المستخدم المحلي من JWT:
 * - إن كان user_id موجودًا في السيشن → يمرّ.
 * - إن لم يكن: يفك شفرة JWT من السيشن/الكوكي → يبحث عن المستخدم محليًا
 *   عبر email (أولوية) أو عبر معرّف خارجي sub (لو كنت تخزّنه عندك كحقل).
 * - عند النجاح: يضبط user_id في السيشن، لا يلمس بقية الحقول.
 * - عند الفشل: لا يرجع 401 تلقائيًا إلا إذا كان المسار يتطلّب مصادقة.
 *   (يمكنك استعمال هذا الفلتر قبل فلاتر/مسارات تتطلب مستخدمًا مسجلاً).
 */
class AuthFromToken implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        // إن كان مضبوطًا مسبقًا، لا تفعل شيئًا
        $userId = (int) ($session->get('user_id') ?? 0);
        if ($userId > 0) {
            return null;
        }

        // فكّ التوكن
        $decoded = JwtHelper::readFromCookieOrSession();
        if (!$decoded) {
            // لا مستخدم محلي بعد، ونفس الفلتر غير مسؤول عن الرفض.
            return null;
        }

        // نُفضّل الربط بالـemail لأنه فريد عندك
        $email = property_exists($decoded, 'email') ? (string) $decoded->email : '';
        $sub   = property_exists($decoded, 'sub')   ? (string) $decoded->sub   : '';

        $userModel = new UserModel();
        $userRow   = null;

        if ($email !== '') {
            $userRow = $userModel->where('email', $email)->first();
        }

        // إذا لم نجد بالبريد وأنت تحفظ مرجع sub خارجي في users (إن وجد لديك عمود مناسب)
        // يمكنك هنا تجربة حقل آخر مثل external_sub أو auth_sub — اتركها مُعطّلة افتراضيًا
        // if (!$userRow && $sub !== '') {
        //     $userRow = $userModel->where('auth_sub', $sub)->first();
        // }

        if (!$userRow) {
            // لم نجد مستخدمًا محليًا بهذا البريد/المعرّف
            // سياسة افتراضية: لا ننشئ تلقائيًا. نتركه بدون user_id.
            // (إن كنت تريد الإنشاء التلقائي لاحقًا، أخبرني لنضيفه كخيار مُتحكّم به من config)
            return null;
        }

        // ✅ ثبّت user_id في السيشن حتى يتعامل معه باقي النظام
        $session->set('user_id', (int) $userRow['id']);

        // (اختياري) خزن سطرًا سريعًا للعرض في Debug
        $session->set('user_email', (string) $userRow['email']);
        $session->set('user_name',  (string) ($userRow['name'] ?? ''));

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // لا شيء
    }
}

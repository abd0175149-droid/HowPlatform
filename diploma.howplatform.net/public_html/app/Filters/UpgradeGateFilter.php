<?php

declare(strict_types=1);

namespace App\Filters;

use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Config\UpgradeFeature;

/**
 * فلتر يتحقق إن كان المستخدم مخوّلاً برؤية/استخدام ميزة الترقية.
 * - لا يعتمد على تعديل قاعدة البيانات.
 * - يعتمد على Allowlist بالبريد/المعرّف من Config\UpgradeFeature.
 */
class UpgradeGateFilter implements FilterInterface
{
    /** @inheritDoc */
    public function before(RequestInterface $request, $arguments = null)
    {
        // ✅ إن كانت الميزة متوقفة كليًا
        $cfg = new UpgradeFeature();
        if (!$cfg->enabled) {
            return Services::response()
                ->setStatusCode(403)
                ->setJSON(['status' => 'forbidden', 'message' => 'Feature disabled']);
        }

        // ✅ التحقق من جلسة الطالب (نفترض أن user_id مخزون في السيشن)
        $session = session();
        $userId  = (int) ($session->get('user_id') ?? 0);
        if ($userId <= 0) {
            // ليس مسجل دخول
            return Services::response()
                ->setStatusCode(401)
                ->setJSON(['status' => 'unauthenticated']);
        }

        // ✅ جلب المستخدم (للايميل)
        $user = (new UserModel())->find($userId);
        if (!$user) {
            return Services::response()
                ->setStatusCode(401)
                ->setJSON(['status' => 'unauthenticated']);
        }

        $byId    = $cfg->checkById && \in_array($userId, $cfg->allowedIds, true);
        $byEmail = false;

        if ($cfg->checkByEmail && !empty($cfg->allowedEmails)) {
            $email = (string) ($user['email'] ?? '');
            $byEmail = $email !== '' && \in_array(mb_strtolower($email), array_map('mb_strtolower', $cfg->allowedEmails), true);
        }

        if (!($byId || $byEmail)) {
            // ❌ غير مخوّل: نعيد 403 JSON لمسارات الـAPI
            // ملاحظة: على الواجهة (JS) سنُخفي الزرّ ونمنع الدخول أيضًا (دفاعان).
            return Services::response()
                ->setStatusCode(403)
                ->setJSON(['status' => 'forbidden', 'message' => 'Feature under development']);
        }

        // مسموح → السماح بالمتابعة
        return null;
    }

    /** @inheritDoc */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // لا شيء
    }
}

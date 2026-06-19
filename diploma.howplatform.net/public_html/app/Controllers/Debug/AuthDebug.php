<?php

declare(strict_types=1);

namespace App\Controllers\Debug;

use App\Controllers\BaseController;
use App\Libraries\JwtHelper;

/**
 * كنترولر فحص السِيشِن (بيئة التطوير فقط)
 * - يعرض أساسيات السِيشِن: user_id/email/name
 * - يعرض إن وُجد توكن وكيف قُرئ (Session/Cookie/Bearer)
 * - يفك جزءًا آمنًا من الـJWT لغايات الاختبار (بدون أي أسرار)
 */
class AuthDebug extends BaseController
{
    /**
     * GET /debug/session
     * ملاحظة: اربطه بفلتر authFromToken قبل النداء ليتم حقن user_id تلقائيًا
     */
    public function session()
    {
        // 🔐 حماية: لا تسمح بعرض هذه المعلومات في الإنتاج
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
            return $this->response->setStatusCode(403)->setJSON([
                'status'  => 'forbidden',
                'message' => 'Debug endpoint is disabled in production.',
            ]);
        }

        $session = session();

        // معلومات أساسية من السِيشِن
        $userId    = (int) ($session->get('user_id') ?? 0);
        $userEmail = (string) ($session->get('user_email') ?? '');
        $userName  = (string) ($session->get('user_name') ?? '');
        $hasToken  = (bool) $session->has('howplatform_token');

        // نحاول قراءة/فك التوكن (من السيشن/الكوكي/الهيدر) لعرض claims غير حساسة
        $decoded = JwtHelper::readFromCookieOrSession();
        $claims  = null;

        if ($decoded) {
            // ⚠️ لا تُرجع كل شيء؛ نُرجع حقولًا شائعة فقط للاختبار
            $claims = [
                'iss'   => property_exists($decoded, 'iss') ? $decoded->iss : null,
                'aud'   => property_exists($decoded, 'aud') ? $decoded->aud : null,
                'sub'   => property_exists($decoded, 'sub') ? $decoded->sub : null,
                'email' => property_exists($decoded, 'email') ? $decoded->email : null,
                'name'  => property_exists($decoded, 'name') ? $decoded->name : null,
                'exp'   => property_exists($decoded, 'exp') ? (int) $decoded->exp : null,
                'iat'   => property_exists($decoded, 'iat') ? (int) $decoded->iat : null,
            ];
        }

        return $this->response->setJSON([
            'status' => 'ok',
            'session' => [
                'has_user_id' => $userId > 0,
                'user_id'     => $userId,
                'user_email'  => $userEmail,
                'user_name'   => $userName,
                'has_token'   => $hasToken,
            ],
            'jwt_preview' => $claims, // null إذا لم يوجد/فشل
        ]);
    }
}

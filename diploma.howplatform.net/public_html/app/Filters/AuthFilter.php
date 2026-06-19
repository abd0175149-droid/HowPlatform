<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use App\Helpers\JwtHelper;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        log_message('debug', '🚀 AuthFilter triggered');

        $token = $_COOKIE['howplatform_token'] ?? null;
        log_message('debug', 'Token: ' . $token);

        if (!$token) {
            return redirect()->to('https://auth.howplatform.net/login?redirect=' . current_url());
        }

        $decoded = JwtHelper::decodeToken($token);
        log_message('debug', 'Decoded: ' . print_r($decoded, true));

        if (!$decoded) {
            return redirect()->to('https://auth.howplatform.net/login?redirect=' . current_url());
        }

        // ✅ تحديد الرول من قاعدة البيانات
        $db   = \Config\Database::connect();
        $role = 3; // افتراضي طالب
        $row  = $db->table('diploma_users')
                   ->select('account_type_id')
                   ->where('user_id', $decoded->sub)
                   ->get()
                   ->getRowArray();

        if ($row && isset($row['account_type_id'])) {
            $role = (int) $row['account_type_id'];
        }

        // ✅ نخزن بيانات المستخدم في السيشن
        session()->set('auth_user', [
            'id'     => $decoded->sub    ?? null,
            'email'  => $decoded->email  ?? null,
            'name'   => $decoded->name   ?? null,
            'role'   => $role,
            'avatar' => $decoded->avatar ?? null, 
            'is_sales' => $decoded->is_sales ?? 0,
            'gid'    => $decoded->gid    ?? null,
        ]);

        // ✅ نسخة مختصرة للتوافق مع الكود القديم
        session()->set('user_id', $decoded->sub ?? null);
        session()->set('user_name', $decoded->name ?? null);
        session()->set('role', $role);
        session()->set('profile_image_url', $decoded->avatar ?? null);

        return;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // not used
    }
}

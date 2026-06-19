<?php

namespace App\Controllers\Students;

use App\Controllers\BaseController;

class Auth extends BaseController
{
    public function logout()
    {
        // 🟢 مسح السيشن المحلية
        session()->remove('auth_user');
        session()->destroy();

        // 🟢 تحويل للمشروع الرئيسي
        return redirect()->to('https://www.howplatform.net/logout?redirect=' . base_url('/'));
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers\Student;

use App\Controllers\BaseController;

class UpgradeController extends BaseController
{
    public function index()
    {
        // ✅ تأكيد تسجيل الدخول (عدّل وفق منظومة التوثيق لديك إن لزم)
        if (! session()->get('user.id')) {
            return redirect()->to('/login');
        }

        // ✅ إرجاع الواجهة من المسار الفعلي: app/Views/student/pages/upgrade.php
        return view('student/pages/upgrade');
    }
}

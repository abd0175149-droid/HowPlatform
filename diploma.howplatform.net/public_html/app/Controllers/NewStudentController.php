<?php

namespace App\Controllers;

class NewStudentController extends BaseController
{
    public function index()
    {
        // سيشن المستخدم
        $authUser = session('auth_user');

        return view('students/pages/welcome', [
            'user' => $authUser
        ]);
    }
}

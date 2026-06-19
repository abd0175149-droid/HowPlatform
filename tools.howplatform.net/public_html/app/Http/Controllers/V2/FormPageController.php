<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FormPageController extends Controller
{
    public function page(Request $request)
    {
        // تأكد من تسجيل الدخول (نفس منطقك السابق لو تحب)
        $uid = session('auth_user')['id'] ?? (auth()->id() ?? null);
        if (!$uid) {
            $redirect = url()->current();
            return redirect()->route('login', ['redirect' => $redirect])
                ->with('status', 'الرجاء تسجيل الدخول أولاً.');
        }

        return view('tools.assignment-form-v2'); // نضيفه تحت resources/views/tools
    }
}

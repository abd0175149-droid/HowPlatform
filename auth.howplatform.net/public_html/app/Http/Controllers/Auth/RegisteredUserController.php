<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Helpers\RewardsHelper;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * عرض صفحة التسجيل.
     */
    public function create(Request $request): View
    {
        // التقاط redirect إن وُجد ليُستخدم بعد التحقق
        if ($request->has('redirect')) {
            session(['redirect_after_login' => $request->input('redirect')]);
        }

        // التقاط referral_code من query parameter
        if ($request->has('ref')) {
            $refCode = trim($request->input('ref'));
            if ($refCode !== '') {
                session(['referral_code' => $refCode]);
            }
        }

        return view('auth.register');
    }

    /**
     * تنفيذ طلب التسجيل.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name'     => $request->string('name'),
            'email'    => $request->string('email'),
            'password' => Hash::make($request->string('password')),
        ]);

        // معالجة referral_code إن وُجد
        $referralCode = $request->input('referral_code') ?: session('referral_code');
        if ($referralCode) {
            $referralCode = trim($referralCode);
            if ($referralCode !== '') {
                // التحقق من صحة الكود
                $referrerUserId = RewardsHelper::validateReferralCode($referralCode);
                if ($referrerUserId) {
                    // تسجيل الدعوة
                    RewardsHelper::registerReferral($referrerUserId, $user->id, $referralCode);
                }
                // حذف الكود من Session بعد الاستخدام
                session()->forget('referral_code');
            }
        }

        // يطلق إرسال رسالة التفعيل
        event(new Registered($user));

        // دخول للجلسة فقط (بدون SSO)
        Auth::login($user);

        // دائمًا اذهب لصفحة التفعيل
        return redirect()->route('verification.notice');
    }
}

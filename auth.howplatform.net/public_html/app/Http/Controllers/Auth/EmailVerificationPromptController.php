<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    /**
     * عرض صفحة المطالبة بتفعيل البريد، أو إعادة التوجيه إن كان مُفعّلاً.
     */
    public function __invoke(Request $request): RedirectResponse|View
    {
        if ($request->user()->hasVerifiedEmail()) {
            if ($redirect = session('redirect_after_login')) {
                session()->forget('redirect_after_login');
                return redirect()->away($redirect);
            }
            return redirect()->away('https://howplatform.net');
        }

        return view('auth.verify-email');
    }
}

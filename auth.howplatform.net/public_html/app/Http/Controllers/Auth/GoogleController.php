<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Helpers\JwtHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class GoogleController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request)
    {
        try {
            /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
            $driver = Socialite::driver('google');
            /** @var \Laravel\Socialite\Two\User $googleUser */
            $googleUser = $driver->stateless()->user();

            // ابحث/أنشئ المستخدم
            $user = User::where('email', $googleUser->getEmail())->first();

            if (!$user) {
                $user = User::create([
                    'name'      => $googleUser->getName(),
                    'email'     => $googleUser->getEmail(),
                    'password'  => bcrypt(Str::random(16)),
                    'google_id' => $googleUser->getId(),
                    'avatar'    => $googleUser->getAvatar(), // رابط كامل من Google
                ]);
            } else {
                // لا نستبدل صورة المستخدم إذا كانت صورة مرفوعة على منصتنا
                $updates = [
                    'google_id' => $user->google_id ?: $googleUser->getId(),
                ];

                $googleAvatar = $googleUser->getAvatar();
                if (!$this->isPlatformAvatar($user->avatar ?? null) && !empty($googleAvatar)) {
                    $updates['avatar'] = $googleAvatar;
                }

                $user->update($updates);
            }

            // تفعيل البريد إذا أبلغت Google أنه مُتحقق
            $verifiedFlag = data_get($googleUser->user, 'verified_email');
            if ($verifiedFlag === null) {
                $verifiedFlag = data_get($googleUser->user, 'email_verified');
            }
            if ($verifiedFlag) {
                if (is_null($user->email_verified_at)) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }
                $extra = [];
                if (Schema::hasColumn('users', 'is_verified')) {
                    $extra['is_verified'] = 1;
                }
                if (Schema::hasColumn('users', 'profile_verified_at')) {
                    $extra['profile_verified_at'] = now();
                }
                if ($extra) {
                    DB::table('users')->where('id', $user->id)->update($extra);
                }
            }

            // تسجيل دخول Laravel + تجديد الجلسة
            Auth::login($user);
            $request->session()->regenerate();

            // المنطقة الزمنية +03:00 (الأردن)
            $now = Carbon::now('Asia/Amman');
            try { DB::statement("SET time_zone = '+03:00'"); } catch (\Throwable $e) {}

            // توليد JTI وتحديث بيانات الدخول على auth DB
            $jti = Str::uuid()->toString();
            DB::table('users')->where('id', $user->id)->update([
                'current_jti'           => $jti,
                'current_jti_issued_at' => $now,
                'last_login_at'         => $now,
                'last_login_ip'         => $request->ip(),
                'last_login_ua'         => substr((string)$request->userAgent(), 0, 255),
                'updated_at'            => $now,
            ]);

            // تسجيل حدث الدخول (اختياري)
            try {
                DB::table('user_login_events')->insert([
                    'user_id'    => $user->id,
                    'jti'        => $jti,
                    'login_at'   => $now,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            } catch (\Throwable $e) {}

            // JWT + SSO Cookie (التوكن يحمل jti المطابق لـ users.current_jti لتفعيل سياسة الجلسة الواحدة)
            $jwt = class_exists(JwtHelper::class) ? JwtHelper::generateToken($user, ['jti' => $jti]) : null;
            if ($jwt) {
                $this->setSsoCookie($jwt, time() + (int) env('JWT_TTL_SECONDS', 2592000));
            }

            // تحديث سنابشوت الجلسة (إن وُجد الكلاس)
            if (class_exists(\App\Support\SessionProfile::class)) {
                \App\Support\SessionProfile::refresh($user->id);
            }

            // تحديد الوجهة (session ثم query) + تأمينها
            $rawRedirect = session('redirect_after_login') ?: $request->input('redirect');
            if ($rawRedirect) {
                session()->forget('redirect_after_login');
            }
            $finalUrl = $rawRedirect ? $this->safeRedirectUrl($rawRedirect) : 'https://auth.howplatform.net/profile';

            // لو الوجهة الدبلوم → حدّث last_login_at في قاعدة الدبلوم بتوقيت الأردن
            try {
                $host = parse_url($finalUrl, PHP_URL_HOST) ?: '';
                if ($host === 'diploma.howplatform.net') {
                    try { DB::connection('diploma')->statement("SET time_zone = '+03:00'"); } catch (\Throwable $e) {}
                    DB::connection('diploma')
                        ->table('diploma_users')
                        ->updateOrInsert(
                            ['user_id' => $user->id],
                            ['last_login_at' => $now, 'updated_at' => $now]
                        );
                }
            } catch (\Throwable $e) {}

            return redirect()->away($finalUrl);

        } catch (\Exception $e) {
            \Log::error('Google login failed', ['error' => $e->getMessage()]);
            return redirect()->route('login')->withErrors('فشل تسجيل الدخول باستخدام Google.');
        }
    }

    /* ================= Helpers ================= */

    private function setSsoCookie(string $value, int $expires): void
    {
        setcookie('howplatform_token', $value, [
            'expires'  => $expires,
            'path'     => '/',
            'domain'   => '.howplatform.net',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'None',
        ]);
    }

    private function safeRedirectUrl(string $url): string
    {
        $parsed = @parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            return 'https://howplatform.net';
        }
        $host = $parsed['host'];
        if ($host === 'howplatform.net' || substr($host, -strlen('.howplatform.net')) === '.howplatform.net') {
            return $url;
        }
        return 'https://howplatform.net';
    }

    /**
     * هل رابط الصورة يعود لصورة مرفوعة على منصتنا؟
     * يدعم الروابط النسبية والمطلقة على howplatform.net وأي سب-دومين.
     */
    private function isPlatformAvatar(?string $url): bool
    {
        if (!$url) return false;
        $u = trim($url);

        // روابط نسبية شائعة
        if (Str::startsWith($u, ['/media/avatars/', '/storage/avatars/', 'avatars/', '/uploads/profile/'])) {
            return true;
        }

        // روابط مطلقة على howplatform.net
        $host = parse_url($u, PHP_URL_HOST) ?? '';
        $path = parse_url($u, PHP_URL_PATH) ?? '';
        if ($host !== '' && preg_match('/(\.|^)howplatform\.net$/i', $host)) {
            return Str::contains($path, ['/media/avatars/', '/storage/avatars/', '/uploads/profile/', '/avatars/']);
        }

        return false;
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Firebase\JWT\JWT;
use App\Helpers\JwtHelper;
use Carbon\Carbon;
class AuthenticatedSessionController extends Controller
{
    /**
     * مدة صلاحية الـ JWT نفسها (ثابتة/طويلة نسبياً).
     * ملاحظة: الخروج بعد ساعة خمول يُضبط عبر الكوكي + الميدلوير،
     * لذلك نجعل الـ JWT أطول (24h افتراضياً) حتى لا يخرج المستخدم النشط بعد ساعة.
     */
    private int $jwtTtlSeconds;

    public function __construct()
    {
        // قابل للتعديل من env (إن لم تُضبط، القيمة 24 ساعة)
        $this->jwtTtlSeconds = (int) env('JWT_TTL_SECONDS', 24 * 60 * 60);
    }

    public function create(Request $request): View
    {
        if ($request->has('redirect')) {
            session(['redirect_after_login' => $request->input('redirect')]);
        }
        return view('auth.login');
    }

public function store(LoginRequest $request): RedirectResponse
{
    // 1) التحقق وتثبيت الجلسة
    $request->authenticate();
    $request->session()->regenerate();

    $user = $request->user();

    // 2) إن كان البريد غير مُفعّل
    if (method_exists($user, 'hasVerifiedEmail') && !$user->hasVerifiedEmail()) {
        return redirect()->route('verification.notice');
    }

    // ✅ اضبط المنطقة الزمنية +03:00 (الأردن)
    $now = Carbon::now('Asia/Amman');

    // (اختياري لكن مُستحب) اضبط جلسة MySQL على +03:00 لتوحيد سلوك TIMESTAMP
    try {
        DB::statement("SET time_zone = '+03:00'");
    } catch (\Throwable $e) {}
    
    // 3) تحديث user + تسجيل حدث الدخول (على auth DB)
    $jti = Str::uuid()->toString();

    DB::table('users')->where('id', $user->id)->update([
        'current_jti'           => $jti,
        'current_jti_issued_at' => $now,
        'last_login_at'         => $now,
        'last_login_ip'         => $request->ip(),
        'last_login_ua'         => substr((string)$request->userAgent(), 0, 255),
        'updated_at'            => $now,
    ]);

    try {
        DB::table('user_login_events')->insert([
            'user_id'    => $user->id,
            'jti'        => $jti,
            'login_at'   => $now,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    } catch (\Throwable $e) {}

    // 4) JWT + كوكي SSO (التوكن يحمل jti المطابق لـ users.current_jti لتفعيل سياسة الجلسة الواحدة)
    $jwt = JwtHelper::generateToken($user, ['jti' => $jti]);
    $this->setSsoCookie($jwt, time() + (int) env('JWT_TTL_SECONDS', 2592000));

    // 5) تحديد وجهة الإرجاع
    $rawRedirect = session('redirect_after_login') ?: $request->input('redirect');
    if ($rawRedirect) {
        session()->forget('redirect_after_login');
    }
    $finalUrl = $rawRedirect ? $this->safeRedirectUrl($rawRedirect) : 'https://auth.howplatform.net/profile';

    // 6) لو الوجهة الدبلوم → حدّث في قاعدة الدبلوم بتوقيت الأردن
    try {
        $host = parse_url($finalUrl, PHP_URL_HOST) ?: '';
        if ($host === 'diploma.howplatform.net') {
            // اضبط جلسة DB الدبلوم على +03:00 (اختياري)
            try {
                DB::connection('diploma')->statement("SET time_zone = '+03:00'");
            } catch (\Throwable $e) {}

            DB::connection('diploma')
                ->table('diploma_users')
                ->updateOrInsert(
                    ['user_id' => $user->id],
                    ['last_login_at' => $now, 'updated_at' => $now]
                );
        }
    } catch (\Throwable $e) {}

    return redirect()->away($finalUrl);
}


    public function destroy(Request $request): RedirectResponse
    {
        // احتفظ بالـ JTI قبل تصفيره لتحديث سجل الحدث بدقة
        $user = $request->user();
        $prevJti = null;

        if ($user) {
            $prevJti = DB::table('users')->where('id', $user->id)->value('current_jti');
        }

        $now  = now();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user) {
            DB::table('users')->where('id', $user->id)->update([
                'current_jti'           => null,
                'current_jti_issued_at' => null,
                'last_logout_at'        => $now,
                'updated_at'            => $now,
            ]);

            // حدّث حدث الدخول لنفس الـ JTI (أدق من آخر صف)
            if ($prevJti) {
                try {
                    DB::table('user_login_events')
                        ->where('user_id', $user->id)
                        ->where('jti', $prevJti)
                        ->whereNull('logout_at')
                        ->update(['logout_at' => $now, 'reason' => 'logout']);
                } catch (\Throwable $e) {
                    // اختياري
                }
            }
        }

        // احذف الكوكي بنفس خصائص الإنشاء تماماً
        $this->deleteSsoCookie();

        if ($request->has('redirect')) {
            return redirect()->away($this->safeRedirectUrl($request->input('redirect')));
        }
        if ($redirect = session('redirect_after_login')) {
            session()->forget('redirect_after_login');
            return redirect()->away($this->safeRedirectUrl($redirect));
        }
        return redirect()->away('https://howplatform.net');
    }

    protected function authenticated(Request $request, $user)
    {
        if ($request->has('redirect')) {
            return redirect()->away($this->safeRedirectUrl($request->input('redirect')));
        }
        if ($redirect = session('redirect_after_login')) {
            session()->forget('redirect_after_login');
            return redirect()->away($this->safeRedirectUrl($redirect));
        }
        return redirect()->away('https://howplatform.net');
    }

    /* ============== Helpers ============== */
private function jwtSecret(): string
{
    // جرّب من config أولاً (إن كان عندك config/jwt.php)
    $secret = config('jwt.secret');

    // إن كانت غير متوفرة، خذها مباشرة من بيئة السيرفر (أقوى من env() وقت التشغيل مع config:cache)
    if (!is_string($secret) || $secret === '') {
        $secret = $_ENV['JWT_SECRET'] ?? $_SERVER['JWT_SECRET'] ?? getenv('JWT_SECRET') ?: '';
    }

    if ($secret === '') {
        // نتعمّد رمي خطأ واضح بدل توقيع توكن بمفتاح خطأ
        throw new \RuntimeException('Missing JWT_SECRET for password login');
    }

    return $secret;
}

private function makeJwt($user, string $jti, int $ttlSeconds): string
{
    $iat = time();
    $exp = $iat + $ttlSeconds;

    $payload = [
        'iss'   => 'auth.howplatform.net',
        'aud'   => 'howplatform.net',
        'iat'   => $iat,
        'nbf'   => $iat,
        'exp'   => $exp,
        'sub'   => (int)$user->id,
        'jti'   => $jti,
        'name'  => (string)($user->name ?? ''),
        'email' => (string)($user->email ?? ''),
    ];

    // أهم سطر: استخدام سر مضمون
    $secret = $this->jwtSecret();

    return JWT::encode($payload, $secret, 'HS256');
}


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

    private function deleteSsoCookie(): void
    {
        setcookie('howplatform_token', '', [
            'expires'  => time() - 3600,
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
}

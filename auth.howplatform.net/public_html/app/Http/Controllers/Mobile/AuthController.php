<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Mobile\MobileUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use App\Notifications\EmailVerificationCodeNotification;
use App\Notifications\PasswordResetCodeNotification;

class AuthController extends Controller
{
    /** إعدادات عامة */
    protected int $verifyTtlSec       = 1800; // 30 دقيقة لصلاحية كود التحقق
    protected int $resetTtlSec        = 1800; // 30 دقيقة لصلاحية كود إعادة التعيين
    protected int $resendCooldownSec  = 60;   // تبريد إعادة الإرسال بالثواني
    protected int $maxAttempts        = 6;    // أقصى محاولات إدخال الكود

    /** رد JSON UTF-8 */
    private function j($data, int $code = 200)
    {
        return response()->json($data, $code, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** هل الرابط مطلق؟ */
    private function isAbsoluteUrl(?string $url): bool
    {
        if (!$url) return false;
        return (bool) preg_match('~^https?://|^//~i', $url);
    }

    /** تهيئة بيانات المستخدم لتطبيق الموبايل */
    private function userPayload(MobileUser $u): array
    {
        $avatar = $u->avatar ?: null;
        if ($avatar && !$this->isAbsoluteUrl($avatar)) {
            $avatar = url($avatar);
        }

        return [
            'id'     => $u->id,
            'name'   => $u->name,
            'email'  => $u->email,
            'avatar' => $avatar,
        ];
    }

    private function maskEmail(string $email): string
    {
        [$user, $domain] = explode('@', $email);
        $first = mb_substr($user, 0, 1);
        return $first . str_repeat('*', max(mb_strlen($user) - 1, 0)) . '@' . $domain;
    }

    private function makeCode(): string   { return (string) random_int(100000, 999999); }
    private function now(): Carbon        { return Carbon::now(); }

    // ----------------------------------------------------------------------------
    // Login (email/password)
    // ----------------------------------------------------------------------------
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /** @var MobileUser|null $user */
        $user = MobileUser::where('email', mb_strtolower($data['email']))->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return $this->j([
                'message' => 'The given data was invalid.',
                'errors'  => ['email' => ['بيانات الدخول غير صحيحة.']],
            ], 422);
        }

        $user->forceFill(['last_login_at' => $this->now()])->save();

        $token = $user->createToken('mobile')->plainTextToken;

        return $this->j([
            'token' => $token,
            'user'  => $this->userPayload($user),
        ]);
    }

    // ----------------------------------------------------------------------------
    // Google Sign-in
    // ----------------------------------------------------------------------------
    public function loginGoogle(Request $request)
    {
        $data = $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        $allowedClientIds = (array) config('services.google.client_ids', []);
        if (empty($allowedClientIds)) {
            $fallback = array_filter([
                config('services.google.client_id'),
                config('services.google.client_id_mobile'),
                config('services.google.client_id_ios'),
            ]);
            if (!empty($fallback)) $allowedClientIds = $fallback;
        }

        $client = new GoogleClient();
        try { $payload = $client->verifyIdToken($data['id_token']); }
        catch (\Throwable $e) { $payload = null; }

        if (!$payload || empty($payload['email'])) {
            return $this->j(['error'=>'invalid_google_token','message' => 'رمز Google غير صالح.'], 401);
        }

        $audience = $payload['aud'] ?? null;
        if (!$audience || !in_array($audience, $allowedClientIds, true)) {
            return $this->j(['error'=>'invalid_google_token','message' => 'Google client ID غير مصرح به.'], 401);
        }

        $email     = mb_strtolower($payload['email']);
        $name      = $payload['name'] ?? strtok($email, '@');
        $googleId  = $payload['sub'] ?? null;
        $avatar    = $payload['picture'] ?? null;
        $verified  = (bool) ($payload['email_verified'] ?? true);

        /** @var MobileUser|null $user */
        $user = MobileUser::where('email', $email)->first();

        if (!$user) {
            $user = MobileUser::create([
                'name'              => $name,
                'email'             => $email,
                'password'          => Hash::make(Str::random(32)),
                'google_id'         => $googleId,
                'avatar'            => $avatar,
                'email_verified_at' => $verified ? $this->now() : null,
                'last_login_at'     => $this->now(),
            ]);
        } else {
            $updates = ['last_login_at' => $this->now()];
            if (!$user->google_id && $googleId) $updates['google_id'] = $googleId;
            if ($avatar && $avatar !== $user->avatar) $updates['avatar'] = $avatar;
            if ($verified && !$user->email_verified_at) $updates['email_verified_at'] = $this->now();
            if ($updates) $user->fill($updates)->save();
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return $this->j([
            'token' => $token,
            'user'  => $this->userPayload($user),
        ]);
    }

    public function googleSignin(Request $request)
    {
        return $this->loginGoogle($request);
    }

    // ----------------------------------------------------------------------------
    // Register  ➜ يرسل كود تحقق (بدون روابط)
    // ----------------------------------------------------------------------------
    public function register(Request $request)
    {
        $table = (new MobileUser())->getTable();

        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:160'],
            'email'                 => ['required', 'email', 'max:255', "unique:{$table},email"],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'device_id'             => ['nullable','string','max:190'],
            'push_token'            => ['nullable','string','max:400'],
        ]);

        $email = mb_strtolower($data['email']);

        $user = MobileUser::create([
            'name'          => $data['name'],
            'email'         => $email,
            'password'      => Hash::make($data['password']),
            'last_login_at' => $this->now(),
        ]);

        // أنشئ أو حدّث كود التحقق
        $code = $this->makeCode();
        $now = $this->now();
        DB::table('email_verifications')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'email'           => $email,
                'code'            => $code,
                'attempts'        => 0,
                'expires_at'      => $now->copy()->addSeconds($this->verifyTtlSec),
                'resend_after_at' => $now->copy()->addSeconds($this->resendCooldownSec),
                'created_at'      => $now,
                'updated_at'      => $now,
            ]
        );

        // أرسل الكود بالبريد
        try {
            $user->notify(new EmailVerificationCodeNotification($code, $this->verifyTtlSec));
        } catch (\Throwable $e) {
            // نتجاهل فشل الإرسال حفاظاً على الخصوصية
        }

        return $this->j([
            'status'        => 'pending_verification',
            'user_id'       => $user->id,
            'email_masked'  => $this->maskEmail($email),
            'verify' => [
                'expires_in_sec'   => $this->verifyTtlSec,
                'resend_after_sec' => $this->resendCooldownSec,
            ],
        ], 201);
    }

    // ----------------------------------------------------------------------------
    // POST /verify/send  ➜ إعادة إرسال كود التحقق
    // ----------------------------------------------------------------------------
    public function verifySend(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
        ]);

        $email = mb_strtolower(trim($data['email']));
        /** @var MobileUser|null $user */
        $user = MobileUser::where('email', $email)->first();

        // لأغراض الخصوصية نعيد ok دوماً
        if (!$user) {
            return $this->j(['ok' => true, 'resend_after_sec' => $this->resendCooldownSec], 200);
        }
        if ($user->email_verified_at) {
            return $this->j(['ok' => true, 'resend_after_sec' => 0], 200);
        }

        // تبريد الإرسال
        $coolKey = "verify:send:{$user->id}";
        if (Cache::has($coolKey)) {
            $ttl = (int) Cache::get($coolKey) - time();
            return $this->j([
                'error'            => 'too_many_requests',
                'message'          => 'الرجاء الانتظار قبل إعادة الإرسال.',
                'resend_after_sec' => max(0, $ttl),
            ], 429);
        }

        // أنشئ كودًا جديدًا
        $now = $this->now();
        $code = $this->makeCode();
        DB::table('email_verifications')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'email'           => $email,
                'code'            => $code,
                'attempts'        => 0,
                'expires_at'      => $now->copy()->addSeconds($this->verifyTtlSec),
                'resend_after_at' => $now->copy()->addSeconds($this->resendCooldownSec),
                'updated_at'      => $now,
            ]
        );

        try {
            $user->notify(new EmailVerificationCodeNotification($code, $this->verifyTtlSec));
        } catch (\Throwable $e) {}

        Cache::put($coolKey, time() + $this->resendCooldownSec, $this->resendCooldownSec);

        return $this->j(['ok' => true, 'resend_after_sec' => $this->resendCooldownSec], 200);
    }

    // ----------------------------------------------------------------------------
    // POST /verify/confirm  ➜ تأكيد التحقق (email + code)
    // ----------------------------------------------------------------------------
    public function verifyConfirm(Request $request)
    {
        $data = $request->validate([
            'email' => ['required','email','max:190'],
            'code'  => ['required','digits:6'],
        ]);

        $email = mb_strtolower(trim($data['email']));
        /** @var MobileUser|null $user */
        $user = MobileUser::where('email', $email)->first();

        if (!$user) {
            return $this->j(['error'=>'invalid_email','message'=>'المستخدم غير موجود.'], 404);
        }

        $rec = DB::table('email_verifications')->where('user_id', $user->id)->first();
        if (!$rec) {
            return $this->j(['error'=>'invalid_code','message'=>'لا يوجد كود تحقق نشط.'], 400);
        }

        $now = $this->now();
        if (!$rec->expires_at || $now->gte(Carbon::parse($rec->expires_at))) {
            return $this->j(['error'=>'code_expired','message'=>'انتهت صلاحية الكود.'], 410);
        }

        if (($rec->attempts ?? 0) >= $this->maxAttempts) {
            return $this->j(['error'=>'too_many_requests','message'=>'محاولات كثيرة. اطلب كودًا جديدًا.'], 429);
        }

        if ($data['code'] !== $rec->code) {
            DB::table('email_verifications')->where('user_id', $user->id)->update([
                'attempts'   => ($rec->attempts ?? 0) + 1,
                'updated_at' => $now,
            ]);
            return $this->j(['error'=>'invalid_code','message'=>'الكود غير صحيح.'], 400);
        }

        if (!$user->email_verified_at) {
            $user->forceFill(['email_verified_at' => $now])->save();
        }

        DB::table('email_verifications')->where('user_id', $user->id)->delete();

        return $this->j(['verified'=>true, 'next'=>'login'], 200);
    }

    // ----------------------------------------------------------------------------
    // Forgot / Reset password (كود 6 أرقام بالبريد)
    // ----------------------------------------------------------------------------
    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required','email','max:190'],
        ]);

        $email = mb_strtolower(trim($data['email']));
        /** @var MobileUser|null $user */
        $user = MobileUser::where('email', $email)->first();

        // لأغراض الخصوصية: ok دائمًا
        if (!$user) {
            return $this->j(['ok'=>true, 'resend_after_sec'=>$this->resendCooldownSec], 200);
        }

        $coolKey = "reset:send:{$user->id}";
        if (Cache::has($coolKey)) {
            $ttl = (int) Cache::get($coolKey) - time();
            return $this->j([
                'error'            => 'too_many_requests',
                'message'          => 'الرجاء الانتظار قبل إعادة الإرسال.',
                'resend_after_sec' => max(0, $ttl),
            ], 429);
        }

        $now  = $this->now();
        $code = $this->makeCode();

        DB::table('password_reset_codes')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'email'           => $email,
                'code'            => $code,
                'attempts'        => 0,
                'expires_at'      => $now->copy()->addSeconds($this->resetTtlSec),
                'resend_after_at' => $now->copy()->addSeconds($this->resendCooldownSec),
                'created_at'      => $now,
                'updated_at'      => $now,
            ]
        );

        try {
            $user->notify(new PasswordResetCodeNotification($code, $this->resetTtlSec));
        } catch (\Throwable $e) {}

        Cache::put($coolKey, time() + $this->resendCooldownSec, $this->resendCooldownSec);

        return $this->j(['ok'=>true, 'resend_after_sec'=>$this->resendCooldownSec], 200);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email'                 => ['required','email','max:190'],
            'code'                  => ['required','digits:6'],
            'password'              => ['required','string','min:8','confirmed'],
        ]);

        $email = mb_strtolower(trim($data['email']));
        /** @var MobileUser|null $user */
        $user = MobileUser::where('email', $email)->first();
        if (!$user) {
            return $this->j(['error'=>'invalid_email','message'=>'المستخدم غير موجود.'], 404);
        }

        $rec = DB::table('password_reset_codes')->where('user_id', $user->id)->first();
        if (!$rec) {
            return $this->j(['error'=>'invalid_code','message'=>'لا يوجد كود إعادة تعيين نشط.'], 400);
        }

        $now = $this->now();
        if (!$rec->expires_at || $now->gte(Carbon::parse($rec->expires_at))) {
            return $this->j(['error'=>'code_expired','message'=>'انتهت صلاحية الكود.'], 410);
        }

        if (($rec->attempts ?? 0) >= $this->maxAttempts) {
            return $this->j(['error'=>'too_many_requests','message'=>'محاولات كثيرة. اطلب كودًا جديدًا.'], 429);
        }

        if ($data['code'] !== $rec->code) {
            DB::table('password_reset_codes')->where('user_id', $user->id)->update([
                'attempts'   => ($rec->attempts ?? 0) + 1,
                'updated_at' => $now,
            ]);
            return $this->j(['error'=>'invalid_code','message'=>'الكود غير صحيح.'], 400);
        }

        // غير كلمة المرور
        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        // تنظيف السجل
        DB::table('password_reset_codes')->where('user_id', $user->id)->delete();

        return $this->j(['reset'=>true, 'next'=>'login'], 200);
    }

    // ----------------------------------------------------------------------------
    // Me / Logout
    // ----------------------------------------------------------------------------
    public function me(Request $request)
    {
        /** @var MobileUser $user */
        $user = $request->user();
        return $this->j(['user' => $this->userPayload($user)]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return $this->j(['ok' => true]);
    }
}

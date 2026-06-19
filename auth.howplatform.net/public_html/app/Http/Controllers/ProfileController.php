<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Support\SessionProfile;
use App\Helpers\RewardsHelper;
use App\Helpers\JwtHelper;
use Carbon\Carbon;

class ProfileController extends Controller
{

    // داخل ProfileController
    private function avatarUrlFor(?string $value): string
    {
        // صورة افتراضية (عدّل المسار إذا كانت في assets/images)
        $fallback = asset('images/Avatar.png');

        $raw = trim((string) ($value ?? ''));
        if ($raw === '')
            return $fallback;

        // 1) إصلاح مسار يبدأ بـ /public/
        if (Str::startsWith($raw, '/public/')) {
            $raw = Str::after($raw, '/public/'); // يحذف /public/
        }

        // 2) رابط مطلق خارجي جاهز (مثلاً من Google)
        if (preg_match('#^https?://#i', $raw)) {
            return $raw;
        }

        // 3) رابط ميديا جاهز بدأ بـ /media/... (اسم المسار media.public)
        if (Str::startsWith($raw, '/media/')) {
            return url($raw); // يحوّله لرابط مطلق
        }

        // 4) رابط تخزين Laravel:
        //    - نمط /storage/avatars/..  → حوّل إلى public disk نسبي avatars/..
        if (Str::startsWith($raw, '/storage/')) {
            $raw = Str::after($raw, '/storage/'); // يصبح avatars/xxx.webp
        }

        //    - نمط avatars/.. على public disk
        if (Str::startsWith($raw, 'avatars/')) {
            if (Storage::disk('public')->exists($raw)) {
                $mtime = Storage::disk('public')->lastModified($raw) ?: time();
                // استخدم راوت الميديا ليتم بثّ الملف مع ترويسة كاش صحيحة
                return route('media.public', ['path' => $raw, 'v' => $mtime]);
            }
            return $fallback;
        }

        // 5) أي قيمة غير معروفة → ارجع للـ fallback
        return $fallback;
    }


    /** عرض صفحة البروفايل */
    public function index(Request $request)
    {
        $user = $request->user();
        $db = DB::connection('diploma');

        $du = $db->table('diploma_users')->where('user_id', $user->id)->first();
        if (!$du) {
            $du = (object) [
                'user_id' => $user->id,
                'phone_number' => '',
                'university_id' => null,
                'specialty_id' => null,
                'diploma_type_id' => null,
                'academic_year_id' => null,
                'gender' => null, // male|female
                'birth_date' => null,
                'is_complete' => 0,
            ];
        }

        $universities = $db->table('universities')->select('id', 'name')->orderBy('name')->get();

        // جلب التخصصات المرتبطة بالجامعة المختارة حالياً (إن وُجدت)
        $specialties = collect();
        if ($du->university_id) {
            $specialties = $db->table('specialties as s')
                ->join('university_specialties as us', 'us.specialty_id', '=', 's.id')
                ->where('us.university_id', $du->university_id)
                ->select('s.id', 's.name')
                ->orderBy('s.name')
                ->get();
        } else {
            // إذا لم تكن هناك جامعة مختارة، اجلب جميع التخصصات
            $specialties = $db->table('specialties')->select('id', 'name')->orderBy('name')->get();
        }

        $diplomaTypes = $db->table('diploma_types')->select('id', 'name')->orderBy('id')->get();
        $years = $db->table('academic_years')->select('id', 'label')->orderBy('id')->get();

        $isVerified = !is_null($user->email_verified_at);

        // لو أتى من منصة الدبلوم مع سبب incomplete احفظ redirect في session عادي
        if ($request->query('reason') === 'incomplete') {
            $goBack = $request->query('redirect');
            if ($goBack) {
                // احفظه دائمًا (ليس flash) حتى بعد عدة نقرات/طلبات
                session()->put('redirect_after_diploma', $goBack);
            }
            session()->flash('active', 'diploma');
            session()->flash('err_diploma', 'يرجى إكمال بيانات الدبلوم للمتابعة. لن تتمكن من استخدام منصة الدبلوم قبل ذلك.');

            // نظافة للـURL
            return redirect()->route('profile.index');
        }

        if ($tab = $request->query('active')) {
            session()->flash('active', $tab);
            return redirect()->route('profile.index');
        }

        // اكتمال الدبلوم الحالي (للعرض فقط)
        $isDiplomaComplete = $this->isDiplomaComplete([
            'phone_number' => $du->phone_number,
            'university_id' => $du->university_id,
            'specialty_id' => $du->specialty_id,
            'diploma_type_id' => $du->diploma_type_id,
            'academic_year_id' => $du->academic_year_id,
            'gender' => $du->gender,
            'birth_date' => $du->birth_date,
        ]);

        // إعداد بيانات التحويل JS (إن لزم)
        $safeRedirect = $this->sanitizeRedirect(session('redirect_after_diploma'));
        $redirectCountdown = session('diploma_redirect_pending') ? 10 : null;
        // نظّف العلم (pending) بعد قراءته مرة واحدة
        if ($redirectCountdown) {
            session()->forget('diploma_redirect_pending');
        }
        if ($request->filled('redirect')) {
            $redir = $request->query('redirect');
            if ($this->sanitizeRedirect($redir)) {
                session()->put('redirect_after_diploma', $redir);
            }
        }
        $avatarUrl = $this->avatarUrlFor($user->avatar ?? null);

        return view('profile.index', compact(
            'user',
            'du',
            'universities',
            'specialties',
            'diplomaTypes',
            'years',
            'isVerified',
            'isDiplomaComplete',
            'safeRedirect',
            'redirectCountdown',
            'avatarUrl'
        ));
    }


    /** حفظ بيانات auth الأساسية (الاسم/الإيميل/الصورة) */
    public function updateAuth(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'avatar' => ['nullable', 'url', 'max:2048'],
        ]);

        $user->fill($data);

        // تغيير الإيميل يلغي التفعيل
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();
        SessionProfile::refresh($user->id);
        return Redirect::route('profile.index')
            ->with('ok', 'تم حفظ بيانات الحساب.')
            ->with('active', 'auth');
    }

    /** حفظ بيانات الدبلوم (قاعدة diploma) */
    public function updateDiploma(Request $request)
    {
        $auth = $request->user();
        $db = DB::connection('diploma');

        // 1) تحقق أساسي + جندر male/female
        $request->validate([
            'phone_number' => ['nullable', 'string', 'max:32'],
            'university_id' => ['nullable', 'integer'],
            'specialty_id' => ['nullable', 'integer'],
            'diploma_type_id' => ['nullable', 'integer'],
            'academic_year_id' => ['nullable', 'integer'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'birth_date' => ['nullable', 'date_format:Y-m-d'],
            'is_complete' => ['nullable', 'in:0,1'],
        ]);

        // 2) تحقق وجود المراجع داخل قاعدة الدبلوم
        $errors = [];
        $checkMap = [
            'university_id' => 'universities',
            'specialty_id' => 'specialties',
            'diploma_type_id' => 'diploma_types',
            'academic_year_id' => 'academic_years',
        ];
        foreach ($checkMap as $field => $table) {
            $val = $request->input($field);
            if ($val !== null && $val !== '') {
                $exists = $db->table($table)->where('id', $val)->exists();
                if (!$exists)
                    $errors[$field] = "القيمة غير موجودة في جدول {$table}.";
            }
        }
        if ($errors) {
            return Redirect::route('profile.index')
                ->withErrors($errors, 'diploma')
                ->withInput()
                ->with('err_diploma', 'لم يتم الحفظ. هناك حقول مرجعية غير صالحة.');
        }

        // 3) تجهيز الحمولة واحتساب اكتمال البيانات فعليًا
        $payload = [
            'user_id' => $auth->id,
            'phone_number' => $request->input('phone_number') ?: null,
            'university_id' => $request->input('university_id') ?: null,
            'specialty_id' => $request->input('specialty_id') ?: null,
            'diploma_type_id' => $request->input('diploma_type_id') ?: null,
            'academic_year_id' => $request->input('academic_year_id') ?: null,
            'gender' => $request->input('gender') ?: null, // male|female
            'birth_date' => $request->input('birth_date') ?: null,
        ];

        // يعتبر الملف "مكتملًا" إذا توفرت كل القيم التالية:
        $isCompleteNow =
            !empty($payload['phone_number']) &&
            !empty($payload['university_id']) &&
            !empty($payload['specialty_id']) &&
            !empty($payload['diploma_type_id']) &&
            !empty($payload['academic_year_id']) &&
            !empty($payload['gender']) &&
            !empty($payload['birth_date']);

        // التحقق من حالة الاكتمال السابقة
        $previousRecord = $db->table('diploma_users')->where('user_id', $auth->id)->first();
        $wasCompleteBefore = $previousRecord && (int) ($previousRecord->is_complete ?? 0) === 1;

        $payload['is_complete'] = $isCompleteNow ? 1 : 0;
        $finalRedirect = $this->sanitizeRedirect(session('redirect_after_diploma'))
            ?? config('services.diploma.url', 'https://diploma.howplatform.net/students');

        // 4) Upsert على diploma_users
        $db->table('diploma_users')->updateOrInsert(
            ['user_id' => $auth->id],
            $payload
        );
        SessionProfile::refresh($auth->id);

        // إذا اكتمل الملف الآن (ولم يكن مكتملاً من قبل)، فعّل التحويل وإعادة توليد Token
        if ($isCompleteNow && !$wasCompleteBefore) {
            // إعادة توليد JWT Token للدخول إلى منصة الدبلوم
            try {
                $jwt = \App\Helpers\JwtHelper::generateToken($auth);
                $this->setSsoCookie($jwt, time() + 60 * 60); // ساعة واحدة
            } catch (\Throwable $e) {
                \Log::error('ProfileController::updateDiploma - Failed to regenerate token: ' . $e->getMessage());
            }

            // خزّن الوجهة إن لم تكن موجودة (حتى لو لم يأتِ من الدبلوم)
            session()->put('redirect_after_diploma', $finalRedirect);
            // فعّل العدّ التنازلي لمرّة واحدة
            session()->flash('diploma_redirect_pending', true);
        }
        // 5) لو اكتمل الملف: فعّل اشتراك مجاني لمدة 30 يوم (مرة واحدة فقط)
        $promoMsg = null;
        if ($isCompleteNow) {
            $promo = $this->grantFreeMonthOctober2025($auth->id);
            if ($promo['granted'] === true) {
                $promoMsg = 'تم تفعيل اشتراك مجاني حتى ' . $promo['end_at']->format('Y-m-d H:i');
            } elseif ($promo['reason'] === 'already_active') {
                $promoMsg = 'لديك اشتراك نشط بالفعل حتى ' . $promo['end_at']->format('Y-m-d H:i');
            } elseif ($promo['reason'] === 'out_of_window') {
                // خارج نافذة الحملة: لا نفعل شيئًا، ولا نعرض رسالة مزعجة
                $promoMsg = null;
            }

            // 5.1) التحقق من إكمال البروفايل ومنح مكافأة الدعوة (إن وُجدت)
            try {
                RewardsHelper::checkAndRewardReferral($auth->id);
            } catch (\Throwable $e) {
                // لا نريد أن يعطل حفظ البروفايل في حالة حدوث خطأ
                \Log::error('ProfileController::updateDiploma - RewardsHelper error: ' . $e->getMessage());
            }
        }

        // 6) رسائل + (اختياري) تحويل تلقائي نحو منصة الدبلوم بعد 10 ثوانٍ
        $msg = $isCompleteNow
            ? 'تم حفظ بيانات الدبلوم بنجاح.' . ($promoMsg ? ' ' . $promoMsg : '')
            : 'تم حفظ البيانات، لكن الملف غير مكتمل. لن تتمكن من استخدام منصة الدبلوم قبل إكمال جميع الحقول.';

        return Redirect::route('profile.index')
            ->with('ok_diploma', $msg)
            ->with('active', 'diploma');
    }

    private function grantFreeMonthOctober2025(int $userId): array
    {
        // استخدم اتصال قاعدة بيانات الدبلوم
        $dbDiploma = DB::connection('diploma');

        // نافذة الحملة: apr 2026 فقط
        $tz = config('app.timezone', 'UTC');
        $now = Carbon::now($tz);

        $campaignStart = Carbon::create(2026, 4, 1, 0, 0, 0, $tz);
        $campaignEnd = (clone $campaignStart)->endOfMonth(); // 2026-04-30 23:59:59

        if ($now->lt($campaignStart) || $now->gt($campaignEnd)) {
            return ['granted' => false, 'reason' => 'out_of_window'];
        }

        $planId  = 6;
        $voucher = 'FreeMonth2026';

        // هل لديه اشتراك نشط لنفس الخطة؟
        $existing = $dbDiploma->table('user_subscriptions')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where('plan_id', $planId)
            ->where('end_at', '>=', $now)
            ->orderByDesc('end_at')
            ->first();

        if ($existing) {
            return [
                'granted' => false,
                'reason'  => 'already_active',
                'end_at'  => Carbon::parse($existing->end_at, $tz),
            ];
        }

        // ── جلب النقاط ديناميكياً من مميزات الخطة ──────────────────────────
        // الجدول: plan_features | الحقول: plan_id, feature_key, value (JSON)
        // نبحث عن الميزة التي تحتوي على مفتاح "points" في الـ value
        $planPoints = 0;
        $pointsFeature = $dbDiploma->table('plan_features')
            ->where('plan_id', $planId)
            ->whereRaw("JSON_VALID(`limits_json`) AND JSON_UNQUOTE(JSON_EXTRACT(`limits_json`, '$.points')) IS NOT NULL")
            ->first();

        if ($pointsFeature) {
            $decoded = json_decode($pointsFeature->limits_json ?? '{}', true);
            $planPoints = (int) ($decoded['points'] ?? 0);
        }
        // ────────────────────────────────────────────────────────────────────

        // فعّل اشتراك مجاني لمدة 30 يوم + منح النقاط داخل transaction واحدة
        $startAt = $now->copy();
        $endAt   = $now->copy()->addDays(30);

        try {
            $dbDiploma->beginTransaction();

            // 1) إنشاء الاشتراك
            $dbDiploma->table('user_subscriptions')->insert([
                'user_id'      => $userId,
                'plan_id'      => $planId,
                'status'       => 'active',
                'start_at'     => $startAt,
                'end_at'       => $endAt,
                'source'       => 'promo',
                'voucher_code' => $voucher,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);

            // 2) إضافة النقاط فقط إذا كانت الخطة تحتوي على نقاط (> 0)
            //    والتحقق من عدم منحها مسبقاً لنفس الكوبون (منع التكرار)
            if ($planPoints > 0) {
                $alreadyRewarded = $dbDiploma->table('rewards_transactions')
                    ->where('user_id',     $userId)
                    ->where('reward_type', 'subscription_grant')
                    ->where('description', 'like', "%{$voucher}%")
                    ->exists();

                if (!$alreadyRewarded) {
                    // 3) جلب أو إنشاء سجل نقاط للمستخدم
                    $rewardsUser = $dbDiploma->table('rewards_users')
                        ->where('user_id', $userId)
                        ->first();

                    if (!$rewardsUser) {
                        $dbDiploma->table('rewards_users')->insert([
                            'user_id'      => $userId,
                            'total_points' => 0,
                            'created_at'   => $now,
                            'updated_at'   => $now,
                        ]);
                    }

                    // 4) إضافة النقاط المجلوبة من مميزات الخطة
                    $dbDiploma->table('rewards_users')
                        ->where('user_id', $userId)
                        ->increment('total_points', $planPoints, ['updated_at' => $now]);

                    // 5) تسجيل معاملة النقاط
                    $dbDiploma->table('rewards_transactions')->insert([
                        'user_id'        => $userId,
                        'points'         => $planPoints,
                        'reward_type'    => 'subscription_grant',
                        'reward_type_id' => null,
                        'description'    => "اشتراك مجاني - كوبون: {$voucher}",
                        'created_at'     => $now,
                    ]);
                }
            }

            $dbDiploma->commit();
        } catch (\Throwable $e) {
            $dbDiploma->rollBack();
            \Log::error('ProfileController::grantFreeMonthOctober2025 - Transaction failed: ' . $e->getMessage());

            return ['granted' => false, 'reason' => 'db_error'];
        }

        return [
            'granted' => true,
            'end_at'  => $endAt,
        ];
    }


    /** تحديث كلمة المرور */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user = $request->user();
        $user->password = Hash::make($request->input('password'));
        $user->save();

        return Redirect::route('profile.index')
            ->with('ok', 'تم تحديث كلمة المرور.')
            ->with('active', 'security');
    }

    /** حذف الحساب  */
    public function destroy(Request $request)
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /** رفع الصورة (مع إبقاء الرابط كاملًا) */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:5120', // 5MB
                'dimensions:max_width=3000,max_height=3000'
            ],
        ], [
            'avatar.required' => 'الرجاء اختيار صورة.',
            'avatar.image' => 'الملف يجب أن يكون صورة.',
            'avatar.mimes' => 'الامتدادات المسموحة: jpeg, jpg, png, webp.',
            'avatar.max' => 'الحجم الأقصى 5MB.',
            'avatar.dimensions' => 'الأبعاد القصوى المسموحة 3000x3000.',
        ]);

        $user = $request->user();

        // خزن الصورة
        $path = $request->file('avatar')->store('avatars', 'public'); // avatars/xxx.webp
        $lastModified = Storage::disk('public')->lastModified($path) ?: time();

        // ابنِ رابط media (أو استعمل Storage::url إن لم يكن لديك media.public)
        $publicUrl = route('media.public', ['path' => $path, 'v' => $lastModified]);

        // حذف القديمة إن كانت محلية
        $this->tryDeletePreviousAvatar($user->avatar);

        // حدّث المستخدم
        $user->avatar = $publicUrl;
        $user->save();
        SessionProfile::refresh($user->id);
        return response()->json([
            'success' => true,
            'url' => $publicUrl,
        ]);
    }

    /* ===================== Helpers ===================== */

    /**
     * تحقّق اكتمال بيانات الدبلوم.
     * الحقول المطلوبة للاكتمال: phone_number, university_id, specialty_id,
     * diploma_type_id, academic_year_id, gender (male|female), birth_date (Y-m-d).
     */
    private function isDiplomaComplete(array $data): bool
    {
        foreach ([
            'phone_number',
            'university_id',
            'specialty_id',
            'diploma_type_id',
            'academic_year_id',
            'gender',
            'birth_date'
        ] as $f) {
            if (!isset($data[$f]) || $data[$f] === '' || $data[$f] === null)
                return false;
        }
        $gender = strtolower((string) $data['gender']);
        if (!in_array($gender, ['male', 'female'], true))
            return false;
        $d = (string) $data['birth_date'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d))
            return false;
        return true;
    }

    /** تأمين رابط التحويل — يسمح فقط howplatform.net */
    private function sanitizeRedirect(?string $url): ?string
    {
        if (!$url)
            return null;
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (in_array($scheme, ['http', 'https'], true) && $host && preg_match('/(\.|^)howplatform\.net$/', $host)) {
            return $url;
        }
        return null;
    }

    /**
     * محاولة حذف الصورة السابقة إن كانت مخزنة على نفس القرص.
     * يدعم روابط /storage/... أو media.public?path=avatars/...
     */
    private function tryDeletePreviousAvatar(?string $previousUrl): void
    {
        if (!$previousUrl)
            return;

        // 1) إن كانت من /storage/avatars/...
        $path = parse_url($previousUrl, PHP_URL_PATH) ?: '';
        if (Str::startsWith($path, '/storage/avatars/')) {
            $oldRelative = Str::after($path, '/storage/'); // avatars/old.webp
            if ($oldRelative) {
                Storage::disk('public')->delete($oldRelative);
                return;
            }
        }

        // 2) إن كانت على media.public (query param path=avatars/...)
        $query = parse_url($previousUrl, PHP_URL_QUERY);
        if ($query) {
            parse_str($query, $q);
            if (!empty($q['path']) && Str::startsWith($q['path'], 'avatars/')) {
                Storage::disk('public')->delete($q['path']);
            }
        }
    }

    /** جلب التخصصات المرتبطة بجامعة معينة */
    public function getSpecialtiesByUniversity(Request $request)
    {
        $request->validate([
            'university_id' => ['required', 'integer', 'min:1'],
        ]);

        $db = DB::connection('diploma');

        $specialties = $db->table('specialties as s')
            ->join('university_specialties as us', 'us.specialty_id', '=', 's.id')
            ->where('us.university_id', $request->university_id)
            ->select('s.id', 's.name')
            ->orderBy('s.name')
            ->get();

        return response()->json([
            'success' => true,
            'specialties' => $specialties,
        ]);
    }

    /**
     * تعيين كوكي SSO للدخول إلى منصة الدبلوم
     */
    private function setSsoCookie(string $value, int $expires): void
    {
        setcookie('howplatform_token', $value, [
            'expires' => $expires,
            'path' => '/',
            'domain' => '.howplatform.net',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'None',
        ]);
    }
}

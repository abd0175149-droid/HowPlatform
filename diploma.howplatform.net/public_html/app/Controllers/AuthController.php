<?php

namespace App\Controllers;
use App\Models\UserModel;
use CodeIgniter\Controller;
use CodeIgniter\I18n\Time;
use App\Enums\AccountType;
use Config\Services;

require_once ROOTPATH . 'vendor/autoload.php';
helper('cookie');

class AuthController extends Controller
{
    public function registerRedirect()
    {
        return redirect()->to(
            'https://auth.howplatform.net/register?redirect=https://diploma.howplatform.net/students'
        );
    }
    
    public function loginRedirect()
    { 
        // تقدر تمسح منطق الكوكي والجلسة إذا مش محتاجه
        session()->destroy();
        
        return redirect()->to(
            'https://auth.howplatform.net/login?redirect=https://diploma.howplatform.net/students'
        );
    }

    public function login()                         
    { 
        helper('cookie');

        $savedEmail = get_cookie('saved_email');
        session()->destroy();
        return view('partials/header')
             . view('auth/login', ['email' => $savedEmail])
             . view('partials/footer');
    }
    public function doLoginOLD()
    {
        helper(['form', 'cookie']);
    
        $email    = trim($this->request->getPost('email'));
        $password = $this->request->getPost('password');
    
        // التحقق من الإدخال
        if (! $email || ! $password) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'الرجاء إدخال البريد وكلمة المرور.',
            ]);
        }
    
        $userModel = new \App\Models\UserModel();
        $user      = $userModel->where('email', $email)->first();
    
        // تحقق من وجود المستخدم
        if (! $user) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'هذا البريد غير مسجل لدينا.',
            ]);
        }
    
        // تحقق من كلمة المرور
        if (! password_verify($password, $user['password'])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'كلمة المرور غير صحيحة.',
            ]);
        }
    
        // تحقق من التفعيل
        if (! $user['is_verified']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'يرجى تفعيل الحساب من البريد الإلكتروني أولاً.',
            ]);
        }
    
        // ✅ تخزين بيانات الجلسة
        session()->set([
            'user_id'   => $user['id'],
            'user_name' => $user['name'],
            'role'      => $user['account_type_id'],
            'is_premium'=> $user['is_premium'],
        ]);
    
        // ✅ منطق حفظ البريد في الكوكيز بناءً على موافقة المستخدم
        $remember = $this->request->getPost('remember_email');
        $cookieConsent = get_cookie('cookie_consent'); // accepted | rejected | null
    
        if ($remember && $cookieConsent === 'accepted') {
            set_cookie('saved_email', $email, 60 * 60 * 24 * 30); // 30 يوم
        } else {
            delete_cookie('saved_email'); // في حال رفض أو ألغى المستخدم الاختيار
        }
    
        // ✅ التوجيه بعد تسجيل الدخول
    /*    $redirect = $this->calcRedirect(
            $user['account_type_id'],
            $user['is_complete'],
            $user['consent_given'] ?? false
        );*/
        
        $redirect = $this->calcRedirect(
            $user['account_type_id'],
            1, // override مؤقت
            $user['consent_given'] ?? false
        );
    
        return $this->response->setJSON([
            'success'  => true,
            'redirect' => $redirect,
        ]);
    }
    private function calcRedirectTemp(int $role, bool $completed, bool $consented): string
    {
        if (! $consented) {
            return site_url('auth/accept_terms');
        }
    
        return match ($role) {
            AccountType::ADMIN->value
                => $completed ? site_url('admin') : site_url('auth/temp-profile'),
            AccountType::TEACHER->value
                => $completed ? site_url('admin') : site_url('auth/temp-profile'),
            AccountType::TRIAL->value
                => $completed ? site_url('admin') : site_url('auth/temp-profile'),
            AccountType::STUDENT->value
                => $completed ? site_url('student') : site_url('auth/temp-profile'),
            default => site_url('[fakeadmin'),
        };
    }
    private function calcRedirect(int $role, bool $completed, bool $consented): string
    {
        if (! $consented) {
            return site_url('auth/accept_terms');
        }
    
        return match ($role) {
            AccountType::ADMIN->value
                => $completed ? site_url('admin') : site_url('auth/complete-profile'),
            AccountType::TEACHER->value
                => $completed ? site_url('admin') : site_url('auth/complete-profile'),
            AccountType::TRIAL->value
                => $completed ? site_url('admin') : site_url('auth/complete-profile'),
            AccountType::STUDENT->value
                => $completed ? site_url('student') : site_url('auth/complete-profile'),
            default => site_url('[fakeadmin'),
        };
    }
    public function register()
    {
        helper('form');
        return view('partials/header')
             . view('auth/register')
             . view('partials/footer');
    }
    public function doRegister()
    {
    helper(['form', 'url', 'text']);

    $req = $this->request;

    $name     = trim($req->getPost('username'));
    $email    = trim($req->getPost('email'));
    $password = $req->getPost('password');
    $confirm  = $req->getPost('confirm_password');

    // ✅ التحقّقات
    if (! $name || ! $email || ! $password || ! $confirm) {
        return $this->response->setJSON([
            'success' => false,
            'message' => 'جميع الحقول مطلوبة.'
        ]);
    }

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $this->response->setJSON([
            'success' => false,
            'message' => 'البريد الإلكتروني غير صالح.'
        ]);
    }

    if ($password !== $confirm) {
        return $this->response->setJSON([
            'success' => false,
            'message' => 'كلمتا المرور غير متطابقتين.'
        ]);
    }

    if (
        strlen($password) < 8 ||
        ! preg_match('/[A-Za-z]/', $password) ||
        ! preg_match('/\d/', $password) ||
        ! preg_match('/[\W]/', $password)
    ) {
        return $this->response->setJSON([
            'success' => false,
            'message' => 'كلمة المرور ضعيفة. يجب أن تحتوي على حرف ورقم ورمز خاص.'
        ]);
    }

    $userModel = new \App\Models\UserModel();
    $existing = $userModel->select('id,is_verified,verification_token,name')
                          ->where('email', $email)
                          ->first();

    // ✅ حساب موجود وغير مفعّل
    if ($existing && $existing['is_verified'] == 0) {
        $token = $existing['verification_token'] ?: random_string('hex', 32);

        if (! $existing['verification_token']) {
            $userModel->update($existing['id'], ['verification_token' => $token]);
        }

        $this->sendActivationMail($email, $existing['name'], $token);

        return $this->response->setJSON([
            'success' => false,
            'message' => 'تم إنشاء الحساب ولكن تعذر إرسال رسالة التفعيل. يرجى التواصل مع الدعم.',
            'redirect' => site_url('auth/followup?status=mail_failed')
        ]);
    }

    // ✅ حساب موجود ومفعّل
    if ($existing) {
        return $this->response->setJSON([
            'success' => false,
            'message' => 'البريد الإلكتروني مستخدم بالفعل.'
        ]);
    }

    // ✅ إنشاء حساب جديد
    $token = bin2hex(random_bytes(32));
    $data = [
        'name'               => $name,
        'email'              => $email,
        'password'           => password_hash($password, PASSWORD_DEFAULT),
        'verification_token' => $token,
        'is_verified'        => 0,
        'account_type_id' => AccountType::STUDENT->value,
        'created_at'         => \CodeIgniter\I18n\Time::now(),
    ];

    if (! $userModel->insert($data)) {
        log_message('error', 'Insert user failed: ' . print_r($userModel->errors(), true));
        return $this->response->setJSON([
            'success' => false,
            'message' => 'تعذّر إنشاء الحساب، حاول مجدداً أو تواصل مع الدعم.'
        ]);
    }

    $this->sendActivationMail($email, $name, $token);

    return $this->response->setJSON([
        'success'  => true,
        'redirect' => site_url('auth/followup?status=success'),
    ]);
}
    private function sendActivationMail(string $email, string $name, string $token): void
    {
        $verifyLink = site_url('auth/verify?token=' . urlencode($token));
    
        $mail = \Config\Services::email();
    
        // إن كنت تعتمد قيم SMTP في Email.php اترك from فارغاً
        $mail->setFrom('support@howplatform.net', 'منصة كيف التعليمية');
        $mail->setTo($email);
        $mail->setSubject('تفعيل حسابك على منصة كيف التعليمية');
    
        // نص بديل (Plain-Text) اختياري
        $mail->setAltMessage(
            "مرحباً {$name}\nلإكمال التسجيل اضغط الرابط:\n{$verifyLink}\n\t"
            . "إن لم تطلب ذلك تجاهل الرسالة أو تواصل معنا."
        );
    
        // قالب HTML
        $mail->setMessage(view('auth/email_template', [
            'name'          => $name,
            'link'          => $verifyLink,
            'contact_email' => 'support@howplatform.net',
        ]));
    
        $mail->setMailType('html'); // ضروري لإظهار التنسيق
    
        // الإرسال (ستُسجَّل الأخطاء في logs إن وُجدت)
        $mail->send();
    }
    public function verify()
    {
        $token = $this->request->getGet('token');
    
        // لا داعى للاستمرار إن لم يوجد توكن
        if (! $token) {
            return $this->showVerifyResult('missing');
        }
    
        $userModel = new \App\Models\UserModel();
        $user      = $userModel->where('verification_token', $token)->first();
    
        // 1) رابط غير صالح
        if (! $user) {
            return $this->showVerifyResult('invalid');
        }
    
        // 2) الحساب مُفعَّل من قبل
        if ($user['is_verified'] == 1) {
            return $this->showVerifyResult('already');
        }
    
        // 3) تفعيل الحساب
        $userModel->update($user['id'], [
            'is_verified'        => 1,
            'verification_token' => null
        ]);
    
        return $this->showVerifyResult('success');
    }
    private function showVerifyResult(string $status)
    {
        return view('partials/header')
             . view('auth/verify_result', ['status' => $status])
             . view('partials/footer');
    }
    public function followup()
    {
    $status = $this->request->getGet('status');   // success | mail_failed | null

    return view('partials/header')
         . view('auth/followup', ['status' => $status])
         . view('partials/footer');
}
    public function completeProfile()
    {
        $userId = session('user_id');
        if (! $userId) return redirect()->to(site_url('auth/login'));
    
        $userModel = new \App\Models\UserModel();
        $user = $userModel->find($userId);
    
        $db = \Config\Database::connect();
    
        // جلب جميع أنواع الدبلوم
        $diplomaTypes = $db->table('diploma_types')->select('id, name')->get()->getResultArray();
    
        // جلب الجامعات المرتبطة بكل نوع دبلوم
        $uniDiplomaMap = $db->table('university_diploma_types')
            ->select('diploma_type_id, university_id, universities.name as university_name')
            ->join('universities', 'universities.id = university_diploma_types.university_id')
            ->get()->getResultArray();
    
        // جلب التخصصات المرتبطة بكل جامعة
        $specialties = $db->table('university_specialties')
            ->select('university_id, specialties.id as specialty_id, specialties.name as specialty_name')
            ->join('specialties', 'specialties.id = university_specialties.specialty_id')
            ->get()->getResultArray();
    
        // جلب الأعوام الدراسية المرتبطة بكل جامعة
        $academicYears = $db->table('university_academic_years')
            ->select('university_id, academic_years.id as year_id, academic_years.label as year_name')
            ->join('academic_years', 'academic_years.id = university_academic_years.academic_year_id')
            ->get()->getResultArray();
            $universities = [];
        foreach ($uniDiplomaMap as $row) {
            $universities[$row['university_id']] = $row['university_name'];
        }
        $universities = array_map(function ($id, $name) {
            return ['id' => $id, 'name' => $name];
        }, array_keys($universities), $universities);

        return view('partials/header')
            . view('auth/complete_profile', [
                'user' => $user,
                'diploma_types' => $diplomaTypes,
                'universities_map' => $uniDiplomaMap,
                'specialties_map' => $specialties,
                'universities' => $universities,
                'academic_years_map' => $academicYears
            ])
            . view('partials/footer');
    }
    public function doCompleteProfile()
    {
    helper('form');
    $request = service('request');

    $userId = session('user_id');
    if (! $userId) {
        return $this->response->setStatusCode(401)->setJSON([
            'success' => false,
            'message' => 'يجب تسجيل الدخول أولاً.'
        ]);
    }

    $data = [
        'name'             => trim($request->getPost('name')),
        'phone_number'     => trim($request->getPost('phone_number')),
        'gender'           => trim($request->getPost('gender')),
        'birth_date'       => $request->getPost('birth_date'),
        'academic_year_id' => (int) $request->getPost('academic_year_id'),
        'university_id'    => (int) $request->getPost('university_id'),
        'specialty_id'     => (int) $request->getPost('specialty_id'),
        'diploma_type_id'  => (int) $request->getPost('diploma_type_id'),
        'is_complete'      => 1
    ];

    // التحقق من الحقول
    $requiredFields = [
        'name' => 'يرجى إدخال الاسم.',
        'phone_number' => 'يرجى إدخال رقم الهاتف.',
        'gender' => 'يرجى اختيار الجنس.',
        'birth_date' => 'يرجى إدخال تاريخ الميلاد.',
        'academic_year_id' => 'يرجى اختيار السنة الدراسية.',
        'university_id' => 'يرجى اختيار الجامعة.',
        'specialty_id' => 'يرجى اختيار التخصص.',
        'diploma_type_id' => 'يرجى اختيار نوع الدبلوم.'
    ];

    foreach ($requiredFields as $field => $message) {
        if (empty($data[$field])) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => $message
            ]);
        }
    }

    $userProfileModel = new \App\Models\UserProfileModel();

    if ($userProfileModel->update($userId, $data)) {
        return $this->response->setJSON([
            'success'  => true,
            'message'  => 'تم حفظ البيانات بنجاح!',
            'redirect' => site_url('auth/survey') // ✅ التوجيه إلى صفحة الاستبيان
        ]);
    } else {
        return $this->response->setStatusCode(500)->setJSON([
            'success' => false,
            'message' => 'حدث خطأ أثناء حفظ البيانات. يرجى المحاولة مرة أخرى.'
        ]);
    }
}
    public function TempProfile()
    {
        $userId = session('user_id');
        if (! $userId) return redirect()->to(site_url('auth/login'));
    
        $userModel = new \App\Models\UserModel();
        $user = $userModel->find($userId);
    
        $db = \Config\Database::connect();

        return view('partials/header')
            . view('auth/temp_profile', [
                'user' => $user
            ])
            . view('partials/footer');
    }
    public function doTempProfile()
    {
        helper('form');
        $request = service('request');
    
        $userId = session('user_id');
        if (!$userId) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً.'
            ]);
        }
    
        $data = [
            'name'         => trim((string) $request->getPost('name')),
            'phone_number' => trim((string) $request->getPost('phone_number')),
            'gender'       => trim((string) $request->getPost('gender')),
            'birth_date'   => $request->getPost('birth_date'),
            'is_complete'  => 1,
        ];
    
        // تحققات أساسية
        $required = [
            'name'         => 'يرجى إدخال الاسم.',
            'phone_number' => 'يرجى إدخال رقم الهاتف.',
            'gender'       => 'يرجى اختيار الجنس.',
            'birth_date'   => 'يرجى إدخال تاريخ الميلاد.'
        ];
        foreach ($required as $field => $msg) {
            if (empty($data[$field])) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => $msg
                ]);
            }
        }
    
        // ✅ التحقق من صيغة رقم أردني
        if (! preg_match('~^(?:\+?962|00962|0)7[7-9]\d{7}$~', $data['phone_number'])) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'رقم الهاتف الأردني غير صحيح. الصيغة: 07XXXXXXXX.'
            ]);
        }
    
        // ✅ منع تاريخ ميلاد مستقبلي
        if (strtotime($data['birth_date']) > time()) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'تاريخ الميلاد لا يمكن أن يكون في المستقبل.'
            ]);
        }
    
        // ✅ التحقق من تفرّد رقم الهاتف (باستثناء نفس المستخدم)
        $db = \Config\Database::connect();
        $exists = $db->table('users')
            ->select('id')
            ->where('phone_number', $data['phone_number'])
            ->where('id !=', $userId)
            ->get()->getFirstRow();
        if ($exists) {
            return $this->response->setStatusCode(409)->setJSON([
                'success' => false,
                'message' => 'رقم الهاتف مستخدم من حساب آخر. الرجاء إدخال رقم مختلف.'
            ]);
        }
    
        // حفظ
        $model = new \App\Models\UserProfileModel();
    
        try {
            $ok = $model->update($userId, $data);
            if (! $ok) {
                // أعطِ سببًا واضحًا أثناء التطوير
                $errors  = $model->errors();
                $dbError = $model->db->error();
                return $this->response->setStatusCode(400)->setJSON([
                    'success'  => false,
                    'message'  => 'تعذّر حفظ البيانات. رجاءً صحّح الحقول وأعد المحاولة.',
                    'errors'   => $errors ?: null,
                    'db_error' => $dbError['message'] ?? null,
                ]);
            }
    
            return $this->response->setJSON([
                'success'  => true,
                'message'  => 'تم حفظ البيانات بنجاح!',
                'redirect' => site_url('auth/survey')
            ]);
    
        } catch (\Throwable $e) {
            log_message('critical', 'TempProfile exception: {ex}', ['ex' => $e->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'حدث استثناء غير متوقع أثناء الحفظ.'
            ]);
        }
    }
    public function forgot()
    {
        return view('partials/header')
             . view('auth/forgot_password')
             . view('partials/footer');
    }
    public function sendResetLink()
    {
    $email = $this->request->getPost('email');
    $userModel = new \App\Models\UserModel();
    $user = $userModel->where('email', $email)->first();

    if (! $user) {
        return view('partials/header')
     . view('auth/forgot_password', ['error_message' => 'البريد الإلكتروني غير مسجل.'])
     . view('partials/footer');
    }

    // ✅ توليد رمز تحقق وتحديثه في قاعدة البيانات
    $token = bin2hex(random_bytes(32));
    $userModel->update($user['id'], ['verification_token' => $token]);

    // ✅ إنشاء رابط إعادة التعيين
    $link = site_url('auth/reset-password?token=' . $token);

    // ✅ إنشاء قالب البريد
    $emailBody = view('auth/email_template_reset', ['link' => $link]);

    // ✅ إعداد البريد باستخدام CodeIgniter Email
    $emailService = \Config\Services::email();
    $emailService->setTo($email);
    $emailService->setSubject('ضبط كلمة مرور حسابك | منصة كيف؟ التعليمية');
    $emailService->setMessage($emailBody);
    $emailService->setMailType('html'); // مهم: لجعل القالب يعمل كـ HTML

    if ($emailService->send()) {
        return view('partials/header')
     . view('auth/forgot_password', ['success_message' => 'تم إرسال رابط إعادة التعيين إلى بريدك الإلكتروني.'])
     . view('partials/footer');

    }

   return view('partials/header')
     . view('auth/forgot_password', ['error_message' => 'حدث خطأ أثناء إرسال البريد.'])
     . view('partials/footer');

}
    public function reset()
    {
        $token = $this->request->getGet('token');
        if (! $token) return redirect()->to(site_url('auth/forgot-password'))->with('error', 'الرابط غير صالح.');
    
        $userModel = new \App\Models\UserModel();
        $user = $userModel->where('verification_token', $token)->first();
    
        if (! $user) {
            return redirect()->to(site_url('auth/forgot-password'))->with('error', 'رمز الاستعادة غير صالح أو منتهي.');
        }
    
        return view('partials/header')
             . view('auth/reset_password', ['token' => $token])
             . view('partials/footer');
    }
    public function doReset()
    {
        $token = $this->request->getPost('token');
        $newPassword = $this->request->getPost('new_password');
        $confirm = $this->request->getPost('confirm_password');
    
        if ($newPassword !== $confirm || strlen($newPassword) < 8) {
            return $this->response->setJSON(['success' => false, 'message' => 'تأكد من مطابقة كلمة المرور والتزامها بالشروط.']);
        }
    
        $userModel = new \App\Models\UserModel();
        $user = $userModel->where('verification_token', $token)->first();
    
        if (! $user) {
            return $this->response->setJSON(['success' => false, 'message' => 'رمز غير صالح أو منتهي.']);
        }
    
        $userModel->update($user['id'], [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
            'verification_token' => null
        ]);
    
        return $this->response->setJSON(['success' => true, 'message' => 'تم تغيير كلمة المرور بنجاح.', 'redirect' => site_url('auth/login')]);
    }
    public function survey()
    {
        $userId = session('user_id');
        if (! $userId) return redirect()->to(site_url('auth/login'));
    
            return view('partials/header')
                 . view('auth/survey')
                 . view('partials/footer');
        }
    public function doSurvey()
    {
        helper(['form', 'text']);
        $request = service('request');
    
        // 🟡 تحقق من تسجيل الدخول
        $userId = session('user_id');
        if (! $userId) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً.'
            ]);
        }
    
        // 🟢 جلب البيانات من النموذج
        $data = [
            'user_id'            => $userId,
            'how_known_source'   => $request->getPost('how_known'),
            'other_source_text'  => $request->getPost('other_source'),
            'join_reasons'       => json_encode($request->getPost('reasons') ?? []),
            'other_reason_text'  => $request->getPost('other_reason_text'),
            'suggested_services' => json_encode($request->getPost('services') ?? []),
            'device_used'        => $request->getPost('device'),
            'satisfaction_level' => (int) $request->getPost('satisfaction'),
        ];
    
        // ✅ حفظ البيانات باستخدام الموديل
        $model = new \App\Models\SurveyResponseModel();
    
        if ($model->insert($data)) {
            // 🟢 استخدام calcRedirect بدل التوجيه الثابت
            $redirectUrl = $this->calcRedirect(
        session('role'),
        true,
        true
    );
    
    
            return $this->response->setJSON([
                'success' => true,
                'message' => 'شكراً لمشاركتك في الاستبيان!',
                'redirect' => $redirectUrl
            ]);
        } else {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'حدث خطأ أثناء حفظ البيانات. حاول مرة أخرى.'
            ]);
        }
    }
    public function logoutOLD()
    {
        session()->destroy();
        return redirect()->to(site_url('auth/login'));
    }


    public function googleRedirect()
    {
        $client = new \Google_Client();
        $client->setClientId(getenv('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri(getenv('GOOGLE_REDIRECT_URI'));
        $client->addScope('email');
        $client->addScope('profile');
    
        $authUrl = $client->createAuthUrl();
        return redirect()->to($authUrl);
    }
    public function AcceptTerms()                         
    {
        return view('partials/header')
             . view('auth/accept_terms')
             . view('partials/footer');
    }
    public function doAcceptTerms()
    {
        $userId = session('user_id');
        $action = $this->request->getPost('action');
    
        $userModel = new \App\Models\UserModel();
    
        // 🟡 تحميل بيانات المستخدم أولاً
        $user = $userModel->find($userId);
    
        if (! $user) {
            session()->destroy();
            return redirect()->to('/auth/login')->with('message', 'حدث خطأ. الحساب غير موجود.');
        }
    
        if ($action === 'accept') {
            $userModel->update($userId, [
                'consent_given' => 1,
                'consent_date'  => date('Y-m-d H:i:s'),
            ]);
    
            // ✅ إعادة تحميل القيم بعد التحديث
            $user['consent_given'] = 1;
            $user['consent_date']  = date('Y-m-d H:i:s');
    
            // ✅ توجيه حسب الدور والحالة
           /* $redirect = $this->calcRedirect(
                $user['account_type_id'],
                $user['is_complete'],
                true // consented
            );*/
             $redirect = $this->calcRedirectTemp(
                $user['account_type_id'],
                $user['is_complete'],
                true // consented
            );
        
    
    
            return redirect()->to($redirect);
        }
    
        $userModel->delete($userId);
        session()->destroy();
    
        return redirect()->to('/auth/login')->with('message', 'تم حذف حسابك بسبب رفض الشروط.');
    }
    public function Terms()                         
    {
            return view('partials/header')
                 . view('auth/terms')
                 . view('partials/footer');
        }
    public function Privacy()                         
    {
        return view('partials/header')
             . view('auth/privacy')
             . view('partials/footer');
    }
    public function FAQ()                         
    {
        return view('partials/header')
             . view('auth/faq')
             . view('partials/footer');
    }
    public function Contact()                         
    {
        return view('partials/header')
             . view('auth/contact')
             . view('partials/footer');
    }
        public function News()                         
    {
        return view('partials/header')
             . view('auth/news')
             . view('partials/footer');
    }
// تجربة تسجيل الدخول مع الوقت 
    public function doLogin()
    {
        helper(['form', 'cookie']);

        $email    = trim($this->request->getPost('email'));
        $password = $this->request->getPost('password');

        // التحقق من الإدخال
        if (! $email || ! $password) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'الرجاء إدخال البريد وكلمة المرور.',
            ]);
        }

        $userModel = new UserModel();
        $user      = $userModel->where('email', $email)->first();

        // تحقق من وجود المستخدم
        if (! $user) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'هذا البريد غير مسجل لدينا.',
            ]);
        }

        // تحقق من كلمة المرور
        if (! password_verify($password, $user['password'])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'كلمة المرور غير صحيحة.',
            ]);
        }

        // تحقق من التفعيل
        if (! $user['is_verified']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'يرجى تفعيل الحساب من البريد الإلكتروني أولاً.',
            ]);
        }

        // ✅ تخزين بيانات الجلسة
        session()->set([
            'user_id'    => $user['id'],
            'user_name'  => $user['name'],
            'role'       => $user['account_type_id'],
            'is_premium' => $user['is_premium'],
        ]);

        // ✅ منطق حفظ البريد في الكوكيز بناءً على موافقة المستخدم
        $remember       = $this->request->getPost('remember_email');
        $cookieConsent  = get_cookie('cookie_consent'); // accepted | rejected | null

        if ($remember && $cookieConsent === 'accepted') {
            set_cookie('saved_email', $email, 60 * 60 * 24 * 30); // 30 يوم
        } else {
            delete_cookie('saved_email');
        }

        // ✅ تحديث وقت آخر تسجيل دخول
        // نستخدم Time::now() ليحترم التايمزون في إعدادات التطبيق
        // إن أردت الحفظ بتوقيت عمان بشكل صريح:
        // $now = Time::now('Asia/Amman')->toDateTimeString();
        $now = Time::now()->toDateTimeString();

        // تحديث مباشر عبر الموديل (يتطلب وجود last_login_at في allowedFields)
        $userModel->update($user['id'], [
            'last_login_at' => $now, // 👈 تحديث الحقل
            // 'last_seen_at' => $now, // (اختياري) في حال أردت أيضًا تهيئته عند تسجيل الدخول
        ]);

        // ✅ التوجيه بعد تسجيل الدخول (منطِقك الحالي)
        $redirect = $this->calcRedirect(
            $user['account_type_id'],
            1, // override مؤقت كما في كودك
            $user['consent_given'] ?? false
        );

        return $this->response->setJSON([
            'success'  => true,
            'redirect' => $redirect,
        ]);
    }
    public function googleCallback()
    {
            try {
                // ✅ تهيئة Google Client
                $client = new \Google_Client();
                $client->setClientId(getenv('GOOGLE_CLIENT_ID'));
                $client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
                $client->setRedirectUri(getenv('GOOGLE_REDIRECT_URI'));
                $client->addScope('email');
                $client->addScope('profile');
    
                // ✅ استلام code من Google
                $code = $this->request->getGet('code');
                if (! $code) {
                    return redirect()->to(site_url('auth/login'))
                        ->with('error', 'لم يتم الحصول على رمز التحقق من Google.');
                }
    
                // ✅ جلب التوكن
                $token = $client->fetchAccessTokenWithAuthCode($code);
                if (isset($token['error'])) {
                    log_message('error', 'Google Token Error: ' . json_encode($token));
                    return redirect()->to(site_url('auth/login'))
                        ->with('error', 'فشل في الحصول على التوكن من Google.');
                }
    
                $client->setAccessToken($token['access_token']);
    
                // ✅ جلب بيانات المستخدم من Google
                $oauth       = new \Google_Service_Oauth2($client);
                $googleUser  = $oauth->userinfo->get();
                $googleId    = $googleUser->id ?? null;
                $profileImage= $googleUser->picture ?? 'default.png';
    
                // ✅ التحقق/الإنشاء
                $userModel = new UserModel();
                $user      = $userModel->where('google_id', $googleId)->first();
    
                if (! $user) {
                    // محاولة الربط بالبريد (توافقًا مع حسابات قديمة)
                    $user = $userModel->where('email', $googleUser->email)->first();
    
                    if ($user && empty($user['google_id'])) {
                        // ربط حساب جوجل بالحساب الحالي
                        $userModel->update($user['id'], ['google_id' => $googleUser->id]);
                    }
                }
    
                // 🕒 وقت آخر تسجيل دخول (يحترم التايمزون من إعدادات CI)
                // إن أردت فرض عمان صراحة: Time::now('Asia/Amman')->toDateTimeString()
                $now = Time::now()->toDateTimeString();
    
                if (! $user) {
                    // ✅ إنشاء مستخدم جديد + تعيين last_login_at فورًا
                    $userId = $userModel->insert([
                        'name'              => $googleUser->name,
                        'email'             => $googleUser->email,
                        'google_id'         => $googleId,
                        'password'          => password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT),
                        'is_verified'       => 1,
                        'is_premium'        => 0,
                        'is_complete'       => 0,
                        'account_type_id'   => AccountType::STUDENT->value,
                        'profile_image_url' => $profileImage,
                        'last_login_at'     => $now, // ✅ عند الإنشاء
                    ]);
    
                    $user = $userModel->find($userId);
                } else {
                    // ✅ تحديث صورة الملف الشخصي إن كانت default
                    if (($user['profile_image_url'] ?? 'default.png') === 'default.png'
                        && $profileImage !== 'default.png') {
                        $userModel->update($user['id'], ['profile_image_url' => $profileImage]);
                        $user['profile_image_url'] = $profileImage; // تحديث محلي
                    }
    
                    // ✅ تحديث وقت آخر تسجيل دخول للمستخدم الموجود
                    $userModel->update($user['id'], ['last_login_at' => $now]);
                    // ملاحظة: لو لم تُضِف الحقل إلى allowedFields، بديل سريع:
                    // db_connect()->table('users')->where('id', $user['id'])->update(['last_login_at' => $now]);
                }
    
                // ✅ تخزين الجلسة
                session()->set([
                    'user_id'    => $user['id'],
                    'user_name'  => $user['name'],
                    'role'       => $user['account_type_id'],
                    'is_premium' => $user['is_premium'],
                ]);
    
                // ✅ إعادة التوجيه حسب منطقك الحالي
                $redirect = $this->calcRedirectTemp(
                    $user['account_type_id'],
                    $user['is_complete'],
                    $user['consent_given'] ?? false
                );
    
                return redirect()->to($redirect);
            } catch (\Exception $e) {
                log_message('error', 'Google Login Error: ' . $e->getMessage());
                return redirect()->to(site_url('auth/login'))
                    ->with('error', 'حدث خطأ أثناء تسجيل الدخول بواسطة Google.');
            }
        }
    public function doLoginTemp(): \CodeIgniter\HTTP\ResponseInterface
    {
        helper(['form', 'cookie']);
    
        $email    = trim($this->request->getPost('email'));
        $password = $this->request->getPost('password');
    
        // ✅ التحقق من الإدخال
        if (!$email || !$password) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'الرجاء إدخال البريد وكلمة المرور.',
            ]);
        }
    
        $userModel = new \App\Models\UserModel();
        $user      = $userModel->where('email', $email)->first();
    
        // ✅ تحقق من وجود المستخدم
        if (!$user) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'هذا البريد غير مسجل لدينا.',
            ]);
        }
    
        // ✅ تحقق من كلمة المرور
        if (!password_verify($password, $user['password'])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'كلمة المرور غير صحيحة.',
            ]);
        }
    
        // ✅ تحقق من التفعيل
        if (!($user['is_verified'] ?? 0)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'يرجى تفعيل الحساب من البريد الإلكتروني أولاً.',
            ]);
        }
    
        // ✅ تخزين بيانات الجلسة
        session()->set([
            'user_id'    => $user['id'],
            'user_name'  => $user['name'],
            'role'       => $user['account_type_id'],
            'is_premium' => $user['is_premium'],
        ]);
    
        // ✅ منطق حفظ البريد في الكوكيز بناءً على موافقة المستخدم
        $remember      = $this->request->getPost('remember_email');
        $cookieConsent = get_cookie('cookie_consent'); // accepted | rejected | null
        if ($remember && $cookieConsent === 'accepted') {
            set_cookie('saved_email', $email, 60 * 60 * 24 * 30); // 30 يوم
        } else {
            delete_cookie('saved_email');
        }
    
        // ✅ تحديث وقت آخر تسجيل دخول (نستخدم توقيت عمّان مباشرةً)
        $now = Time::now('Asia/Amman')->toDateTimeString();
    
        // نحاول أولاً عبر الموديل (يتطلب وجود last_login_at ضمن allowedFields)
        $ok = $userModel->update($user['id'], ['last_login_at' => $now]);
    
        // خطة بديلة مضمونة عبر Query Builder إذا فشل التحديث بالموديل
        if (!$ok) {
            db_connect()
                ->table('users')
                ->where('id', $user['id'])
                ->update(['last_login_at' => $now]);
            // يمكن تسجيل سبب الفشل أثناء التطوير:
            // log_message('debug', 'Model update failed: '.json_encode($userModel->errors()));
        }
    
        // ✅ التوجيه بعد تسجيل الدخول (منطقك المؤقت)
        $redirect = $this->calcRedirectTemp(
            $user['account_type_id'],
            $user['is_complete'],
            $user['consent_given'] ?? false
        );
    
        return $this->response->setJSON([
            'success'  => true,
            'redirect' => $redirect,
        ]);
    }
    /**
     * مسار إعادة التوجيه الذكي
     * يحدد نوع الحساب ويوجه المستخدم للمسار المناسب
     * إذا كان الحساب غير مكتمل البيانات، يعيده لصفحة البروفايل
     */
    public function redirect()
    {
        $session = session();
        $authUser = $session->get('auth_user') ?? [];
        $userId = (int)($authUser['id'] ?? 0);

        // إذا لم يكن مسجل دخول، إعادة توجيه لصفحة الدخول
        if ($userId <= 0) {
            $currentUrl = current_url();
            $loginUrl = 'https://auth.howplatform.net/login?redirect=' . urlencode($currentUrl);
            
            // دعم AJAX requests
            if ($this->request->isAJAX()) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'يجب تسجيل الدخول أولاً.',
                    'redirect' => $loginUrl,
                ])->setStatusCode(401);
            }
            
            return redirect()->to($loginUrl);
        }

        $db = \Config\Database::connect();
        
        // جلب بيانات الدبلوم للتحقق من اكتمال البيانات
        $diplomaUser = $db->table('diploma_users')
            ->select('is_complete, account_type_id')
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();

        $isComplete = (int)($diplomaUser['is_complete'] ?? 0);
        $role = (int)($authUser['role'] ?? ($diplomaUser['account_type_id'] ?? 3));

        // إذا كان الحساب غير مكتمل البيانات، إعادة توجيه لصفحة البروفايل
        if ($isComplete !== 1) {
            $redirectUrl = 'https://auth.howplatform.net/profile'
                . '?active=diploma'
                . '&reason=incomplete'
                . '&message=' . urlencode('يرجى إكمال بيانات الدبلوم أولاً')
                . '&redirect=' . urlencode(current_url());
            
            // دعم AJAX requests
            if ($this->request->isAJAX()) {
                return $this->response->setJSON([
                    'success' => false,
                    'status' => 'incomplete_profile',
                    'message' => 'يرجى إكمال بيانات الدبلوم أولاً.',
                    'redirect' => $redirectUrl,
                ])->setStatusCode(403);
            }
            
            return redirect()->to($redirectUrl);
        }

        // إذا كان الحساب مكتمل، التوجيه حسب نوع الحساب
        $targetUrl = match ($role) {
            AccountType::STUDENT->value => site_url('students'),
            AccountType::ADMIN->value,
            AccountType::TEACHER->value,
            AccountType::TRIAL->value => site_url('admins'),
            default => site_url('students'), // افتراضي للطلاب
        };

        // دعم AJAX requests
        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'تم التوجيه بنجاح.',
                'redirect' => $targetUrl,
            ]);
        }

        return redirect()->to($targetUrl);
    }

    public function logout()
    {
        // 🟢 مسح السيشن المحلية
        session()->remove('auth_user');
        session()->destroy();

        // 🟢 تحويل للمشروع الرئيسي (نفس منطق الطالب)
        return redirect()->to('https://www.howplatform.net/logout?redirect=' . base_url('/'));
    }


}

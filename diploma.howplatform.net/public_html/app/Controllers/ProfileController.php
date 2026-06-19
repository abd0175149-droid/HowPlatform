<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use Config\Services;  // ← لا تنسَ هذا الاستيراد


class ProfileController extends ResourceController
{
    protected $format = 'json';

    /*──────────── صفحة الويب ────────────*/
    public function index()
    {
        return view('admins/pages/account');
    }

    /*────────── API: المستخدم الحالي ──────────*/
    public function showCurrent()
    {
        $uid = session('user_id');           // تأكد أن الـ user_id محفوظ في الجلسة
        if (! $uid) {
            return $this->failUnauthorized('يجب تسجيل الدخول أولاً.');
        }

        return $this->respond($this->fetchUserData($uid));
    }

    /*────────── API: مستخدم برقم ID ──────────*/
    public function show($id = null)
    {
        if (! $id) {
            return $this->failValidationError('لم يُمرَّر مُعرّف المستخدم.');
        }

        return $this->respond($this->fetchUserData((int) $id));
    }

    /*──────────────────── Helper ────────────────────*/
    private function fetchUserData(int $id): array
    {
        $db = db_connect();

        // نجلب كل شيء من جدول users ونربط الجداول المرجعية
        $user = $db->table('users u')
            ->select("
                u.id,
                u.name,
                u.name              AS full_name,
                u.email,
                u.phone_number,
                u.gender,
                u.birth_date,
                u.is_premium,
                u.premium_until,
                u.profile_image_url,
                u.is_verified,
                u.last_login_at,
                u.last_seen_at,
                u.created_at,

                at.name             AS account_type,
                dt.name             AS diploma_type,
                un.name             AS university,
                sp.name             AS specialty,
                ay.label            AS academic_year
            ")
            ->join('account_types  at', 'at.id = u.account_type_id',  'left')
            ->join('diploma_types  dt', 'dt.id = u.diploma_type_id',  'left')
            ->join('universities   un', 'un.id = u.university_id',    'left')
            ->join('specialties    sp', 'sp.id = u.specialty_id',     'left')
            ->join('academic_years ay', 'ay.id = u.academic_year_id', 'left')
            ->where('u.id', $id)
            ->get()
            ->getRow();

        if (! $user) {
            return $this->failNotFound('المستخدم غير موجود.');
        }

        return [
            /*— أعمدة users —*/
            'id'                => $user->id,
            'name'              => $user->name,
            'full_name'         => $user->full_name,
            'email'             => $user->email,
            'phone_number'      => $user->phone_number,
            'gender'            => $user->gender,
            'birth_date'        => $user->birth_date,
            'is_premium'        => (bool) $user->is_premium,
            'premium_until'     => $user->premium_until,
            'profile_image_url' => $user->profile_image_url,
            'is_verified'       => (bool) $user->is_verified,

            /*— جداول مرجعية —*/
            'account_type'      => $user->account_type,
            'diploma_type'      => $user->diploma_type,
            'university'        => $user->university,
            'specialty'         => $user->specialty,
            'academic_year'     => $user->academic_year,

            /*— بيانات إضافية للمتابعة —*/
            'last_login_at'     => $user->last_login_at,
            'last_seen_at'      => $user->last_seen_at,
            'created_at'        => $user->created_at,
        ];
    }
    
public function uploadImage()
{
    $uid = session('user_id');
    if (! $uid) {
        return $this->failUnauthorized('يجب تسجيل الدخول.');
    }

    /** @var \CodeIgniter\HTTP\Files\UploadedFile $file */
    $file = $this->request->getFile('image');
    if (! $file || $file->hasMoved() || ! $file->isValid()) {
        return $this->failValidationError('لم يتم تحميل صورة صالحة.');
    }

    // حد أقصى للحجم: 2 ميغابايت
    if ($file->getSize() > 2 * 1024 * 1024) {
        return $this->failValidationError('حجم الصورة أكبر من 2MB.');
    }

    // التحقق من نوع الميم أنه صورة
    $mime = $file->getMimeType();
    if (strpos($mime, 'image/') !== 0) {
        return $this->failValidationError('الملف ليس صورة.');
    }

    // مسار الحفظ داخل public/uploads/profile
    $folder = FCPATH . 'uploads/profile/';
    if (! is_dir($folder)) {
        mkdir($folder, 0755, true);
    }

    // اسم جديد يعتمد على ID والتوقيت
    $newName = $uid . '_' . time() . '.png';
    // استخدام true لإعادة الكتابة إن وُجد
    $file->move($folder, $newName, true);

    $publicUrl = base_url('uploads/profile/' . $newName);

    // تحديث قاعدة البيانات
    db_connect()
        ->table('users')
        ->where('id', $uid)
        ->update(['profile_image_url' => $publicUrl]);

    return $this->respond([
        'status'             => 'success',
        'profile_image_url'  => $publicUrl,
    ]);
}

/**
 * API: تحديث بيانات المستخدم الحالي
 * PUT /api/profile/update
 */
 public function update($id = null)
    {
        // 1) ID المستخدم من الجلسة
        $uid = session('user_id');
        if (! $uid) {
            return $this->failUnauthorized('يجب تسجيل الدخول.');
        }

        // 2) جلب البيانات المرسلة
        $data = $this->request->getJSON(true);
        if (! is_array($data)) {
            return $this->failValidationError('بيانات غير صالحة.');
        }

        // 3) خريطة من مفتاح JSON إلى اسم العمود في DB
        $map = [
            'full_name'   => 'name',
            'gender'      => 'gender',
            'birth_date'  => 'birth_date',
            'email'       => 'email',
            'phone_number'=> 'phone_number',
        ];

        // 4) بناء مصفوفة التحديث
        $update = [];
        foreach ($map as $jsonKey => $dbCol) {
            if (isset($data[$jsonKey])) {
                $update[$dbCol] = $data[$jsonKey];
            }
        }
        if (empty($update)) {
            return $this->failValidationError('لا توجد بيانات للتحديث.');
        }

        // 5) قواعد التحقق
        $validation = Services::validation();
        $validation->setRules([
            'name'         => 'required|string',
            'gender'       => 'in_list[male,female]',
            'birth_date'   => 'permit_empty|valid_date[Y-m-d]',
            'email'        => "required|valid_email|is_unique[users.email,id,{$uid}]",
            'phone_number' => 'permit_empty|alpha_numeric_space'
        ]);

        if (! $validation->run($update)) {
            return $this->failValidationError($validation->getErrors());
        }

        // 6) نفّذ التحديث
        db_connect()
            ->table('users')
            ->where('id', $uid)
            ->update($update);

        // 7) أعد جلب الحقول المحدثة للإرسال
        $updated = db_connect()
            ->table('users')
            ->select(array_values($map))
            ->where('id', $uid)
            ->get()
            ->getRowArray();

        // 8) أعد Response بالمفتاح original JSON keys
        $response = ['id' => $uid];
        foreach ($map as $jsonKey => $dbCol) {
            if (isset($updated[$dbCol])) {
                $response[$jsonKey] = $updated[$dbCol];
            }
        }

        return $this->respond($response);
    }

}

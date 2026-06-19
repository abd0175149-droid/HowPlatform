<?php

namespace App\Controllers\Students;

use CodeIgniter\RESTful\ResourceController;
use Config\Services;

class StudentProfileController extends ResourceController
{
    protected $format = 'json';

 public function getProfile()
{
    $uid = session('user_id');
    if (! $uid) {
        return $this->failUnauthorized('يجب تسجيل الدخول.');
    }

    $db = db_connect();

    $user = $db->table('users u')
        ->select("
            u.id,
            u.name,
            u.email,
            u.phone_number,
            u.gender,
            u.birth_date,
            u.profile_image_url,
            u.is_premium,
            u.premium_until,

            un.name AS university,
            sp.name AS specialty,
            ay.label AS academic_year
        ")
        ->join('universities un', 'un.id = u.university_id', 'left')
        ->join('specialties sp', 'sp.id = u.specialty_id', 'left')
        ->join('academic_years ay', 'ay.id = u.academic_year_id', 'left')
        ->where('u.id', $uid)
        ->get()
        ->getRow();

    if (! $user) {
        return $this->failNotFound('لم يتم العثور على المستخدم.');
    }

    return $this->respond([
        'id'                => $user->id,
        'full_name'         => $user->name,
        'email'             => $user->email,
        'phone_number'      => $user->phone_number,
        'gender'            => $user->gender,
        'birth_date'        => $user->birth_date,
        'profile_image_url' => $user->profile_image_url,
        'is_premium'        => (bool) $user->is_premium,
        'premium_until'     => $user->premium_until,
        'university'        => $user->university,
        'specialty'         => $user->specialty,
        'academic_year'     => $user->academic_year,
    ]);
}


    public function updateProfile()
    {
        $uid = session('user_id');
        if (! $uid) {
            return $this->failUnauthorized('يجب تسجيل الدخول.');
        }

        $data = $this->request->getJSON(true);

        $map = [
            'full_name'    => 'name',
            'gender'       => 'gender',
            'birth_date'   => 'birth_date',
            'email'        => 'email',
            'phone_number' => 'phone_number',
        ];

        $update = [];
        foreach ($map as $jsonKey => $dbCol) {
            if (isset($data[$jsonKey])) {
                $update[$dbCol] = $data[$jsonKey];
            }
        }

        if (empty($update)) {
            return $this->failValidationErrors(['خطأ' => 'لم يتم إرسال بيانات للتحديث.']);
        }

        $validation = Services::validation();
        $validation->setRules([
            'name'         => 'required|string',
            'gender'       => 'in_list[male,female]',
            'birth_date'   => 'permit_empty|valid_date[Y-m-d]',
            'email'        => "required|valid_email|is_unique[users.email,id,{$uid}]",
            'phone_number' => 'permit_empty|alpha_numeric_space'
        ]);

        if (! $validation->run($update)) {
            return $this->failValidationErrors($validation->getErrors());
        }

        db_connect()
            ->table('users')
            ->where('id', $uid)
            ->update($update);

        return $this->respond([
    'full_name'    => $update['name'] ?? '',
    'gender'       => $update['gender'] ?? '',
    'birth_date'   => $update['birth_date'] ?? '',
    'email'        => $update['email'] ?? '',
    'phone_number' => $update['phone_number'] ?? ''
]);

    }
    
    public function uploadImage()
{
    $uid = session('user_id');
    if (! $uid) {
        return $this->failUnauthorized('يجب تسجيل الدخول.');
    }

    $file = $this->request->getFile('image');
    if (! $file->isValid()) {
        return $this->failValidationError('لم يتم رفع صورة صالحة.');
    }

    $ext = $file->getExtension();
    $newName = 'profile_' . $uid . '_' . time() . '.' . $ext;

    $uploadPath = FCPATH . 'uploads/profile/';
    if (! is_dir($uploadPath)) {
        mkdir($uploadPath, 0777, true);
    }

    $file->move($uploadPath, $newName);

    $url = base_url("uploads/profile/$newName");

    db_connect()->table('users')
        ->where('id', $uid)
        ->update(['profile_image_url' => $url]);

    return $this->respond([
        'status' => 'success',
        'profile_image_url' => $url
    ]);
}

}

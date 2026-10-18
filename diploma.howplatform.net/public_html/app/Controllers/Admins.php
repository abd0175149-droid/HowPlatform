<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use Config\Database;

class Admins extends Controller
{
public function index()
{
    $session = session();

    if (! $session->has('user_id') || ! in_array($session->get('role'), [1, 2])) {
    return redirect()->to(site_url('auth/login'));
}

    $uid = $session->get('user_id');
    $db  = Database::connect();
    $user = $db->table('users')
               ->select('name, gender, profile_image_url')
               ->where('id', $uid)
               ->get()
               ->getRowArray();

    // تحديد رابط الصورة الصحيح
    $profileImage = '';
    if (!empty($user['profile_image_url'])) {
        if (filter_var($user['profile_image_url'], FILTER_VALIDATE_URL)) {
            // إذا كانت قيمة كاملة لرابط صورة
            $profileImage = $user['profile_image_url'];
        } else {
            // إذا كانت مجرد اسم ملف
            $profileImage = base_url('uploads/profile/' . $user['profile_image_url']);
        }
    } else {
        // صورة افتراضية حسب الجنس
        $gender = strtolower($user['gender'] ?? '');
        $defaultFile = ($gender === 'أنثى') ? 'default_female_new.jpg' : 'default_male_new.jpg';
        $profileImage = base_url('uploads/profile/' . $defaultFile);
    }

    $data = [
        'adminName'    => $user['name'] ?? 'مشرف',
        'profileImage' => $profileImage,
    ];

    return view('admins/layout', $data);
}



    public function loadPage($page)
    {
        $allowedPages = [
            'dashboard',
            'courses',
            'weeks',
            'tasks',
            'task_type_options',
            'manage_users',
            'university_options',
            'account',
            'pending_requests',
            'service_chat',
            'notifications',
            'email_broadcast',
            'library_upload',
            'plans',
            'ocr_upload',
        ];

        if (! in_array($page, $allowedPages, true)) {
            return view('errors/html/error_404');
        }

        switch ($page) {
            case 'university_options':
                return (new \App\Controllers\UniversityOptions())->index();
            case 'task_type_options':
                return (new \App\Controllers\TaskTypeOptions())->index();
            default:
                return view("admins/pages/$page");
        }
    }

    public function stats()
    {
        $db = Database::connect();

        $monthAgo = date('Y-m-d H:i:s', strtotime('-1 month'));

        $builder  = $db->table('users');
        $active   = $builder->where('last_login_at >=', $monthAgo)->countAllResults(false);
        $inactive = $builder->where('last_login_at <', $monthAgo)
                            ->orWhere('last_login_at', null)
                            ->countAllResults();

        $builder  = $db->table('users');
        $premium  = $builder->where('is_premium', 1)->countAllResults(false);
        $normal   = $builder->where('is_premium', 0)
                            ->orWhere('is_premium', null)
                            ->countAllResults();

        $universities = $db->table('users u')
            ->select('universities.name as university, COUNT(u.id) as total')
            ->join('universities', 'universities.id = u.university_id', 'left')
            ->groupBy('u.university_id')
            ->get()
            ->getResultArray();

        return $this->response->setJSON([
            'activity'     => ['active'  => $active,   'inactive' => $inactive],
            'premium'      => ['premium' => $premium,  'normal'   => $normal],
            'universities' => $universities,
        ]);
    }
}

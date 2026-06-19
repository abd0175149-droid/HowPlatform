<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use Config\Database;

class Student extends BaseController
{
    public function index()
    {
        $session = session();

        // ✅ التأكد من تسجيل الدخول
        if (! $session->has('user_id') || $session->get('role') != 3) {
            return redirect()->to(site_url('auth/login'));
        }

        $uid = $session->get('user_id');
        $db  = Database::connect();

        // 🟢 جلب معلومات الطالب الأساسية
        $user = $db->table('users')
            ->select('name, gender, profile_image_url, university_id, academic_year_id')
            ->where('id', $uid)
            ->get()
            ->getRowArray();

        // 🔵 تجهيز صورة الملف الشخصي
        $profileImage = '';
        if (!empty($user['profile_image_url'])) {
            if (filter_var($user['profile_image_url'], FILTER_VALIDATE_URL)) {
                $profileImage = $user['profile_image_url'];
            } else {
                $profileImage = base_url('uploads/profile/' . $user['profile_image_url']);
            }
        } else {
            $gender = strtolower($user['gender'] ?? '');
            $defaultFile = ($gender === 'أنثى') ? 'default_female_new.jpg' : 'default_male_new.jpg';
            $profileImage = base_url('uploads/profile/' . $defaultFile);
        }

        $universityId    = $user['university_id'] ?? null;
        $academicYearId  = $user['academic_year_id'] ?? null;
        $semesters       = [];
        $semesterCourses = [];

        // 🔶 جلب الفصول الدراسية المرتبطة بالسنة الأكاديمية
        if ($universityId && $academicYearId) {
            $semesters = $db->table('semesters')
                ->select('id, name')
                ->where('academic_year_id', $academicYearId)
                ->orderBy('id')
                ->get()
                ->getResultArray();

            // 🟣 جلب المساقات المرتبطة بكل فصل
            $courses = $db->table('course_offerings co')
                ->select('c.id, c.name, s.id as semester_id')
                ->join('courses c', 'c.id = co.course_id')
                ->join('semesters s', 's.id = co.semester_id')
                ->where('co.university_id', $universityId)
                ->where('s.academic_year_id', $academicYearId)
                ->orderBy('s.id')
                ->get()
                ->getResultArray();

            foreach ($courses as $course) {
                $semesterCourses[$course['semester_id']][] = $course;
            }
        }

        // ✅ تمرير البيانات إلى واجهة الطالب
        return view('student/layout', [
            'studentName'       => $user['name'] ?? 'طالب',
            'profileImage'      => $profileImage,
            'semesters'         => $semesters,
            'semesterCourses'   => $semesterCourses,
        ]);
    }

    // ✅ تحديث method pages لدعم المساقات الديناميكية
    public function pages($page)
    {
        // الصفحات الثابتة المسموحة (كما هي)
        $staticPages = ['dashboard', 'account', 'service', 'welcome'];
        
        // التحقق من الصفحات الثابتة (النظام القديم)
        if (in_array($page, $staticPages)) {
            return $this->loadStaticPage($page);
        }
        
        // 🆕 التحقق من صفحات المساقات الديناميكية
        if (preg_match('/^course-(\d+)$/', $page, $matches)) {
            $courseOfferingId = $matches[1];
            return $this->loadCoursePage($courseOfferingId, $page);
        }
        
        // صفحة غير موجودة (كما هي)
        return view('errors/html/error_404');
    }

    // 🆕 تحميل الصفحات الثابتة (دالة مساعدة)
    private function loadStaticPage($page)
    {
        // التحقق من وجود ملف الصفحة
        $viewPath = APPPATH . "Views/student/pages/{$page}.php";
        if (!file_exists($viewPath)) {
            return view('errors/html/error_404');
        }

        // تمرير بيانات إضافية حسب الصفحة (اختياري)
        $data = [];
        switch ($page) {
            case 'dashboard':
                $data = $this->getDashboardData();
                break;
            case 'account':
                $data = $this->getAccountData();
                break;
            case 'service':
                $data = $this->getServiceData();
                break;
            case 'welcome':
                $data = $this->getWelcomeData();
                break;
        }

        return view("student/pages/{$page}", $data);
    }

    // 🆕 تحميل صفحة المساق
    private function loadCoursePage($courseOfferingId, $section)
    {
        $session = session();
        $userId = $session->get('user_id');
        
        if (!$userId) {
            return redirect()->to(site_url('auth/login'));
        }

        // التحقق من صلاحية الوصول للمساق
        if (!$this->canAccessCourse($userId, $courseOfferingId)) {
            return view('errors/html/error_403', [
                'message' => 'ليس لديك صلاحية للوصول إلى هذا المساق'
            ]);
        }

        // تمرير بيانات المساق
        $data = [
            'course_offering_id' => $courseOfferingId,
            'section' => $section,
            'user_id' => $userId
        ];

          return view('student/pages/course_content', $data);
    }

    // 🆕 التحقق من صلاحية الوصول للمساق
    private function canAccessCourse($userId, $courseOfferingId)
    {
        $db = Database::connect();
        
        $query = "
        SELECT 1
        FROM users u
        JOIN semesters s ON s.academic_year_id = u.academic_year_id
        JOIN course_offerings co ON co.semester_id = s.id AND co.university_id = u.university_id
        WHERE u.id = ? AND co.id = ?
        LIMIT 1
        ";
        
        $result = $db->query($query, [$userId, $courseOfferingId])->getRowArray();
        return !empty($result);
    }

    // 🆕 جلب بيانات لوحة التحكم (اختياري)
    private function getDashboardData()
    {
        $session = session();
        $userId = $session->get('user_id');
        if (!$userId) return [];

        $db = Database::connect();
        
        // جلب إحصائيات سريعة
        $stats = $this->getUserStats($userId);
        
        return ['stats' => $stats];
    }

    // 🆕 جلب بيانات الحساب الشخصي (اختياري)
    private function getAccountData()
    {
        $session = session();
        $userId = $session->get('user_id');
        if (!$userId) return [];

        $db = Database::connect();
        
        // جلب بيانات المستخدم المفصلة
        $user = $db->table('users u')
            ->select('u.*, uni.name as university_name')
            ->join('universities uni', 'uni.id = u.university_id', 'left')
            ->where('u.id', $userId)
            ->get()
            ->getRowArray();

        return ['user' => $user];
    }

    // 🆕 جلب بيانات صفحة الخدمة (اختياري)
    private function getServiceData()
    {
        $session = session();
        $userId = $session->get('user_id');
        if (!$userId) return [];

        $db = Database::connect();
        
        // جلب إحصائيات طلبات الخدمة
        $serviceStats = $db->query("
            SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_requests
            FROM service_requests 
            WHERE user_id = ?
        ", [$userId])->getRowArray();

        return ['service_stats' => $serviceStats ?: []];
    }

    // 🆕 جلب بيانات الصفحة الترحيبية (اختياري)
    private function getWelcomeData()
    {
        $session = session();
        $userId = $session->get('user_id');
        if (!$userId) return [];

        $db = Database::connect();
        
        // جلب بيانات المستخدم الأساسية
        $user = $db->table('users')
            ->select('name, is_complete, created_at')
            ->where('id', $userId)
            ->get()
            ->getRowArray();

        return ['user' => $user];
    }

    // 🆕 جلب إحصائيات المستخدم
    private function getUserStats($userId)
    {
        $db = Database::connect();
        
        $query = "
        WITH user_courses AS (
            SELECT co.id as course_offering_id
            FROM users u
            JOIN semesters s ON s.academic_year_id = u.academic_year_id
            JOIN course_offerings co ON co.semester_id = s.id AND co.university_id = u.university_id
            WHERE u.id = ?
        )
        SELECT 
            COUNT(DISTINCT uc.course_offering_id) as total_courses,
            COUNT(DISTINCT wc.id) as total_weeks,
            COUNT(DISTINCT t.id) as total_tasks
        FROM user_courses uc
        LEFT JOIN weekly_contents wc ON wc.course_offering_id = uc.course_offering_id
        LEFT JOIN tasks t ON t.weekly_content_id = wc.id
        ";

        $result = $db->query($query, [$userId])->getRowArray();
        
        return $result ?: [
            'total_courses' => 0,
            'total_weeks' => 0,
            'total_tasks' => 0
        ];
    }

    // 🆕 API: جلب قائمة الصفحات المتاحة
    public function getAvailablePages()
    {
        $session = session();
        $userId = $session->get('user_id');
        
        if (!$userId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'User not authenticated'
            ]);
        }

        // الصفحات الثابتة
        $staticPages = [
            'welcome' => 'الصفحة الترحيبية',
            'dashboard' => 'لوحة التحكم',
            'service' => 'طلبات الخدمة',
            'account' => 'الحساب الشخصي'
        ];

        // المساقات المتاحة
        $courses = $this->getAvailableCourses($userId);

        return $this->response->setJSON([
            'success' => true,
            'data' => [
                'static_pages' => $staticPages,
                'courses' => $courses
            ]
        ]);
    }

    // 🆕 جلب المساقات المتاحة للمستخدم
    private function getAvailableCourses($userId)
    {
        $db = Database::connect();
        
        $query = "
        SELECT 
            co.id as course_offering_id,
            c.name as course_name,
            c.code as course_code,
            s.name as semester_name
        FROM users u
        JOIN semesters s ON s.academic_year_id = u.academic_year_id
        JOIN course_offerings co ON co.semester_id = s.id AND co.university_id = u.university_id
        JOIN courses c ON c.id = co.course_id
        WHERE u.id = ?
        ORDER BY s.id, c.name
        ";

        return $db->query($query, [$userId])->getResultArray();
    }

    // 🆕 API: التحقق من صحة القسم
    public function validateSection($section)
    {
        $session = session();
        $userId = $session->get('user_id');
        
        if (!$userId) {
            return $this->response->setJSON(['valid' => false]);
        }

        // فحص الصفحات الثابتة
        $staticPages = ['dashboard', 'account', 'service', 'welcome'];
        if (in_array($section, $staticPages)) {
            return $this->response->setJSON(['valid' => true, 'type' => 'static']);
        }

        // فحص المساقات
        if (preg_match('/^course-(\d+)$/', $section, $matches)) {
            $courseOfferingId = $matches[1];
            $valid = $this->canAccessCourse($userId, $courseOfferingId);
            return $this->response->setJSON([
                'valid' => $valid,
                'type' => 'course',
                'course_offering_id' => $courseOfferingId
            ]);
        }

        return $this->response->setJSON(['valid' => false]);
    }
}
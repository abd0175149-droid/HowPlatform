<?php

declare(strict_types=1);

namespace App\Controllers\Admins;

use App\Controllers\BaseController;
use App\Helpers\StudentViewHelper;
use CodeIgniter\API\ResponseTrait;
use Config\Database;

/**
 * StudentViewController
 * 
 * Controller لإدارة استعراض الأدمن لواجهة الطالب
 */
class StudentViewController extends BaseController
{
    use ResponseTrait;

    /**
     * التحقق من أن المستخدم أدمن
     */
    private function checkAdminAccess(): bool
    {
        $authUser = session('auth_user') ?? [];
        $role = (int)($authUser['role'] ?? 0);
        return in_array($role, [1, 2], true);
    }

    /**
     * جلب الإعدادات الحالية
     * GET /admins/api/student-view/settings
     */
    public function settings()
    {
        if (!$this->checkAdminAccess()) {
            return $this->failUnauthorized('غير مصرح لك بالوصول');
        }

        $prefs = StudentViewHelper::getStudentViewPreferences();
        
        return $this->respond([
            'success' => true,
            'data' => $prefs ?? [
                'university_id' => null,
                'specialty_id' => null,
                'academic_year_id' => null,
                'diploma_type_id' => null,
            ],
        ]);
    }

    /**
     * حفظ الإعدادات في session
     * POST /admins/api/student-view/settings
     */
    public function saveSettings()
    {
        if (!$this->checkAdminAccess()) {
            return $this->failUnauthorized('غير مصرح لك بالوصول');
        }

        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        // التحقق من البيانات
        $universityId = isset($data['university_id']) ? (int)$data['university_id'] : null;
        $specialtyId = isset($data['specialty_id']) ? (int)$data['specialty_id'] : null;
        $academicYearId = isset($data['academic_year_id']) ? (int)$data['academic_year_id'] : null;
        $diplomaTypeId = isset($data['diploma_type_id']) ? (int)$data['diploma_type_id'] : null;

        // التحقق من صحة البيانات (اختياري - يمكن إزالة هذا إذا كان اختياري)
        $db = Database::connect();
        
        if ($universityId) {
            $uniExists = $db->table('universities')->where('id', $universityId)->countAllResults() > 0;
            if (!$uniExists) {
                return $this->failValidationErrors('الجامعة المحددة غير موجودة');
            }
        }

        if ($specialtyId) {
            $specExists = $db->table('specialties')->where('id', $specialtyId)->countAllResults() > 0;
            if (!$specExists) {
                return $this->failValidationErrors('التخصص المحدد غير موجود');
            }
        }

        if ($academicYearId) {
            $yearExists = $db->table('academic_years')->where('id', $academicYearId)->countAllResults() > 0;
            if (!$yearExists) {
                return $this->failValidationErrors('السنة الأكاديمية المحددة غير موجودة');
            }
        }

        if ($diplomaTypeId) {
            $diplomaExists = $db->table('diploma_types')->where('id', $diplomaTypeId)->countAllResults() > 0;
            if (!$diplomaExists) {
                return $this->failValidationErrors('نوع الدبلوم المحدد غير موجود');
            }
        }

        // حفظ في session
        $session = session();
        $session->set('admin_student_preferences', [
            'university_id'   => $universityId,
            'specialty_id'    => $specialtyId,
            'academic_year_id'=> $academicYearId,
            'diploma_type_id' => $diplomaTypeId,
        ]);

        // 🔑 توليد session ID جديد لضمان الكتابة الفورية
        $session->regenerate(false);

        return $this->respond([
            'success' => true,
            'message' => 'تم حفظ الإعدادات بنجاح',
            'data' => [
                'university_id'   => $universityId,
                'specialty_id'    => $specialtyId,
                'academic_year_id'=> $academicYearId,
                'diploma_type_id' => $diplomaTypeId,
            ],
        ]);
    }

    /**
     * بدء الاستعراض
     * POST /admins/api/student-view/start
     */
    public function start()
    {
        if (!$this->checkAdminAccess()) {
            return $this->failUnauthorized('غير مصرح لك بالوصول');
        }

        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        // حفظ الإعدادات أولاً
        $universityId = isset($data['university_id']) ? (int)$data['university_id'] : null;
        $specialtyId = isset($data['specialty_id']) ? (int)$data['specialty_id'] : null;
        $academicYearId = isset($data['academic_year_id']) ? (int)$data['academic_year_id'] : null;
        $diplomaTypeId = isset($data['diploma_type_id']) ? (int)$data['diploma_type_id'] : null;

        // التحقق من وجود البيانات الأساسية
        if (!$universityId || !$academicYearId) {
            return $this->failValidationErrors('الرجاء تحديد الجامعة والسنة الأكاديمية على الأقل');
        }

        $session = session();
        $authUser = session('auth_user') ?? [];
        $adminId = (int)($authUser['id'] ?? 0);
        $adminRole = (int)($authUser['role'] ?? 0);

        // حفظ حالة الاستعراض
        $session->set('admin_viewing_as_student', true);
        $session->set('original_admin_id', $adminId);
        $session->set('original_admin_role', $adminRole);
        $session->set('admin_student_preferences', [
            'university_id'   => $universityId,
            'specialty_id'    => $specialtyId,
            'academic_year_id'=> $academicYearId,
            'diploma_type_id' => $diplomaTypeId,
        ]);

        // 🔑 توليد session ID جديد لضمان كتابة القيم الجديدة فوراً
        // false = الاحتفاظ بالبيانات القديمة + الجديدة (لا حذف)
        $session->regenerate(false);

        return $this->respond([
            'success' => true,
            'message' => 'تم بدء الاستعراض بنجاح',
            'redirect' => site_url('students/'),
        ]);
    }

    /**
     * إنهاء الاستعراض
     * POST /admins/api/student-view/stop
     */
    public function stop()
    {
        if (!$this->checkAdminAccess()) {
            return $this->failUnauthorized('غير مصرح لك بالوصول');
        }

        $session = session();
        
        // تنظيف session
        $session->remove('admin_viewing_as_student');
        $session->remove('original_admin_id');
        $session->remove('original_admin_role');
        $session->remove('admin_student_preferences');

        // 🔑 توليد session ID جديد بعد إنهاء الاستعراض
        $session->regenerate(false);

        return $this->respond([
            'success' => true,
            'message' => 'تم إنهاء الاستعراض بنجاح',
            'redirect' => site_url('admins/'),
        ]);
    }

    /**
     * جلب خيارات القوائم المنسدلة
     * GET /admins/api/student-view/options
     */
    public function options()
    {
        if (!$this->checkAdminAccess()) {
            return $this->failUnauthorized('غير مصرح لك بالوصول');
        }

        $db = Database::connect();

        // جلب جميع أنواع الدبلوم
        $diplomaTypes = $db->table('diploma_types')
            ->select('id, name')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        // جلب جميع الجامعات
        $universities = $db->table('universities')
            ->select('id, name')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        // جلب جميع التخصصات
        $specialties = $db->table('specialties')
            ->select('id, name')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        // جلب جميع السنوات الأكاديمية
        $academicYears = $db->table('academic_years')
            ->select('id, label as name')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        // جلب العلاقات للفلترة الديناميكية
        $universityDiplomaTypes = $db->table('university_diploma_types')
            ->select('diploma_type_id, university_id')
            ->get()
            ->getResultArray();

        $universitySpecialties = $db->table('university_specialties')
            ->select('university_id, specialty_id')
            ->get()
            ->getResultArray();

        $universityAcademicYears = $db->table('university_academic_years')
            ->select('university_id, academic_year_id')
            ->get()
            ->getResultArray();

        return $this->respond([
            'success' => true,
            'data' => [
                'diploma_types' => $diplomaTypes,
                'universities' => $universities,
                'specialties' => $specialties,
                'academic_years' => $academicYears,
                'university_diploma_types' => $universityDiplomaTypes,
                'university_specialties' => $universitySpecialties,
                'university_academic_years' => $universityAcademicYears,
            ],
        ]);
    }
}


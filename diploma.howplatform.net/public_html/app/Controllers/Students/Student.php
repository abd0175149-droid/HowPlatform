<?php

declare(strict_types=1);

namespace App\Controllers\Students;

use App\Controllers\BaseController;
use App\Helpers\StudentViewHelper;
use Config\Database;

class Student extends BaseController
{
    /* ---------------------------- Utils: no-store JSON ---------------------------- */
    private function jsonNoStore(array $data)
    {
        return $this->response
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache')
            ->setJSON($data);
    }

    /* ===================== NEW: Service unread utilities ===================== */

    /**
     * فحص وجود جدول في قاعدة البيانات.
     */
    private function tableExists(string $table): bool
    {
        try {
            $db     = Database::connect();
            $dbName = $db->getDatabase();
            $like   = $db->escape($table);
            $q = $db->query("SHOW TABLES FROM `{$dbName}` LIKE {$like}");
            return (bool) $q->getResult();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * هل منطق القراءات مُفعّل؟
     */
    private function readsEnabled(): bool
    {
        return $this->tableExists('service_message_reads');
    }

    /**
     * يحسب إجمالي الرسائل غير المقروءة في طلبات الخدمة للطالب.
     * المنطق:
     *  - الرسائل الواردة فقط (ليست مرسلة من الطالب نفسه)
     *  - غير موجود لها سجل قراءة في service_message_reads لنفس الطالب (عند توفر الجدول)
     * إن لم يتوفر جدول القراءات نرجع 0 لتفادي الالتباس.
     */
    private function countServiceUnreadForUser(int $userId): int
    {
        if ($userId <= 0) return 0;

        if (!$this->readsEnabled()) {
            return 0;
        }

        $db = Database::connect();
        $sql = "
            SELECT COUNT(*) AS c
            FROM service_messages sm
            JOIN service_requests sr
                 ON sr.id = sm.service_request_id
            LEFT JOIN service_message_reads r
                 ON r.message_id   = sm.id
                AND r.reader_user_id = ?
            WHERE sr.user_id = ?
              AND sm.sender_user_id <> ?
              AND r.message_id IS NULL
        ";
        $row = $db->query($sql, [$userId, $userId, $userId])->getRowArray();
        return (int) ($row['c'] ?? 0);
    }

    /**
     * (API للواجهة) يرجّع إجمالي الرسائل غير المقروءة للبادجات (بولينغ).
     * GET /students/service-unread-total
     */
    public function serviceUnreadTotal()
    {
        $authUser = session('auth_user') ?? [];
        $uid      = (int)($authUser['id'] ?? 0);
        if ($uid <= 0) {
            return $this->jsonNoStore(['success' => false, 'message' => 'unauthorized'])->setStatusCode(401);
        }
        $total = $this->countServiceUnreadForUser($uid);
        return $this->jsonNoStore(['success' => true, 'data' => ['total_unread' => $total]]);
    }

    /**
     * التحقق من وجود عمود في جدول
     */
    private function columnExists(string $table, string $column): bool
    {
        try {
            $db = Database::connect();
            $dbName = $db->getDatabase();
            
            $query = $db->query("
                SELECT COUNT(*) as count
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?
            ", [$dbName, $table, $column]);
            
            $result = $query->getRowArray();
            return (int)($result['count'] ?? 0) > 0;
        } catch (\Throwable $e) {
            log_message('error', 'Column exists check error: ' . $e->getMessage());
            return false;
        }
    }

    /* ----------------------------- Debug helper ----------------------------- */
    public function debugSession()
    {
        $sessionData = session()->get();

        return $this->response->setJSON([
            'success' => true,
            'session' => $sessionData,
        ]);
    }

    /* ---------------------------------- index ----------------------------------- */
    public function index()
    {
        $session  = session();

        // 1) مسجل دخول؟
        $authUser = $session->get('auth_user') ?? [];
        $uid      = (int)($authUser['id'] ?? 0);
        if ($uid <= 0) {
            return redirect()->to(
                'https://auth.howplatform.net/login?redirect=' . urlencode(current_url())
            );
        }

        $db = Database::connect();

        // 2) اكتمال ملف الدبلوم + التخصص
        // إذا كان الأدمن في وضع الاستعراض، نتخطى التحقق من اكتمال الملف
        $isAdminViewing = StudentViewHelper::isAdminViewingAsStudent();
        
        if (!$isAdminViewing) {
            $du = $db->table('diploma_users')
                ->select('user_id, university_id, academic_year_id, specialty_id, gender, is_complete, terms_accepted')
                ->where('user_id', $uid)
                ->get()
                ->getRowArray();
                
            // 2.1) التحقق من الموافقة على الشروط والأحكام
            if (!empty($du)) {
                // التحقق من وجود الحقل أولاً
                $hasTermsColumn = $this->columnExists('diploma_users', 'terms_accepted');
                
                if ($hasTermsColumn) {
                    $termsAccepted = (int)($du['terms_accepted'] ?? 0);
                    
                    // إذا لم يوافق بعد، نسمح بتحميل الصفحة لكن JavaScript سيعرض المودال
                    // لا نحظر الوصول هنا لأن المودال يحتاج تحميل الصفحة أولاً
                }
            }
                
            $isComplete = (int)($du['is_complete'] ?? 0);
            if ($isComplete !== 1) {
                $redirectUrl = 'https://auth.howplatform.net/profile'
                    . '?active=diploma'
                    . '&reason=incomplete'
                    . '&redirect=' . urlencode(current_url()); 
            
                $accept = $this->request->getHeaderLine('Accept') ?: 'text/html';
                if ($this->request->isAJAX() || !str_contains($accept, 'text/html')) {
                    return $this->response->setJSON([
                        'status'   => 'incomplete_profile',
                        'message'  => 'يرجى إكمال بيانات الدبلوم أولاً.',
                        'redirect' => $redirectUrl,
                    ])->setStatusCode(403);
                }
            
                return redirect()->to($redirectUrl);
            }
        }

        // 3) صلاحية الدور
        $role = (int)($authUser['role'] ?? 0);
        
        // السماح للأدمن في وضع الاستعراض
        $isAdminViewing = StudentViewHelper::isAdminViewingAsStudent();
        if ($role !== 3 && !$isAdminViewing) {
            return redirect()->to(base_url('students'));
        }

        // الاسم
        $studentName = $authUser['name']
            ?? ($db->table('users')->select('name')->where('id', $uid)->get()->getRow('name') ?? 'طالب');

        // الصورة
        if (!empty($authUser['avatar'])) {
            $profileImage = $authUser['avatar'];
        } else {
            $gender = strtolower($du['gender'] ?? '');  // $du معرّف فقط للطلاب الحقيقيين
            $defaultFile  = 'avatar.png';
            $profileImage = base_url('uploads/profile/' . $defaultFile);
        }

        // الفصول والمساقات - استخدام الإعدادات الفعالة
        $universityId    = StudentViewHelper::getEffectiveUniversityId();
        $academicYearId  = StudentViewHelper::getEffectiveAcademicYearId();
        $selectedSpecialtyId = (int)(StudentViewHelper::getEffectiveSpecialtyId() ?? 0);
        
        $semesters       = [];
        $semesterCourses = [];

        if ($universityId && $academicYearId) {
            $semesters = $db->table('semesters')
                ->select('id, name')
                ->where('academic_year_id', $academicYearId)
                ->orderBy('id')
                ->get()
                ->getResultArray();

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
                $semesterCourses[(int)$course['semester_id']][] = $course;
            }
        }

        /* ===== NEW: احسب إجمالي غير المقروء لطلبات الخدمة لبادج السايدبار ===== */
        $unreadServiceRequests = $this->countServiceUnreadForUser($uid);

        return view('students/layout', [
            'studentName'             => $studentName,
            'profileImage'            => $profileImage,
            'semesters'               => $semesters,
            'semesterCourses'         => $semesterCourses,
            'selectedSpecialtyId'     => $selectedSpecialtyId,
            'unreadServiceRequests'   => $unreadServiceRequests, // ← للبادج في السايدبار
        ]);
    }

    /* ---------------------------------- Pages ----------------------------------- */
    public function pages($page)
    {
        // صفحات ثابتة مدعومة
        $staticPages = ['dashboard', 'account', 'service', 'welcome', 'notifications', 'upgrade', 'rewards', 'rag-debug', 'tasks', ];

        // ✅ دعم المسار الذي يطلبه الراوتر لصفحة المساق HTML
        if ($page === 'course_content' || $page === 'course-content') {
            return view('students/pages/course_content');
        }

        // ✅ صفحة فَطين الكاملة (قسم SPA يستضيف نافذة فَطين)
        if ($page === 'fateen-chat' || $page === 'fateen_chat') {
            return view('students/pages/fateen_chat');
        }

        if (in_array($page, $staticPages, true)) {
            return $this->loadStaticPage($page);
        }

        // /students/pages/course-<id> (لو تم استدعاؤها مباشرة)
        if (preg_match('/^course-(\d+)$/', $page, $matches)) {
            $courseOfferingId = $matches[1];
            return $this->loadCoursePage($courseOfferingId, $page);
        }

        return view('errors/html/error_404');
    }

    private function loadStaticPage($page)
    {
        $viewPath = APPPATH . "Views/students/pages/{$page}.php";
        if (!file_exists($viewPath)) {
            return view('errors/html/error_404');
        }

        $data = [];
        switch ($page) {
            case 'dashboard':     $data = $this->getDashboardData(); break;
            case 'account':       $data = $this->getAccountData();   break;
            case 'service':       $data = $this->getServiceData();   break;
            case 'welcome':       $data = $this->getWelcomeData();   break;
            case 'notifications': $data = [];                        break;
            case 'upgrade':       $data = [];                        break;
            case 'rewards':       $data = $this->getRewardsData();   break;
        }

        return view("students/pages/{$page}", $data);
    }

    private function loadCoursePage($courseOfferingId, $section)
    {
        $authUser = session('auth_user') ?? [];
        $uid      = (int)($authUser['id'] ?? 0);

        if ($uid <= 0) {
            return redirect()->to('https://auth.howplatform.net/login?redirect=' . current_url());
        }

        if (!$this->canAccessCourse($uid, (int)$courseOfferingId)) {
            return view('errors/html/error_403', [
                'message' => 'ليس لديك صلاحية للوصول إلى هذا المساق'
            ]);
        }

        // نعرض نفس الـ View الموحد للمحتوى
        return view('students/pages/course_content', [
            'course_offering_id' => (int)$courseOfferingId,
            'section'            => $section,
            'user_id'            => $uid,
        ]);
    }

    /* --------------------------- Course Access (fixed) --------------------------- */
    private function canAccessCourse($userId, $courseOfferingId)
    {
        $db = Database::connect();
        // ✅ الاعتماد على diploma_users بدل users
        $sql = "
            SELECT 1
            FROM diploma_users du
            JOIN semesters s         ON s.academic_year_id = du.academic_year_id
            JOIN course_offerings co ON co.semester_id = s.id AND co.university_id = du.university_id
            WHERE du.user_id = ? AND co.id = ?
            LIMIT 1
        ";
        return (bool)$db->query($sql, [(int)$userId, (int)$courseOfferingId])->getRowArray();
    }

    /* --------------------------------- Data ------------------------------------- */
    private function getDashboardData()
    {
        $authUser = session('auth_user') ?? [];
        $uid      = (int)($authUser['id'] ?? 0);
        if ($uid <= 0) return [];

        return [
            'stats'    => $this->getUserStats($uid),
            // الأدوات التعليمية تُجلب ديناميكيًا من منصّة الأدوات (مع كاش) بدل الترميز اليدوي
            'eduTools' => \App\Libraries\ToolsCatalog::fetch(),
        ];
    }

    private function getAccountData()
    {
        $authUser = session('auth_user') ?? [];
        $uid      = (int)($authUser['id'] ?? 0);
        if ($uid <= 0) return [];

        $db = Database::connect();
        $user = $db->table('users u')
            ->select('u.*, uni.name as university_name')
            ->join('universities uni', 'uni.id = u.university_id', 'left')
            ->where('u.id', $uid)
            ->get()
            ->getRowArray();

        // مستخدمو SSO (user_id عالمي بلا صفّ users محلّي): ابنِ من diploma_users + auth_user.
        if (! $user) {
            $du = $db->table('diploma_users du')
                ->select('du.*, uni.name as university_name')
                ->join('universities uni', 'uni.id = du.university_id', 'left')
                ->where('du.user_id', $uid)
                ->get()
                ->getRowArray();
            if ($du) {
                $du['id']    = $uid;
                $du['name']  = $authUser['name'] ?? '';
                $du['email'] = $authUser['email'] ?? '';
                $user = $du;
            }
        }

        return ['user' => $user];
    }

    private function getServiceData()
    {
        $authUser = session('auth_user') ?? [];
        $uid      = (int)($authUser['id'] ?? 0);
        if ($uid <= 0) return [];

        $db = Database::connect();
        $stats = $db->query("
            SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_requests
            FROM service_requests WHERE user_id = ?
        ", [$uid])->getRowArray();

        return ['service_stats' => $stats ?: []];
    }

    private function getWelcomeData()
    {
        $authUser = session('auth_user') ?? [];
        $uid      = (int)($authUser['id'] ?? 0);
        if ($uid <= 0) return [];

        $db = Database::connect();
        $user = $db->table('users')
            ->select('name, is_complete, created_at')
            ->where('id', $uid)
            ->get()
            ->getRowArray();

        // مستخدمو SSO: الاسم من auth_user، is_complete/created_at من diploma_users.
        if (! $user) {
            $du = $db->table('diploma_users')
                ->select('is_complete, created_at')
                ->where('user_id', $uid)
                ->get()
                ->getRowArray();
            $user = [
                'name'        => $authUser['name'] ?? '',
                'is_complete' => $du['is_complete'] ?? 0,
                'created_at'  => $du['created_at'] ?? null,
            ];
        }

        return ['user' => $user];
    }

    private function getRewardsData()
    {
        $authUser = session('auth_user') ?? [];
        $uid      = (int)($authUser['id'] ?? 0);
        $userEmail = (string)($authUser['email'] ?? '');
        
        if ($uid <= 0) return [];

        // التحقق من تفعيل الميزة
        $featureGate = new \App\Libraries\FeatureGate();
        if (!$featureGate->isEnabled('rewards_system', $uid, $userEmail)) {
            return ['unavailable' => true];
        }

        $rewardsService = new \App\Services\RewardsService();
        $referralSystem = new \App\Libraries\ReferralSystem();
        $db = Database::connect();

        $stats = $rewardsService->getUserStats($uid);
        $points = $rewardsService->getUserPoints($uid);
        $referralCode = $referralSystem->getOrCreateReferralCode($uid);
        $referralStats = $referralSystem->getUserReferralStats($uid);
        
        // جلب بيانات الدعوات مع أسماء المستخدمين
        $referralsModel = model(\App\Models\RewardsReferralModel::class);
        $referrals = $referralsModel->getUserReferrals($uid);
        
        // جلب أسماء المستخدمين - نستخدم اتصال مباشر بقاعدة auth
        $referralsWithNames = [];
        foreach ($referrals as $referral) {
            $referredUserId = (int)$referral['referred_user_id'];
            
            // محاولة جلب اسم المستخدم من قاعدة auth
            // نستخدم اتصال 'remote' لقاعدة auth
            $authUser = null;
            try {
                $authDb = Database::connect('remote');
                $authUser = $authDb->query("
                    SELECT name, email 
                    FROM users 
                    WHERE id = ?
                ", [$referredUserId])->getRowArray();
            } catch (\Exception $e) {
                // إذا فشل، نستخدم الاسم من session أو نعرض معرف المستخدم
                log_message('debug', 'Could not fetch user name for ID: ' . $referredUserId . ' - ' . $e->getMessage());
            }
            
            $referralsWithNames[] = [
                'id' => $referral['id'],
                'referred_user_id' => $referredUserId,
                'status' => $referral['status'],
                'name' => $authUser['name'] ?? 'مستخدم #' . $referredUserId,
                'email' => $authUser['email'] ?? '',
                'created_at' => $referral['created_at'],
                'completed_at' => $referral['completed_at'] ?? null,
                'rewarded_at' => $referral['rewarded_at'] ?? null,
            ];
        }
        
        // جلب streak data (0–5 فقط: 5 أيام ثم سلسلة جديدة)
        $consecutiveDaysModel = model(\App\Models\RewardsConsecutiveDaysModel::class);
        $streakRecord = $consecutiveDaysModel->getOrCreate($uid);
        $currentStreak = (int)($streakRecord['current_streak'] ?? 0);
        $currentStreak = max(0, min(5, $currentStreak));

        return [
            'userId'         => $uid,
            'points'         => $points,
            'stats'          => $stats,
            'referralCode'   => $referralCode,
            'referralStats' => $referralStats,
            'referrals'      => $referralsWithNames,
            'currentStreak'  => $currentStreak,
            'isPublic'       => $featureGate->isPublic('rewards_system'),
        ];
    }

    /**
     * ✅ إصلاح باطني للداشبورد:
     * - استبدال الاعتماد على users بـ diploma_users
     * - تصحيح الربط على weekly_contents
     * - عدّ الأسابيع والمهام ضمن المساقات المتاحة للطالب
     */
    private function getUserStats(int $userId): array
    {
        try {
            $db = Database::connect();

            $sql = "
                WITH user_courses AS (
                    SELECT co.id AS course_offering_id
                    FROM diploma_users du
                    JOIN semesters s
                        ON s.academic_year_id = du.academic_year_id
                    JOIN course_offerings co
                        ON co.semester_id = s.id
                       AND co.university_id = du.university_id
                    WHERE du.user_id = ?
                )
                SELECT 
                    COUNT(DISTINCT uc.course_offering_id) AS total_courses,
                    COUNT(DISTINCT wc.id)                 AS total_weeks,
                    COUNT(DISTINCT t.id)                  AS total_tasks
                FROM user_courses uc
                LEFT JOIN weekly_contents wc
                    ON wc.course_offering_id = uc.course_offering_id
                LEFT JOIN tasks t
                    ON t.weekly_content_id = wc.id
            ";

            $row = $db->query($sql, [$userId])->getRowArray();

            return $row ?: [
                'total_courses' => 0,
                'total_weeks'   => 0,
                'total_tasks'   => 0,
            ];
        } catch (\Throwable $e) {
            // في حال أي خطأ غير متوقع، لا نُسقط الصفحة
            log_message('error', 'getUserStats error: ' . $e->getMessage());
            return [
                'total_courses' => 0,
                'total_weeks'   => 0,
                'total_tasks'   => 0,
            ];
        }
    }

    /* --------------------------- Available Pages API ---------------------------- */
    public function getAvailablePages()
    {
        $authUser = session('auth_user') ?? [];
        $uid      = (int)($authUser['id'] ?? 0);
        if ($uid <= 0) {
            return $this->jsonNoStore(['success' => false, 'message' => 'User not authenticated']);
        }

        $staticPages = [
            'welcome'   => 'الصفحة الترحيبية',
            'dashboard' => 'لوحة التحكم',
            'service'   => 'طلبات الخدمة',
            'account'   => 'الحساب الشخصي',
            'notifications' => 'الإشعارات',
            'upgrade'   => 'الترقية',
            'rewards'   => 'المكافآت',
        ];

        $courses = $this->getAvailableCourses($uid);

        return $this->jsonNoStore([
            'success' => true,
            'data'    => ['static_pages' => $staticPages, 'courses' => $courses],
        ]);
    }

    private function getAvailableCourses($userId)
    {
        $db = Database::connect();
        // ✅ على diploma_users
        $sql = "
        SELECT 
            co.id  AS course_offering_id,
            c.name AS course_name,
            c.code AS course_code,
            s.name AS semester_name
        FROM diploma_users du
        JOIN semesters s         ON s.academic_year_id = du.academic_year_id
        JOIN course_offerings co ON co.semester_id = s.id AND co.university_id = du.university_id
        JOIN courses c           ON c.id = co.course_id
        WHERE du.user_id = ?
        ORDER BY s.id, c.name
        ";
        return $db->query($sql, [$userId])->getResultArray();
    }

    /* ---------------------------- Validation API ------------------------------ */
    public function validateSection($section)
    {
        // 🔒 تحقّق الجلسة
        $authUser = session('auth_user') ?? [];
        $uid      = (int) ($authUser['id'] ?? 0);
        if ($uid <= 0) {
            // لا نخزّن هذه الاستجابة في الكاش
            return $this->jsonNoStore(['valid' => false, 'reason' => 'unauthorized']);
        }

        // ✅ صفحات ثابتة (HTML فقط) — لاحظ أننا أخرجنا 'service' من هذه القائمة
        $staticPages = ['dashboard', 'account', 'welcome', 'notifications', 'upgrade', 'rewards', 'task-details', 'task_details', 'rag-debug', 'tasks'];
        if (in_array($section, $staticPages, true)) {
            return $this->jsonNoStore(['valid' => true, 'type' => 'static']);
        }

        // ✅ صفحة "service" ديناميكية: حمّل الـ HTML ثم استورد الموديول JS واعمل mount
        if ($section === 'service') {
            return $this->jsonNoStore([
                'valid'  => true,
                'type'   => 'dynamic',   // مهم ليعرف الراوتر أنها تحتاج استيراد موديول
                'module' => 'service',   // مساعدة للراوتر لاختيار /public/assets/js/students/service.js
            ]);
        }

        // ✅ صفحة فَطين الكاملة ديناميكية (تنقل نافذة فَطين إلى منطقة المحتوى)
        if ($section === 'fateen-chat') {
            return $this->jsonNoStore([
                'valid'  => true,
                'type'   => 'dynamic',
                'module' => 'fateen-chat',
            ]);
        }

        // ✅ صفحات المساقات بصيغة course-<offeringId>
        if (preg_match('/^course-(\d+)$/', (string) $section, $matches)) {
            $courseOfferingId = (int) $matches[1];
            $valid = $this->canAccessCourse($uid, $courseOfferingId);
            return $this->jsonNoStore([
                'valid'              => $valid,
                'type'               => $valid ? 'course' : null,
                'course_offering_id' => $courseOfferingId,
                'reason'             => $valid ? null : 'forbidden',
            ]);
        }

        // ❌ غير معروف
        return $this->jsonNoStore(['valid' => false, 'reason' => 'unknown']);
    }
}

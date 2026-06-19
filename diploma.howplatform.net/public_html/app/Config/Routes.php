<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ───────────────────── أساسيات عامة ─────────────────────
$routes->get('health/session', 'Health::session'); // بدون CSRF
$routes->get('sitemap.xml', 'Sitemap::index');
$routes->get('ads.txt', 'AdsController::index');

$routes->get('run-inquiry-tools-seeder-v2', function() {
    $k = (string) (getenv('CRON_SECRET') ?: '');
    if ($k === '' || ($_GET['key'] ?? '') !== $k) { return service('response')->setStatusCode(403)->setBody('Forbidden'); }
    $seeder = \Config\Database::seeder();
    $seeder->call('InquiryToolsSeeder');
    return 'تم تحديث أدوات البحث الاستقصائي (لتحميل كملفات وورد) بنجاح! يمكنك إزالة الرابط الآن.';
});
$routes->get('setup-tool', 'Test\ToolSetup::run');// صفحة بحث بسيطة لتمكين Sitelinks Search Box
$routes->get('setup-stakeholder-tool', 'Test\ToolSetupStakeholder::run');
$routes->get('search', 'Search::index');
// راوتر لفَطين
$routes->group('FateenAi', ['namespace' => 'App\Controllers'], function($routes) {
    $routes->get('usage', 'FateenAi::usage');
    $routes->post('generate', 'FateenAi::generate');
});

/*──────────────────────── AI Endpoints ───────────────────────*/
$routes->post('ai/generate', 'Ai::generate');
$routes->get ('ai/usage',    'Ai::usage');
// إن أردت لاحقًا البثّ المباشر:
$routes->post('ai/stream',   'Ai::stream'); // اختياري لاحقًا

/*──────────────────────── الواجهة العامة ─────────────────────*/
$routes->get('/', 'Home::index');
$routes->get ('complete-profile', 'AuthController::completeProfile');
$routes->post('complete-profile', 'AuthController::doCompleteProfile');

// ───────────────────── مصادقة المستخدم (Auth) ─────────────────────
$routes->group('auth', static function (RouteCollection $routes) {
    // التسجيل والتحقق
    $routes->get ('register',         'AuthController::registerRedirect');
    $routes->post('register',         'AuthController::doRegister');
    $routes->get ('followup',         'AuthController::followup');
    $routes->get ('verify',           'AuthController::verify');

    // الدخول والخروج وإكمال الملف
    $routes->get ('login',            'AuthController::loginRedirect');
    $routes->post('login',            'AuthController::doLoginTemp'); // was doLogin
    $routes->get ('redirect',         'AuthController::redirect', ['filter' => 'auth']); // مسار إعادة التوجيه الذكي
    $routes->get ('complete-profile', 'AuthController::completeProfile');
    $routes->post('complete-profile', 'AuthController::doCompleteProfile');
    $routes->get ('temp-profile',     'AuthController::TempProfile'); // مؤقت
    $routes->post('temp-profile',     'AuthController::doTempProfile'); // مؤقت

    // استعادة كلمة المرور
    $routes->get ('forgot-password',  'AuthController::forgot');
    $routes->post('forgot-password',  'AuthController::sendResetLink');
    $routes->get ('reset-password',   'AuthController::reset');
    $routes->post('reset-password',   'AuthController::doReset');

    // الاستبيان
    $routes->get ('survey',           'AuthController::survey');
    $routes->post('survey',           'AuthController::doSurvey');

    // Google OAuth
    $routes->get ('google',           'AuthController::googleRedirect');
    $routes->get ('google-callback',  'AuthController::googleCallback');

    // السياسات والصفحات القانونية
    $routes->get ('accept_terms',     'AuthController::AcceptTerms');
    $routes->post('accept_terms',     'AuthController::doAcceptTerms');
    $routes->get ('terms',            'AuthController::Terms');
    $routes->get ('privacy',          'AuthController::Privacy');
    $routes->get ('faq',              'AuthController::FAQ');
    $routes->get ('contact',          'AuthController::Contact');
    $routes->get ('news',             'AuthController::News');

    // تسجيل الخروج
    $routes->get ('logout',           'AuthController::logout');
});


// ======================================================================
//                           مســـارات الأدمن مع المنطق الجديد
// ======================================================================





$routes->group('admins', ['namespace' => 'App\Controllers\Admins', 'filter' => 'auth'], static function (RouteCollection $routes)
{
    // صفحات تُحقن ديناميكيًا داخل #contentArea
    $routes->group('pages', static function (RouteCollection $routes) {
        $routes->get('courses', 'PagesController::courses');     // مثال ثابت
        $routes->get('task_type_options', 'TaskTypeOptions::index'); // ✅ /admins/pages/task_type_options

        $routes->get('academic_year_options',       'AcademicYearOptions::index');
        $routes->get('university_options',          'UniversityOptions::index');




        $routes->get('(:segment)', 'PagesController::page/$1');  // عام: /admins/pages/<slug>
    });

    // ===== API الموحد تحت /admins/api =====
    $routes->group('api', static function (RouteCollection $routes) {

        // Courses
        $routes->group('courses', static function (RouteCollection $routes) {
            $routes->get ('meta',                'CoursesMeta::index');
            $routes->get ('universities/(:num)', 'CoursesMeta::getUniversities/$1');
            $routes->get ('semesters/(:num)',    'CoursesMeta::getSemesters/$1');
            $routes->get ('filter',              'CoursesMeta::getCoursesByFilters');
            $routes->get ('show/(:num)',         'CoursesMeta::show/$1');
            $routes->post('store',               'CoursesMeta::store');
            $routes->put ('update/(:num)',       'CoursesMeta::update/$1');
            $routes->delete('delete/(:num)',     'CoursesMeta::delete/$1');
        });

        // Weeks
        $routes->group('weeks', static function (RouteCollection $routes) {
            $routes->get ('meta',                'WeeksMeta::index');
            $routes->get ('universities/(:num)', 'WeeksMeta::getUniversities/$1');
            $routes->get ('semesters/(:num)',    'WeeksMeta::getSemesters/$1');
            $routes->get ('courses',             'WeeksMeta::getCourses');
            $routes->get ('list',                'WeeksMeta::list');
            $routes->post('store',               'WeeksMeta::store');
            $routes->get ('show/(:num)',         'WeeksMeta::show/$1');
            $routes->put ('update/(:num)',       'WeeksMeta::update/$1');
            $routes->delete('delete/(:num)',     'WeeksMeta::delete/$1');
        });

        // Tasks
        $routes->group('task', static function (RouteCollection $routes) {
            $routes->get('/',                         'TasksMeta::index');
            $routes->get('getAcademicYears',          'TasksMeta::getAcademicYears');
            $routes->get('getTaskTypes',              'TasksMeta::getTaskTypes');
            $routes->get('getAiJobTypes',             'TasksMeta::getAiJobTypes');
            $routes->get('getSpecialties',            'TasksMeta::getSpecialties');
            $routes->get('getSpecialties/(:num)',     'TasksMeta::getSpecialties/$1');
            $routes->get('getUniversities/(:num)',    'TasksMeta::getUniversities/$1');
            $routes->get('getSemesters/(:num)',       'TasksMeta::getSemesters/$1');
            $routes->get('getCourses',                'TasksMeta::getCourses');
            $routes->get('getWeeks/(:num)',           'TasksMeta::getWeeks/$1');
            $routes->get('getTasks',                  'TasksMeta::getTasks');
            $routes->get('getTask/(:num)',            'TasksMeta::getTask/$1');
            $routes->get('getTaskForView/(:num)',     'TasksMeta::getTaskForView/$1');
            $routes->post  ('createOrUpdateTask',     'TasksMeta::createOrUpdateTask');
            $routes->delete('deleteTask/(:num)',      'TasksMeta::deleteTask/$1');
            $routes->get   ('download/(:num)',        'TasksMeta::download/$1');
        });

        /*──────────────────────── جدول طلبات الخدمة (أدمن) ─────────────────────*/
        $routes->group('service-requests-table', ['namespace' => 'App\Controllers\Admins'], static function (RouteCollection $routes) {
            
            $routes->post('(:num)/messages/(:num)/delete', '\App\Controllers\Admins\ServiceMessages::delete/$1/$2');

            // واجهة الصفحة (Partial)
            $routes->get('/', 'AdminServiceRequestsTableController::index');

            // Endpoints
            $routes->get('health', 'AdminServiceRequestsTableController::health');
            $routes->get('list',   'AdminServiceRequestsTableController::getRequestsList');
            $routes->get('admins', 'AdminServiceRequestsTableController::getAdminsList');
            $routes->get('stats',  'AdminServiceRequestsTableController::getStats');
            $routes->post('status/(:num)', 'AdminServiceRequestsTableController::updateStatus/$1');
            $routes->post('assign/(:num)', 'AdminServiceRequestsTableController::assignRequest/$1');
            // ملاحظة: إبقاء هذا الراوت لا يضر، لكن التحديث الأساسي صار ضمن مجموعة service-requests أدناه.
            // $routes->post('(:num)/status', 'ServiceChatController::updateStatus/$1');
        });

        /*──────────────────────── محادثة طلبات الخدمة (أدمن) ─────────────────────*/
        $routes->group('service-requests', static function (RouteCollection $routes) {

            // أعلى الصفحة (الخطة/الأولوية/الصوت)
            $routes->get('top-meta', 'ServiceChatController::topMeta');

            // قائمة الطلبات (بحث/فلترة/ترقيم)
            $routes->get('list', 'ServiceChatController::listRequests');

            // تفاصيل طلب + المرفقات الأولية
            $routes->get('(:num)', 'ServiceChatController::requestDetails/$1');

            // رسائل الطلب (جلب أحدث الرسائل + before_id)
            $routes->get('(:num)/messages', 'ServiceChatController::listMessages/$1');

            // إرسال رسالة (نص/ملفات/صوت) — CSRF
            $routes->post('(:num)/messages', 'ServiceChatController::sendMessage/$1');

            // إلغاء الطلب — CSRF
            $routes->post('(:num)/cancel', 'ServiceChatController::cancelRequest/$1');

            // تحديث حالة الطلب — CSRF  ✅ جديد
            $routes->post('(:num)/status', 'ServiceChatController::updateStatus/$1');

            // (اختياري) وسم الرسائل كمقروءة حتى رسالة معيّنة — CSRF
            $routes->post('(:num)/read-up-to', 'ServiceChatController::readUpTo/$1');

            // (اختياري) عدّادات غير المقروء لكل طلب
            $routes->get('unread-counters', 'ServiceChatController::unreadCounters');

            // بث/عرض الملفات ضمن صلاحيات الطلب ✅ جديد
            // GET /admins/api/service-requests/file/406?disposition=inline
            $routes->get('file/(:num)', 'ServiceChatController::streamFile/$1');
        });

        // أدوات
        $routes->get('tools/purge-cache', 'ToolsController::purgeCache');
        
        // استعراض واجهة الطالب
        $routes->group('student-view', static function (RouteCollection $routes) {
            $routes->get('settings', 'StudentViewController::settings');
            $routes->post('settings', 'StudentViewController::saveSettings');
            $routes->post('start', 'StudentViewController::start');
            $routes->post('stop', 'StudentViewController::stop');
            $routes->get('options', 'StudentViewController::options');
        });
        
        
        
        
        $routes->group('academic-years', static function (RouteCollection $routes) {
            // counts
            $routes->get('summary', 'AcademicYearApi::summary');

            // years CRUD
            $routes->post('store',           'AcademicYearApi::storeYear');
            $routes->put ('update/(:num)',   'AcademicYearApi::updateYear/$1');
            $routes->delete('delete/(:num)', 'AcademicYearApi::deleteYear/$1');

            // semesters
            $routes->post  ('semesters/store',          'AcademicYearApi::storeSemester');
            $routes->delete('semesters/delete/(:num)',  'AcademicYearApi::deleteSemester/$1');

            // show (semesters + universities)
            $routes->get('show/(:num)', 'AcademicYearApi::show/$1');

            // link/unlink universities
            $routes->post  ('link-universities/(:num)',          'AcademicYearApi::linkUniversities/$1');
            $routes->delete('link-universities/(:num)/(:num)',   'AcademicYearApi::unlinkUniversity/$1/$2');
        });
        
        
        
         $routes->group('UniversityOptions', static function (RouteCollection $routes) {

            // الإحصاءات للبطاقات
            $routes->get('summary', 'UniversityOptions::summary');    // GET /admins/api/UniversityOptions/summary

            // الجامعات
            $routes->post  ('universities/store',         'UniversityOptions::createUniversity');         // POST
            $routes->get   ('universities/show/(:num)',   'UniversityOptions::show/$1');                  // GET
            $routes->delete('universities/delete/(:num)', 'UniversityOptions::delete/$1');                // DELETE

            // التخصصات
            $routes->post  ('specialties/store',              'UniversityOptions::createSpecialty');           // POST
            $routes->post  ('specialties/link/(:num)',        'UniversityOptions::linkSpecialties/$1');        // POST body: {specialty_ids:[]}
            $routes->delete('specialties/unlink/(:num)/(:num)','UniversityOptions::unlinkSpecialty/$1/$2');    // DELETE
            $routes->delete('specialties/delete/(:num)',      'UniversityOptions::deleteSpecialty/$1');        // DELETE
        });
        
       
        $routes->group('task-type', static function($routes){
      $routes->get('summary',        'TaskTypeOptions::summary');
      $routes->post('create',        'TaskTypeOptions::create');
      $routes->delete('delete/(:num)','TaskTypeOptions::delete/$1');
    });
    
    


        $routes->group('stats', static function (RouteCollection $routes) {

            $routes->group('diploma', static function (RouteCollection $routes) {
               
                $routes->get('active-breakdown',         'Stats\DiplomaStats::activeBreakdown');  // ✅ جديد
                $routes->get('plan-distribution',        'Stats\DiplomaStats::planDistribution');
                $routes->get('fateen-usage',             'Stats\DiplomaStats::fateenUsage');
               
               
               
                // ✅ دبلوم: ملخص النشطين (DAU/WAU/MAU)
                $routes->get('active-users',              'Stats\DiplomaStats::activeUsers');

                // ✅ دبلوم: النشطون حسب الجامعة (window=day|week|month, limit=10)
                $routes->get('active-by-university',      'Stats\DiplomaStats::activeByUniversity');

                // ✅ دبلوم: اكتمال التسجيل (is_complete)
                $routes->get('registration-completion',   'Stats\DiplomaStats::registrationCompletion');

                // ✅ دبلوم: توزيع المستخدمين حسب الخطة (مع شهر مجاني)

                // ✅ دبلوم: توزيع عام على الجامعات (بدون شرط نشاط)
                $routes->get('universities-distribution', 'Stats\DiplomaStats::universitiesDistribution');
                
                $routes->get('universities-distribution', 'Stats\DiplomaStats::universitiesDistribution');
                $routes->get('active-users-list', 'Stats\DiplomaStats::activeUsersList');



                // ✅ دبلوم: استخدام فَطين (fateen_usage_logs) bucket=day|week|month

                // ✅ إحصائيات ai_jobs (مهام الذكاء الاصطناعي للتاسك)
                $routes->get('ai-job-stats', 'Stats\\DiplomaStats::aiJobStats');
            });
            
            
            $routes->group('library', static function ($routes) {
                $routes->get('downloads-summary',     'Stats\LibraryStats::downloadsSummary');
                $routes->get('top-grades',            'Stats\LibraryStats::topGrades');
                $routes->get('downloads-auth-vs-anon','Stats\LibraryStats::downloadsAuthVsAnon');
                $routes->get('visits-summary',        'Stats\LibraryStats::visitsSummary');
            });
            
        
        
            // ... المجموعات الأخرى
        
    // ===== Tools Stats (جديد) =====
    $routes->group('tools', static function (RouteCollection $routes) {
        // GET /admins/api/stats/tools/cards
        $routes->get('cards', 'Stats\ToolsStats::cards');
    });



        });

  
    
    });
    });


    $routes->group('manage_users', ['namespace' => 'App\Controllers\Admins', 'filter' => 'auth'], static function (RouteCollection $routes) {
    $routes->get ('list',            'ManageUsers::list');          // GET  /manage_users/list
    $routes->get ('options',         'ManageUsers::options');       // GET  /manage_users/options
    $routes->get ('stats',           'ManageUsers::stats');         // GET  /manage_users/stats
    $routes->post('update_account',  'ManageUsers::updateAccount'); // POST /manage_users/update_account
    $routes->post('delete',          'ManageUsers::delete');        // POST /manage_users/delete
});

// مسار عام سابق (لا مانع من بقائه)
    $routes->get('file-view', 'FileView::view');


// app/Config/Routes.php
    $routes->group('ops/db-health', static function($r){
    $r->get('default', 'System\DbHealth::default');
    $r->get('remote',  'System\DbHealth::remote');
    $r->get('group/(:segment)', 'System\DbHealth::group/$1');
    $r->get('all',     'System\DbHealth::all');
});











/*──────────────────────── طلبات الخدمة - جديد (جدول + دردشة) ─────────────────────*/
$routes->group('admins/service-requests-new', static function (RouteCollection $routes) {
    // صفحة الواجهة
    $routes->get ('/',                     'AdminServiceRequestsNewController::index');

    // قائمة الطلبات
    $routes->get ('list',                  'AdminServiceRequestsNewController::getRequestsList');

    // تفاصيل رأس الطلب + الرسائل
    $routes->get ('details/(:num)',        'AdminServiceRequestsNewController::getRequestDetails/$1');
    $routes->get ('new-messages/(:num)',   'AdminServiceRequestsNewController::getNewMessages/$1');

    // المراسلة والملفات
    $routes->post('message/(:num)',        'AdminServiceRequestsNewController::sendMessage/$1');
    $routes->get ('file/(:num)',           'AdminServiceRequestsNewController::downloadFile/$1');

    // تعليم كمقروء
    $routes->post('mark-read/(:num)',      'AdminServiceRequestsNewController::markAsRead/$1');

    // تحديث الحالة والتكليف
    $routes->post('status/(:num)',         'AdminServiceRequestsNewController::updateStatus/$1');
    $routes->post('status',                'AdminServiceRequestsNewController::updateStatus');
    $routes->post('assign/(:num)',         'AdminServiceRequestsNewController::assignRequest/$1');
    $routes->get ('admins',                'AdminServiceRequestsNewController::getAdminsList');
});

// ======================================================================
//                           بقية المسارات (غير الأدمن)
// ======================================================================

/*──────────────────────── API: الدورات ──────────────────────*/
$routes->group('api/courses', static function (RouteCollection $routes) {
    $routes->get ('meta',                '\App\Controllers\Admins\CoursesMeta::index');
    $routes->get ('universities/(:num)', '\App\Controllers\Admins\CoursesMeta::getUniversities/$1');
    $routes->get ('semesters/(:num)',    '\App\Controllers\Admins\CoursesMeta::getSemesters/$1');
    $routes->get ('specialties/(:num)',  '\App\Controllers\Admins\CoursesMeta::getSpecialties/$1');
    $routes->post('store',               '\App\Controllers\Admins\CoursesMeta::store');
    $routes->get ('filter',              '\App\Controllers\Admins\CoursesMeta::getCoursesByFilters');
    $routes->get ('show/(:num)',         '\App\Controllers\Admins\CoursesMeta::show/$1');
    $routes->put ('update/(:num)',       '\App\Controllers\Admins\CoursesMeta::update/$1');
    $routes->delete('delete/(:num)',     '\App\Controllers\Admins\CoursesMeta::delete/$1');
});

/*──────────────────────── API: الأسابيع ─────────────────────*/
$routes->group('api/weeks', static function (RouteCollection $routes) {
    $routes->get ('meta',                'WeeksMeta::index');
    $routes->get ('universities/(:num)', 'WeeksMeta::getUniversities/$1');
    $routes->get ('semesters/(:num)',    'WeeksMeta::getSemesters/$1');
    $routes->get ('courses',             'WeeksMeta::getCourses');
    $routes->get ('list',                'WeeksMeta::list');
    $routes->post('store',               'WeeksMeta::store');
    $routes->get ('show/(:num)',         'WeeksMeta::show/$1');
    $routes->put ('update/(:num)',       'WeeksMeta::update/$1');
    $routes->delete('delete/(:num)',     'WeeksMeta::delete/$1');
});

/*──────────────────────── API: المهام ───────────────────────*/
$routes->group('task', static function (RouteCollection $routes) {
    $routes->get('/',                         'TasksMeta::index');
    $routes->get('getAcademicYears',          'TasksMeta::getAcademicYears');
    $routes->get('getTaskTypes',              'TasksMeta::getTaskTypes');
    $routes->get('getSpecialties',            'TasksMeta::getSpecialties');
    $routes->get('getSpecialties/(:num)',     'TasksMeta::getSpecialties/$1');
    $routes->get('getUniversities/(:num)',    'TasksMeta::getUniversities/$1');
    $routes->get('getSemesters/(:num)',       'TasksMeta::getSemesters/$1');
    $routes->get('getCourses',                'TasksMeta::getCourses');
    $routes->get('getWeeks/(:num)',           'TasksMeta::getWeeks/$1');
    $routes->get('getTasks',                  'TasksMeta::getTasks');
    $routes->get('getTask/(:num)',            'TasksMeta::getTask/$1');
    $routes->get('getTaskForView/(:num)',     'TasksMeta::getTaskForView/$1');
    $routes->post  ('createOrUpdateTask',     'TasksMeta::createOrUpdateTask');
    $routes->delete('deleteTask/(:num)',      'TasksMeta::deleteTask/$1');
    $routes->get   ('download/(:num)',        'TasksMeta::download/$1');
});

/*────────────────── API: الأعوام والفصول (إداري) ───────────*/
$routes->group('api/academic-years', static function (RouteCollection $routes) {
    $routes->get   ('summary',                           'Api\AcademicYearController::summary');
    $routes->post  ('store',                             'Api\AcademicYearController::create');
    $routes->put   ('update/(:num)',                     'Api\AcademicYearController::update/$1');
    $routes->delete('delete/(:num)',                     'Api\AcademicYearController::delete/$1');
    $routes->get   ('show/(:num)',                       'Api\AcademicYearController::show/$1');

    // الفصول المرتبطة
    $routes->post  ('semesters/store',                   'Api\AcademicYearController::createSemester');
    $routes->delete('semesters/delete/(:num)',           'Api\AcademicYearController::deleteSemester/$1');

    // ربط الجامعات بالسنة
    $routes->post  ('link-universities/(:num)',          'Api\AcademicYearController::linkUniversities/$1');
    $routes->delete('link-universities/(:num)/(:num)',   'Api\AcademicYearController::unlinkUniversity/$1/$2');
});

/*────────────── API: الجامعات والتخصّصات (إداري) ───────────*/
$routes->group('api/universities', static function (RouteCollection $routes) {
    $routes->get   ('summary',                           'UniversityOptions::summary');
    $routes->post  ('store',                             'UniversityOptions::createUniversity');
    $routes->delete('delete/(:num)',                     'UniversityOptions::delete/$1');
    $routes->get   ('show/(:num)',                       'UniversityOptions::show/$1');

    // التخصصات
    $routes->post  ('specialties/store',                 'UniversityOptions::createSpecialty');
    $routes->delete('specialties/delete/(:num)',         'UniversityOptions::deleteSpecialty/$1');
    $routes->delete('specialties/unlink/(:num)/(:num)',  'UniversityOptions::unlinkSpecialty/$1/$2');
    $routes->post  ('link-specialties/(:num)',           'UniversityOptions::linkSpecialties/$1');
});

/*────────────────────── API: أنواع المهام ───────────────────*/
$routes->group('api/task-types', static function (RouteCollection $routes) {
    $routes->get   ('summary',       'TaskTypeOptions::summary');
    $routes->post  ('store',         'TaskTypeOptions::create');
    $routes->delete('delete/(:num)', 'TaskTypeOptions::delete/$1');
});

// حفظ/دعم
$routes->post('/submit-request', 'SupportController::submit');
$routes->get('download', 'DownloadController::index', ['as' => 'download']);

// (Route عام لحذف رسالة — غير أدمن)
$routes->post('message/(:num)/delete', '\App\Controllers\ServiceRequestController::deleteMessage/$1');


// ───────────────────── واجهة الطلاب (plural) ─────────────────────
$routes->group('students', ['filter' => 'auth'], static function ($routes) {
    // صفحات عامة
    $routes->get('service-unread-total',            'Students\Student::serviceUnreadTotal');

    $routes->get('/',                               '\App\Controllers\Students\Student::index');
    $routes->get('pages/upgrade',                   'Students\Upgrade::page');
    $routes->get('pages/upgrade/state',             'Students\Upgrade::state');

    $routes->group('upgrade', ['filter' => 'feature:upgrade_page'], static function ($routes) {
        $routes->get ('/',       'Students\UpgradeController::index');  // واجهة
        $routes->post('start',   'Students\UpgradeController::start');  // بدء الترقية
        $routes->post('cancel',  'Students\UpgradeController::cancel'); // إلغاء (إن وُجد)
    });

    // Sidebar
    $routes->get('sidebar-meta',                    'Students\SidebarMeta::index');

    // Views ثابتة لما يتوقعه الـ JS
    $routes->get('pages/course_content',            'Students\CourseContentController::pages/course-content');
    $routes->get('pages/task_details',              'Students\CourseContentController::pages/task-details');
    $routes->get('pages/task-details',              'Students\CourseContentController::pages/task-details');
    $routes->get('pages/(:segment)',                '\App\Controllers\Students\Student::pages/$1');

    // صفحة خدمة (تعريف صريح قبل الـ catch-all)
    $routes->get('pages/service',                   '\App\Controllers\Students\ServiceRequestController::index'); // CHANGED

    // ====== خدمة الطلبات — نسخة الطلاب الجديدة ======
    $routes->group('service-requests', static function ($routes) {
        // الميتا الخاصة بالمودال القديم
        $routes->get('meta', '\App\Controllers\Students\ServiceRequestsController::meta');

        // إنشاء طلب خدمة (FormData)
        $routes->post('', '\App\Controllers\Students\ServiceRequestsController::store');

        // واجهات المحادثة
        $routes->get ('top-meta',                  '\App\Controllers\Students\ServiceChatController::topMeta');
        $routes->get ('list',                      '\App\Controllers\Students\ServiceChatController::listRequests');
        $routes->post('(:num)/messages/(:num)/delete', '\App\Controllers\Students\ServiceMessages::delete/$1/$2');

        $routes->get ('(:num)',                    '\App\Controllers\Students\ServiceChatController::requestDetails/$1');
        $routes->get ('(:num)/messages',           '\App\Controllers\Students\ServiceChatController::listMessages/$1');
        $routes->post('(:num)/messages',           '\App\Controllers\Students\ServiceChatController::sendMessage/$1');
        $routes->post('(:num)/cancel',             '\App\Controllers\Students\ServiceChatController::cancelRequest/$1');

        // غير المقروء
        $routes->post('(:num)/read-up-to',         '\App\Controllers\Students\ServiceChatController::readUpTo/$1');
        $routes->get ('unread-counters',           '\App\Controllers\Students\ServiceChatController::unreadCounters');
     
        // عرض/بث الملف (inline أو حسب نوع الميم)
        $routes->get('file/(:num)', '\App\Controllers\Students\FileViewController::view/$1');

        // تنزيل صريح للمرفق
        $routes->get('file/(:num)/download', '\App\Controllers\Students\FileViewController::download/$1');   
    });
    $routes->get('file-view', '\App\Controllers\Students\FileViewController::show');

    // Course Content APIs
    $routes->get ('course-content/(:num)',         'Students\CourseContentController::getCourseContent/$1');
    $routes->get ('course-content/stats/(:num)',   'Students\CourseContentController::getCourseStats/$1');
    $routes->post('course-content/access/(:num)',  'Students\CourseContentController::updateLastAccess/$1');
    $routes->get ('course-content/search',         'Students\CourseContentController::searchCourseContent');
    $routes->post('course-content/clear-cache',    'Students\CourseContentController::clearUserCache'); // كاش المستخدم فقط
    $routes->post('course-content/server-cache',   'Students\CourseContentController::clearCache');     // كاش الخادم (AJAX, POST)

    // اختياري + مكرّرات متوافقة مع القديم
    $routes->get ('course-content/user-courses',   'Students\CourseContentController::getUserCourses');
    $routes->post('course-content/clear-cache',    'Students\CourseContentController::clearUserCache');
    $routes->get ('courses/by-semester/(:num)',    'Students\CourseContentController::getCoursesBySemester/$1');

    // تفاصيل مهمة + ستريم
    $routes->get ('course-content/task-context/(:num)', 'Students\CourseContentController::getTaskContext/$1');
    $routes->get ('course-content/stream/(:num)',       'Students\CourseContentController::stream/$1');
    $routes->get ('course-content/download/(:num)',     'Students\CourseContentController::download/$1');

    // APIs عامة
    $routes->get('api/available-pages',             '\App\Controllers\Students\Student::getAvailablePages');
    $routes->get('api/validate-section/(:segment)', '\App\Controllers\Students\Student::validateSection/$1');

    // Terms & conditions consent
    $routes->group('terms', static function ($routes) {
        $routes->get ('status',  '\App\Controllers\Students\TermsConsentController::status');
        $routes->post('accept',  '\App\Controllers\Students\TermsConsentController::accept');
        $routes->post('decline', '\App\Controllers\Students\TermsConsentController::decline');
    });

    // Debug / Auth
    $routes->get('debug-session',                   'Students\Student::debugSession');
    $routes->get('logout',                          '\App\Controllers\Students\Auth::logout');
    // Dashboard API
    $routes->group('dashboard', static function (RouteCollection $routes) {
        $routes->get('upcoming-tasks',              '\App\Controllers\Students\StuDashboard::upcomingTasks');
        $routes->get('getTaskForView/(:num)',       '\App\Controllers\Students\StuDashboard::getTaskForView/$1');
        $routes->get('download/(:num)',             '\App\Controllers\Students\StuDashboard::download/$1');
    });

    // نظام المكافآت
    $routes->group('rewards', ['filter' => 'auth'], static function (RouteCollection $routes) {
        $routes->get('/',                    '\App\Controllers\RewardsController::index');
        $routes->get('referral-code',        '\App\Controllers\RewardsController::getReferralCode');
        $routes->get('transactions',         '\App\Controllers\RewardsController::getTransactions');
        $routes->get('stats',                '\App\Controllers\RewardsController::getStats');
    });
});

// عرض ملف/تشخيص
$routes->get('file-view', '\App\Controllers\FilesController::view', ['filter' => 'auth']);
$routes->get('api/diag/preview', 'Api\PreviewDiagnosticsController::index');

// ===================== API: File Previews =====================
$routes->group('api', ['filter' => 'auth'], static function (RouteCollection $routes) {
    // PDF/Office previews as images
    $routes->head('files/(:num)/preview',        'Api\FilePreviewController::image/$1');
    $routes->get ('files/(:num)/preview',        'Api\FilePreviewController::image/$1');
    $routes->post('files/(:num)/preview/purge',  'Api\FilePreviewController::purge/$1');
});

// ===================== Upgrade APIs (عام + Auth) =====================
$routes->group('api', static function ($r) {
    $r->get('plans', 'Api\PlansApi::index');                 // (أقدم)
    $r->get('payment-methods', 'Api\PaymentsMeta::methods');

    $r->post('upgrade/request', 'Api\UpgradeApi::request');
    $r->post('upgrade/confirm', 'Api\UpgradeApi::confirm');  // للأدمن/الويبهوك
});
$routes->get('api/plans', 'Api\LookupApi::plans');           // (منتصف)
$routes->get('api/payment-methods', 'Api\LookupApi::paymentMethods');

// حفظ الطلب
$routes->post('api/upgrade/request', 'Api\UpgradeApi::request');
// اعتماد الدفع (أدمن/ويبهوك)
$routes->post('api/upgrade/confirm', 'Api\UpgradeApi::confirm');

$routes->group('api/pos', static function($routes){
    $routes->get('plans', 'Api\Pos\Plans::index');
    $routes->options('plans', 'Api\Pos\Plans::index'); // للـ CORS preflight
});

$routes->group('admins/library', [
    'namespace' => 'App\Controllers\Admins',
    'filter'    => 'authGuard',
], static function ($routes) {
    $routes->get ('uploads', 'LibraryUpload::index');
    $routes->post('uploads', 'LibraryUpload::store');

    $routes->get('meta/grades',                 'LibraryMeta::grades');
    $routes->get('meta/grades/(:num)/subjects', 'LibraryMeta::subjects/$1');
    $routes->get('meta/semesters',              'LibraryMeta::semesters');
    $routes->get('meta/categories',             'LibraryMeta::categories');

    $routes->get   ('files',               'LibraryMeta::files');
    $routes->delete('files/(:num)',        'LibraryMeta::delete/$1');
    $routes->post  ('files/(:num)/delete', 'LibraryMeta::delete/$1');
});
$routes->get(
    'admins/library/debug',
    'Admins\LibraryMeta::debug',
    ['filter' => 'authGuard']
);

// نضع نسخة الـ Auth في الآخر لتكون الفعّالة عند تكرار نفس المسار
$routes->group('api', ['filter' => 'auth'], static function ($routes) {
    $routes->get('plans', 'Api\Plans::index');               // (أحدث/محمي)
    $routes->group('vouchers', ['filter' => 'authBridge'], static function ($routes) {
        $routes->post('verify', 'Api\Vouchers::verify');
        $routes->post('redeem', 'Api\Vouchers::redeem');
    });
});

$routes->get('api/v2/plans', 'Api\Plans::index');
$routes->group('api/v2', ['namespace' => 'App\Controllers\Api'], static function($routes) {
    $routes->get   ('vouchers',    'VoucherStore::index'); // سيرجع 405
    $routes->post  ('vouchers',    'VoucherStore::index'); // إنشاء
    $routes->delete('vouchers',    'VoucherStore::index'); // حذف
});

// صفحة هبوط للتحقق من الفاوتشر
$routes->get('voucher/verify', '\App\Controllers\VoucherLanding::verify');

// ===================== API - ملفات (عرض/تنزيل/فحص) =====================
$routes->group('api/files', static function($routes) {
    $routes->get('(:num)/check',    'FileGateway::check/$1');    // للتحقق قبل (عرض/تنزيل)
    $routes->get('(:num)/stream',   'FileGateway::stream/$1');   // بث/عرض AV بمدى (Range)
    $routes->get('(:num)/download', 'FileGateway::download/$1'); // تنزيل الملف مع احتساب السقوف
});
$routes->group('api/public-files', ['namespace' => 'App\Controllers'], function($routes) {
    $routes->get('(:num)/view',             'PublicFileController::view/$1');
    $routes->get('(:num)/download',         'PublicFileController::download/$1');
    $routes->post('access-check',           'PublicFileController::accessCheck');
});



// ===================== AI Jobs (جديد – بدون تعارض) =====================
$routes->group('api/ai-jobs', [
    'namespace' => 'App\Controllers\Api',
    'filter'    => 'auth', // نفس نمط بقية الـ APIs المحمية عندك
], static function ($routes) {
    // إنشاء طلب توليد (يمنع التكرار لكل user/task/type)
    $routes->post('request', 'AiJobs::request');  // POST /api/ai-jobs/request

    // فحص حالة الطلب/الملف
    $routes->get('status',  'AiJobs::status');    // GET  /api/ai-jobs/status?type_key=...&task_id=...

    // إعادة بناء مهمة فاشلة ✅
    $routes->post('rebuild', 'AiJobs::rebuild');  // POST /api/ai-jobs/rebuild  { job_id: N }
    
    // إعادة بناء الملف من النتيجة السابقة
    $routes->post('rebuild-file', 'AiJobs::rebuildFile');
     $routes->get ('diag',    'AiJobs::diag');   // ← مؤقت للتشخيص
      $routes->get('result',          'Api\AiJobs::result');   // يُرجع ردّ الـ AI كـ JSON عند اكتمال الطلب
    $routes->get('show/(:num)',     'Api\AiJobs::show/$1');  // (اختياري) عرض الطلب مباشرة بالـ id

});

// ===================== AI Tools for Students (نظام جديد) =====================
$routes->group('students/ai-tools', [
    'namespace' => 'App\Controllers\Students',
    'filter'    => 'auth',
], static function ($routes) {
    $routes->get('/', 'AiToolsController::index');           // GET  /students/ai-tools
    $routes->get('placements', 'AiToolsController::placements'); // GET /students/ai-tools/placements
    $routes->get('check-access', 'AiToolsController::checkAccess'); // GET /students/ai-tools/check-access?type_key=...&task_id=...
    $routes->get('jobs-list', 'AiToolsController::jobsList'); // GET /students/ai-tools/jobs-list?type_key=...&task_id=...
    $routes->get('job-payload/(:num)', 'AiToolsController::jobPayload/$1'); // GET /students/ai-tools/job-payload/:jobId
    $routes->post('job-update-payload/(:num)', 'AiToolsController::jobUpdatePayload/$1'); // POST /students/ai-tools/job-update-payload/:jobId
    $routes->post('job-reset/(:num)', 'AiToolsController::jobReset/$1'); // POST /students/ai-tools/job-reset/:jobId
    $routes->post('request', 'AiToolsController::request');   // POST /students/ai-tools/request
    $routes->get('status', 'AiToolsController::status');     // GET  /students/ai-tools/status?type_key=...&task_id=...
    $routes->get('result', 'AiToolsController::result');     // GET  /students/ai-tools/result?type_key=...&task_id=...
    $routes->get('download/(:num)', 'AiToolsController::download/$1'); // GET /students/ai-tools/download/:fileId
    $routes->post('comment-reply', 'AiToolsController::commentReply'); // POST /students/ai-tools/comment-reply
    $routes->get('comments', 'AiToolsController::comments'); // GET /students/ai-tools/comments?task_id=...
    $routes->post('retry', 'AiToolsController::retry');     // POST /students/ai-tools/retry
    $routes->delete('delete', 'AiToolsController::delete'); // DELETE /students/ai-tools/delete?type_key=...&task_id=... (فقط للأدمن في وضع الاستعراض)
    $routes->get('specialties', 'AiToolsController::specialties'); // GET /students/ai-tools/specialties
    $routes->post('debug-rag', 'AiToolsController::debugRag'); // POST /students/ai-tools/debug-rag (للتشخيص)
    $routes->get('get-task-prompt', 'AiToolsController::getTaskPrompt'); // GET /students/ai-tools/get-task-prompt?task_id=...
    $routes->post('approve-evaluation', 'AiToolsController::approveEvaluation'); // POST /students/ai-tools/approve-evaluation
    $routes->post('regenerate-feedback', 'AiToolsController::regenerateFeedback'); // POST /students/ai-tools/regenerate-feedback
});


$routes->get('cron/ai-jobs/run', 'Cron\AiJobsRunner::run'); // استخدم ?key=SECRET
$routes->get('cron/rewards/process', 'Cron\RewardsProcessor::process'); // استخدم ?key=SECRET

// Route للتشخيص بدون auth (محمي بمفتاح سري)
$routes->post('debug/rag', 'Students\AiToolsController::debugRagPublic'); // POST /debug/rag?key=SECRET

$routes->get('api/subscription/plan', 'Api\SubscriptionController::plan');

// Notifications API
$routes->group('api/notifications', ['namespace' => 'App\Controllers\Api'], static function (RouteCollection $routes) {
    $routes->post('send-task',   'NotificationApiController::sendTaskNotification');
    $routes->post('send-custom', 'NotificationApiController::sendCustomNotification');
});

$routes->get('ops/ai/health', 'Ops\AiHealth::ping'); // فحص اتصال مزود الذكاء

if (is_file(APPPATH . 'Config/AdminRoutes.php')) {
    require APPPATH . 'Config/AdminRoutes.php';
}
$routes->get('fix-ai-jobs-status', function() {
    $k = (string) (getenv('CRON_SECRET') ?: '');
    if ($k === '' || ($_GET['key'] ?? '') !== $k) { return service('response')->setStatusCode(403)->setBody('Forbidden'); }
    $db = \Config\Database::connect();
    $db->query("ALTER TABLE `ai_jobs` MODIFY COLUMN `status` ENUM('queued','processing','done','failed','review') NOT NULL DEFAULT 'queued'");
    $db->query("UPDATE `ai_jobs` SET `status` = 'queued' WHERE `status` = '' OR `status` = 'pending'");
    return "تم تحديث قاعدة البيانات بنجاح لتشمل حالة queued! يمكنك الآن تجربة إضافة الطلب مرة أخرى.";
});


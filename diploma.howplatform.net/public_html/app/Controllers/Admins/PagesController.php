<?php
namespace App\Controllers\Admins;

use CodeIgniter\Controller;

class PagesController extends Controller
{
    /** صفحات مسموح بها (للأمان) */
    protected array $allowed = [
        'dashboard',
        'courses',
        'weeks',
        'tasks',
        'academic_year_options',
        'task_type_options',
        'university_options',
        'manage_users',
        'account',
        'pending_requests',
        'service_chat',
        'notifications',
        'email_broadcast',
        'library_upload',
        'plans',
        'ocr_upload',
        'ai_tools',

    ];

    /** مسار ثابت إن حبيت: /admins/pages/courses */
    public function courses()
    {
        return $this->render('courses');
    }

    /** مسار عام: /admins/pages/<slug> */
    public function page(string $slug)
    {
        // منع أي محاولة تجاوز مسارات
        $slug = trim($slug);
        if (! in_array($slug, $this->allowed, true)) {
            return $this->response->setStatusCode(404)->setBody('<h1>404</h1>');
        }
        return $this->render($slug);
    }

    protected function render(string $viewName)
    {
        $path = "admins/pages/{$viewName}";
        $full = APPPATH . "Views/{$path}.php";
        if (! is_file($full)) {
            // 404 منطقية لو الفيو ناقص
            return $this->response->setStatusCode(404)
                ->setBody("<h1>404 - View Not Found</h1><pre>{$path}.php</pre>");
        }
        // نرجع جزء HTML فقط (يُحقن داخل #contentArea)
        return view($path);
    }
}

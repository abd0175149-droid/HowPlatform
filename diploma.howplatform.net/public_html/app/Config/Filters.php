<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

/**
 * ⚠️ مهم:
 * - لا تعرّف الخصائص ($aliases, $globals, ...) أكثر من مرة داخل نفس الكلاس.
 * - فلتر upgradegate لا يُطبّق عالميًا؛ نربطه فقط على مجموعات الراوت في Routes.php.
 * - فلتر authFromToken يُفضّل ربطه على مجموعات (api/*) أو جعله عالميًا إن رغبت.
 */
class Filters extends BaseFilters
{
    /**
     * أسماء مستعارة للفلاتر.
     *
     * @var array<string, class-string|list<class-string>>
     */
    public array $aliases = [
        // ===== فلاتر CI4 القياسية =====
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'cors'          => Cors::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,

        // ===== فلاتر التطبيق المخصّصة لديك =====
        'encryptResponse' => \App\Filters\EncryptResponse::class,
        'devsess'         => \App\Filters\DeviceSessionFilter::class,
        'authGuard'       => \App\Filters\AuthGuard::class,
        'auth'            => \App\Filters\AuthFilter::class,

        // ✅ حارس منطقة الإدارة: مصادقة + فرض الدور ∈ [1=admin, 2=teacher]
        'admin'           => \App\Filters\AdminFilter::class,

        // ✅ فلتر حلّ هوية المستخدم من التوكن (JWT → session.user_id)
        'authFromToken'   => \App\Filters\AuthFromToken::class,

        // ✅ فلتر بوابة الإتاحة الجديد
        'upgradegate'     => \App\Filters\UpgradeGateFilter::class,
        'authBridge'      => \App\Filters\AuthBridge::class,

        'feature'         => \App\Filters\FeatureAccessFilter::class,

        // شِم للاسم القديم إن كنت تستخدمه في الراوتات:
        'studentUpgrade'  => \App\Filters\FeatureAccessFilter::class, // سيستدعى مع الميزة upgrade_page

        // ✅ فلتر اختيار مجموعة قاعدة البيانات حسب السب-دومين
        'pickDb'          => \App\Filters\SubdomainDbSelector::class,

        // ✅ فلتر تحديث تتبع الأيام المتتالية لنظام المكافآت
        'rewards'         => \App\Filters\RewardsFilter::class,
    ];

    /**
     * فلاتر مطلوبة (إن كانت نسختك من CI4 تدعم required).
     *
     * @var array{before:list<string>, after:list<string>}
     */
    public array $required = [
        'before' => [
            'forcehttps', // إجبار HTTPS إن كان مفعّلًا
            'pagecache',  // كاش الصفحات (إن مُفعّل)
        ],
        'after' => [
            'pagecache',   // كاش الصفحات (إن مُفعّل)
            'performance', // مؤشرات الأداء
            'toolbar',     // شريط التصحيح (بيئة التطوير)
        ],
    ];

    /**
     * فلاتر تُطبّق دائمًا قبل/بعد كل طلب.
     *
     * ملاحظة: لا نفعّل upgradegate هنا حتى لا يحجب كل الموقع.
     * ويمكنك لاحقًا جعل authFromToken عالميًا لو أردت حلّ الهوية مبكرًا.
     *
     * @var array<string, array<string, array<string,string>>>|array<string, list<string>>
     */
    public array $globals = [
        'before' => [
            // ✅ يجب أن يعمل مبكرًا جدًا قبل أي اتصال بقاعدة البيانات
            'pickDb',
            // 'honeypot',
            // 'csrf',
            // 'invalidchars',
            // 'authFromToken', // ← إن أردت تشغيله عالميًا لاحقًا، ضعه بعد pickDb
            // ✅ تحديث تتبع الأيام المتتالية (يعمل فقط للمستخدمين المسجلين دخول)
            'rewards',
        ],
        'after' => [
            // 'honeypot',
            // 'secureheaders',
            'toolbar',
            // 'encryptResponse',
        ],
    ];

    /**
     * فلاتر حسب نوع الطلب (GET/POST ...).
     *
     * @var array<string, list<string>>
     */
    public array $methods = [
        // مثال:
        // 'post' => ['csrf'],
    ];

    /**
     * فلاتر لمسارات نمطية.
     * نتركها فارغة ونربط الفلاتر اللازمة مباشرة داخل Routes.php.
     *
     * @var array<string, array<string, list<string>>>
     */
    public array $filters = [
        // مثال لاحقًا إن رغبت:
        // 'authFromToken' => [
        //     'before' => ['api', 'api/*'],
        // ],
        // 'devsess' => [
        //     'before' => [
        //         'student', 'student/*',
        //         'admin',   'admin/*',
        //         'api',     'api/*',
        //         'student/pages', 'student/pages/*',
        //         'admin/pages',   'admin/pages/*',
        //         'student/course-content', 'student/course-content/*',
        //     ],
        //     'except' => ['health/*'],
        // ],
    ];
}

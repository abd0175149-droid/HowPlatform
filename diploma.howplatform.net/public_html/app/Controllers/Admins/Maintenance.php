<?php

declare(strict_types=1);

namespace App\Controllers\Admins;

use CodeIgniter\Controller;
use Config\Services;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * أدوات الصيانة للأدمن
 * - Flush Cache: تنظيف كاش الدرايفر + ملفات writable/cache (+ debugbar) + OPcache
 */
class Maintenance extends Controller
{
    public function flushCache()
    {
        // ✅ تحقّق الصلاحية (role=1 فقط)
        $session = session();
        $role = $session->get('role') ?? ($session->get('auth_user')['role'] ?? null);
        if ((int) $role !== 1) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'غير مصرح: هذه العملية متاحة للأدمن فقط.',
            ]);
        }

        $report = [
            'cacheDriver'   => get_class(Services::cache()),
            'cacheCleanOk'  => null,
            'dirs'          => [],
            'opcacheReset'  => null,
            'timestamp'     => date('c'),
        ];

        // 1) تنظيف كاش الدرايفر (APCu/Redis/File... إلخ)
        $cache          = Services::cache();
        $report['cacheCleanOk'] = (bool) $cache->clean();

        // 2) حذف الملفات داخل writable/cache و writable/debugbar (آمن)
        $dirs = [
            WRITEPATH . 'cache',
            WRITEPATH . 'debugbar', // موجود فقط عند تفعيل شريط الأدوات
        ];
        foreach ($dirs as $dir) {
            $report['dirs'][] = $this->purgeDirReport($dir);
        }

        // ⚠️ لا نلمس writable/session حتى لا نطيّح الجلسات

        // 3) إعادة تهيئة OPcache إن كان مفعّل (على Hostinger غالباً مفعّل)
        $report['opcacheReset'] = function_exists('opcache_reset') ? @opcache_reset() : null;

        return $this->response->setJSON([
            'success' => true,
            'message' => 'تم مسح الكاش بنجاح.',
            'report'  => $report,
        ]);
    }

    /**
     * حذف جميع الملفات داخل المجلد (باستثناء index.html و .htaccess و .gitkeep)
     * مع إرجاع تقرير بعدد المحذوف/المتخطى والأخطاء إن وجدت
     */
    private function purgeDirReport(string $dir): array
    {
        $result = [
            'dir'     => $dir,
            'exists'  => is_dir($dir),
            'deleted' => 0,
            'skipped' => 0,
            'errors'  => [],
        ];

        if (! is_dir($dir)) {
            return $result;
        }

        $keep = ['index.html', '.htaccess', '.gitkeep'];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $path     = $file->getPathname();
            $basename = $file->getBasename();

            // لا نحذف ملفات الحماية/الحافظ
            if (in_array($basename, $keep, true)) {
                $result['skipped']++;
                continue;
            }

            if ($file->isDir()) {
                if (! @rmdir($path)) {
                    $result['errors'][] = "rmdir failed: {$path}";
                }
                continue;
            }

            if (@unlink($path)) {
                $result['deleted']++;
            } else {
                $result['errors'][] = "unlink failed: {$path}";
            }
        }

        return $result;
    }
}

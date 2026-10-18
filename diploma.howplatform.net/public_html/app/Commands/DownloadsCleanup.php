<?php
declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Controllers\Cron\DownloadsCleanupRunner;

/**
 * أمر CLI لتنظيف ملفات التنزيل القديمة — غلاف حول DownloadsCleanupRunner::run.
 * مُخصَّص للتشغيل عبر كرون المضيف يومياً:
 *   docker compose exec -T diploma php spark downloads:cleanup
 */
class DownloadsCleanup extends BaseCommand
{
    protected $group       = 'maintenance';
    protected $name        = 'downloads:cleanup';
    protected $description = 'حذف ملفات المهام المُولّدة في public/downloads/ الأقدم من مدة الاحتفاظ (افتراضياً 45 يوماً) وتنظيف سجلاتها في قاعدة البيانات.';
    protected $usage       = 'downloads:cleanup [--key=SECRET] [--days=45] [--dry]';
    protected $options     = [
        '--key'  => 'مفتاح الكرون (يُؤخذ افتراضياً من CRON_SECRET في البيئة).',
        '--days' => 'مدة الاحتفاظ بالأيام (افتراضياً 45).',
        '--dry'  => 'وضع المعاينة: يعرض ما سيُحذف دون حذف فعلي.',
    ];

    public function run(array $params)
    {
        $key  = CLI::getOption('key') ?: (getenv('CRON_SECRET') ?: '');
        $days = CLI::getOption('days');
        $days = ($days !== null && $days !== '') ? (int) $days : null;
        $dry  = CLI::getOption('dry') ? 1 : 0;

        // DownloadsCleanupRunner::out() يطبع نتيجة JSON في وضع CLI.
        (new DownloadsCleanupRunner())->run((string) $key, $days, $dry);
    }
}

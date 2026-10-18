<?php
declare(strict_types=1);

namespace App\Controllers\Cron;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use Config\Database;

/**
 * DownloadsCleanupRunner
 * يحذف ملفات المهام المُولَّدة داخل public/downloads/ الأقدم من مدة الاحتفاظ
 * (افتراضياً 45 يوماً) بناءً على تاريخ تعديل الملف على القرص، ثم ينظّف سجلاتها
 * المرتبطة في قاعدة البيانات لتجنّب روابط تنزيل مكسورة:
 *   - user_task_files (RESTRICT ⇒ تُحذف أولاً)
 *   - task_files      (ON DELETE CASCADE — تُحذف صراحةً أيضاً)
 *   - ai_jobs.file_id (ON DELETE SET NULL — يُفرَّغ صراحةً أيضاً)
 *   - files           (السجل المركزي — يُحذف أخيراً)
 *
 * مجلد القوالب الحقيقي هو public/templates/ (خارج downloads/) فلا يتأثر؛
 * ومع ذلك نستبعد أي مجلد فرعي محميّ داخل downloads/ احترازاً (PROTECTED_SUBDIRS).
 *
 * HTTP: GET /cron/downloads-cleanup/run?key=SECRET[&days=45][&dry=1]
 * CLI : php spark downloads:cleanup [--key=SECRET] [--days=45] [--dry]
 */
class DownloadsCleanupRunner extends BaseController
{
    use ResponseTrait;

    /** مدة الاحتفاظ الافتراضية بالأيام. */
    private const DEFAULT_RETENTION_DAYS = 45;

    /** مجلدات فرعية داخل downloads/ لا تُحذف إطلاقاً (قوالب… إلخ). */
    private const PROTECTED_SUBDIRS = ['templates'];

    /**
     * @param string|null $keyOverride  المفتاح (يُمرَّر من CLI؛ في الويب يُقرأ من ?key=).
     * @param int|null    $daysOverride مدة الاحتفاظ بالأيام (CLI)؛ في الويب من ?days=.
     * @param int|null    $dryOverride  1 = معاينة بلا حذف (CLI)؛ في الويب من ?dry=.
     */
    public function run(?string $keyOverride = null, ?int $daysOverride = null, ?int $dryOverride = null)
    {
        // 🔐 المفتاح — موحّد مع بقية الكرون (CRON_SECRET)
        $providedKey = $keyOverride;
        if ($providedKey === null && isset($this->request) && method_exists($this->request, 'getGet')) {
            $providedKey = (string) ($this->request->getGet('key') ?? '');
        }
        $secret = (string) (getenv('CRON_SECRET') ?: '');
        if ($providedKey === null || $providedKey === '' || $secret === '' || $providedKey !== $secret) {
            return $this->out(['ok' => false, 'error' => 'Invalid cron key.'], 403);
        }

        // ⚙️ مدة الاحتفاظ: باراميتر > ?days= > متغيّر بيئة > الافتراضي
        $days = $daysOverride;
        if ($days === null && isset($this->request) && method_exists($this->request, 'getGet')) {
            $q = $this->request->getGet('days');
            $days = ($q !== null && $q !== '') ? (int) $q : null;
        }
        if ($days === null) {
            $env  = getenv('DOWNLOADS_RETENTION_DAYS');
            $days = ($env !== false && $env !== '') ? (int) $env : self::DEFAULT_RETENTION_DAYS;
        }
        if ($days < 1) {
            $days = self::DEFAULT_RETENTION_DAYS;
        }

        // 🧪 وضع المعاينة (لا حذف فعلي)
        $dry = $dryOverride;
        if ($dry === null && isset($this->request) && method_exists($this->request, 'getGet')) {
            $dry = (int) ($this->request->getGet('dry') ?? 0);
        }
        $dry = ((int) $dry === 1) ? 1 : 0;

        @set_time_limit(300);
        @ini_set('max_execution_time', '300');
        ignore_user_abort(true);

        $baseDir = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'downloads';
        if (!is_dir($baseDir)) {
            return $this->out(['ok' => true, 'message' => 'No downloads directory.', 'base' => $baseDir]);
        }

        $cutoff = time() - ($days * 86400);

        $scanned      = 0;
        $deletedFiles = 0;
        $freedBytes   = 0;
        $dbRows       = 0;
        $errors       = [];

        $db               = Database::connect();
        $hasFiles         = $this->tableExists($db, 'files');
        $hasUserTaskFiles = $this->tableExists($db, 'user_task_files');
        $hasTaskFiles     = $this->tableExists($db, 'task_files');
        $hasAiJobs        = $this->tableExists($db, 'ai_jobs');

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $abs = $fileInfo->getPathname();

            // المسار النسبي من جذر downloads/ (بفواصل أمامية لمطابقة قيم DB)
            $relFromBase = ltrim(str_replace('\\', '/', substr($abs, strlen($baseDir))), '/');

            // تخطّي المجلدات المحميّة (مثل templates)
            $topSeg = strtolower(explode('/', $relFromBase)[0] ?? '');
            if ($topSeg !== '' && in_array($topSeg, self::PROTECTED_SUBDIRS, true)) {
                continue;
            }

            $scanned++;

            $mtime = (int) @$fileInfo->getMTime();
            if ($mtime === 0 || $mtime > $cutoff) {
                continue; // غير موجود التاريخ أو حديث بما يكفي
            }

            $size    = (int) @$fileInfo->getSize();
            $relPath = 'downloads/' . $relFromBase; // كما يُخزَّن في files.file_path

            if ($dry) {
                $deletedFiles++;
                $freedBytes += $size;
                continue;
            }

            // 1) حذف الملف من القرص
            if (!@unlink($abs)) {
                $errors[] = 'unlink failed: ' . $relPath;
                continue;
            }
            $deletedFiles++;
            $freedBytes += $size;

            // 2) تنظيف سجلات قاعدة البيانات المرتبطة
            if ($hasFiles) {
                try {
                    $rows = $db->table('files')
                        ->select('id')
                        ->whereIn('file_path', [$relPath, '/' . $relPath])
                        ->get()->getResultArray();

                    foreach ($rows as $r) {
                        $fid = (int) $r['id'];
                        if ($fid <= 0) {
                            continue;
                        }
                        // الأبناء أولاً (user_task_files بدون ON DELETE ⇒ RESTRICT)
                        if ($hasUserTaskFiles) {
                            $db->table('user_task_files')->where('file_id', $fid)->delete();
                        }
                        if ($hasTaskFiles) {
                            $db->table('task_files')->where('file_id', $fid)->delete();
                        }
                        if ($hasAiJobs) {
                            $db->table('ai_jobs')->where('file_id', $fid)->update(['file_id' => null]);
                        }
                        // السجل المركزي أخيراً — مغلّف احترازاً ضد قيود FK أخرى (fk_srf_file_v1)
                        try {
                            $db->table('files')->where('id', $fid)->delete();
                            $dbRows++;
                        } catch (\Throwable $eDel) {
                            $errors[] = 'files row delete failed for #' . $fid . ': '
                                . mb_substr($eDel->getMessage(), 0, 160);
                        }
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'db cleanup failed for ' . $relPath . ': '
                        . mb_substr($e->getMessage(), 0, 160);
                }
            }
        }

        $result = [
            'ok'              => true,
            'dry_run'         => (bool) $dry,
            'retention_days'  => $days,
            'scanned'         => $scanned,
            'deleted_files'   => $deletedFiles,
            'freed_bytes'     => $freedBytes,
            'freed_mb'        => round($freedBytes / 1048576, 2),
            'db_rows_cleaned' => $dbRows,
            'error_count'     => count($errors),
            'errors'          => array_slice($errors, 0, 50),
        ];

        log_message('info', 'DownloadsCleanup: ' . json_encode(
            ['dry' => $dry, 'days' => $days, 'deleted' => $deletedFiles, 'mb' => $result['freed_mb'], 'db' => $dbRows, 'err' => count($errors)],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));

        return $this->out($result);
    }

    protected function tableExists($db, string $table): bool
    {
        try {
            return (bool) $db->tableExists($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** إخراج JSON آمن للويب والـ CLI (نفس نمط بقية متحكمات الكرون). */
    protected function out(array $data, int $status = 200)
    {
        if (is_cli()) {
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            return $data;
        }
        if (isset($this->response)) {
            return $this->response->setStatusCode($status)->setJSON($data);
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $data;
    }
}

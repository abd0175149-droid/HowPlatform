<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * ServiceFileStorageModel
 * - يتعامل مع جدول files (بدون لمس ServiceRequestFileModel الموجود)
 * - يوفر حذفًا فيزيائيًا آمنًا + حذف سجل الملف.
 */
class ServiceFileStorageModel extends Model
{
    protected $table            = 'files';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;

    /** لا نُمكّن SoftDeletes هنا لأن جدول files غالبًا لا يملك deleted_at */
    protected $useSoftDeletes   = false;

    /** NOTE: حدّث القائمة لو عندك أعمدة إضافية */
    protected $allowedFields    = [
        'file_name',
        'file_path',        // مسار نسبي داخل مجلد الرفع
        'mime_type',
        'file_size_bytes',
        'uploader_id',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = false;

    /** جذر الرفع (Hostinger: public_html/uploads/service) — يمكن ضبطه من .env */
    protected string $baseDir;

    public function __construct(?\CodeIgniter\Database\ConnectionInterface $db = null, ?\CodeIgniter\Validation\ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);
        // SERVICE_UPLOAD_BASE=/home/USER/public_html/uploads/service
        $this->baseDir = rtrim((string) (env('SERVICE_UPLOAD_BASE') ?: FCPATH . 'uploads/service'), '/\\');
    }

    /**
     * حذف آمن لملف واحد:
     * 1) حذف فيزيائي (إن وُجد وكان المسار آمنًا)
     * 2) حذف سجل الملف من جدول files
     */
    public function deleteOneSafe(int $fileId): bool
    {
        $row = $this->find($fileId);
        if (!$row) {
            return false;
        }

        $safePath = $this->resolveSafePath((string) ($row['file_path'] ?? ''));
        if ($safePath !== null && is_file($safePath)) {
            try {
                @chmod($safePath, 0644);
                @unlink($safePath);
            } catch (\Throwable $e) {
                log_message('error', 'deleteOneSafe: unlink failed: {err}', ['err' => $e->getMessage()]);
                // لا نفشل العملية بسبب حذف فيزيائي تعذّر (سنسير ونحذف السجل)
            }
        } elseif ($safePath === null) {
            // مسار غير آمن/خارج الجذر — نتجاهل الحذف الفيزيائي ونستمر بحذف السجل
            log_message('warning', 'deleteOneSafe: unsafe path skipped for file_id={id}', ['id' => $fileId]);
        }

        return (bool) $this->delete($fileId, true); // hard delete
    }

    /**
     * يحوّل مسارًا نسبيًا إلى مسار فيزيائي آمن داخل baseDir
     * يعيد null إن كان خارج النطاق أو يحوي Traversal.
     */
    protected function resolveSafePath(string $relative): ?string
    {
        $relative = ltrim($relative, '/\\');

        // منع أي محاولة خروج
        if ($relative === '' || strpos($relative, '..') !== false || str_starts_with($relative, '/') || str_starts_with($relative, '\\')) {
            return null;
        }

        $full    = $this->baseDir . DIRECTORY_SEPARATOR . $relative;
        $real    = realpath($full) ?: $full;
        $baseReal = realpath($this->baseDir) ?: $this->baseDir;

        $norm = static function (string $p): string {
            $p = str_replace(['\\', '//'], '/', $p);
            return rtrim($p, '/');
        };

        if (! str_starts_with($norm($real) . '/', $norm($baseReal) . '/')) {
            return null;
        }

        return $real;
    }
}

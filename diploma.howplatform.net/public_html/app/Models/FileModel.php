<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class FileModel extends Model
{
    protected $table         = 'files';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'file_name',
        'file_path',
        'mime_type',
        'file_size_bytes',
        'uploader_id',
        'description',
        'uploaded_at',
        'is_premium',
        'allow_public_view',
    ];

    protected $validationRules = [
        'file_name'          => 'required|string',
        'file_path'          => 'required|string',
        'mime_type'          => 'permit_empty|string',
        'file_size_bytes'    => 'permit_empty|integer',
        'uploader_id'        => 'permit_empty|integer',
        'description'        => 'permit_empty|string',
        'is_premium'         => 'permit_empty|in_list[0,1]',
        'allow_public_view'  => 'permit_empty|in_list[0,1]',
    ];

    /**
     * helper: هل المسار مطلق؟
     */
    private function isAbsolute(string $path): bool
    {
        // ويندوز/لينكس
        return str_starts_with($path, '/')
            || preg_match('~^[A-Za-z]:\\\\~', $path) === 1;
    }

    /**
     * تحويل المسار المخزّن إلى مسار مطلق على الخادم
     * - يدعم القيم القديمة (مطلق) والجديدة (نسبي تحت public_html أو writable/)
     * - لا يكسر منطق الأدمن لأنه يوسّع فقط
     */
    public function toAbsolutePath(string $storedPath): string
    {
        $p = trim($storedPath);

        // 1) إن كان مطلقًا ويشير لملف موجود → أعده كما هو
        if ($this->isAbsolute($p) && is_file($p)) {
            return $p;
        }

        // 2) أزل أي فواصل أولية
        $p = ltrim(str_replace(['\\', '//'], ['/', '/'], $p), '/');

        // 3) إن كان يبدأ بـ writable/ → ابنِه على WRITEPATH
        if (str_starts_with($p, 'writable/')) {
            $abs = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . substr($p, strlen('writable/'));
            if (is_file($abs)) {
                return $abs;
            }
        }

        // 4) جرّب تحت public_html (FCPATH)
        $absPublic = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . $p;
        if (is_file($absPublic)) {
            return $absPublic;
        }

        // 5) جرّب تحت WRITEPATH مباشرة (في حال المخزّن كان نسبيًا دون "writable/")
        $absWritable = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . $p;
        if (is_file($absWritable)) {
            return $absWritable;
        }

        // 6) fallback: إن كان مطلقًا لكن الملف غير موجود (حالة حدّية)
        if ($this->isAbsolute($storedPath)) {
            return $storedPath;
        }

        // 7) وأخيرًا، أعد المسار على FCPATH كحل أخير
        return $absPublic;
    }

    /**
     * حذف الملف فعليًا من القرص (يدعم جميع الأشكال)
     */
    public function deletePhysical(string $storedPath): bool
    {
        $abs = $this->toAbsolutePath($storedPath);
        return is_file($abs) ? @unlink($abs) : false;
    }
}

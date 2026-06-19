<?php

declare(strict_types=1);

namespace App\Controllers\Students;

use App\Controllers\BaseController;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;

class FileViewController extends BaseController
{
    /** @var BaseConnection */
    protected $db;

    public function __construct()
    {
        $this->db = db_connect();
    }

    /**
     * جلب user_id من السشن بعدة مفاتيح ممكنة.
     */
    private function currentUserId(): int
    {
        $s = session();
        $candidates = [
            $s->get('user_id'),
            $s->get('auth_user.user_id'),
            $s->get('auth_user.id'),
            $s->get('user.id'),
            $s->get('student.user_id'),
        ];
        foreach ($candidates as $v) {
            $id = (int) ($v ?? 0);
            if ($id > 0) {
                return $id;
            }
        }
        return 0;
    }

    /**
     * طريقة قديمة تعتمد على ?id=... (للإبقاء على التوافق إن كان هناك روابط قديمة).
     * GET /file-view?id={file_id}&disposition=inline|attachment
     */
    public function show(): ResponseInterface
    {
        $fileId = (int) ($this->request->getGet('id') ?? 0);
        $forceDisposition = (string) $this->request->getGet('disposition');
        return $this->serveFileById($fileId, $forceDisposition);
    }

    /**
     * عرض الملف عبر مسار RESTFUL متوافق مع واجهة الطلاب:
     * GET /students/service-requests/file/{id}?disposition=inline|attachment
     *
     * - يدعم البث (Range) للفيديو/الصوت.
     * - inline للصور افتراضياً، وباقي الأنواع attachment إلا إذا طُلِب inline صراحة.
     */
    public function view(int $id): ResponseInterface
    {
        $forceDisposition = (string) $this->request->getGet('disposition');
        return $this->serveFileById($id, $forceDisposition);
    }

    /**
     * تنزيل الملف كـ attachment صريح:
     * GET /students/service-requests/file/{id}/download
     */
    public function download(int $id): ResponseInterface
    {
        return $this->serveFileById($id, 'attachment');
    }

    /**
     * الدالة المركزية التي تتحقق من ملكية الملف ثم تبثه/تعرضه بالـ disposition المطلوب.
     */
    private function serveFileById(int $fileId, ?string $forceDisposition = null): ResponseInterface
    {
        if ($fileId <= 0) {
            return $this->response->setStatusCode(400)->setBody('Bad request');
        }

        $uid = $this->currentUserId();

        // ===== جلب بيانات الملف مع تحقق الملكية =====
        // المنطق:
        // 1) allow_public_view=1 → مسموح بدون تحقق ملكية.
        // 2) غير ذلك → يجب أن يكون عبر service_request_files مرتبطًا بطلب user_id = $uid.
        $sql = "
            SELECT
                f.id,
                f.file_name,
                f.file_path,
                f.mime_type,
                f.file_size_bytes,
                f.allow_public_view,
                sr.user_id AS owner_user_id
            FROM files f
            LEFT JOIN service_request_files srf ON srf.file_id = f.id
            LEFT JOIN service_requests sr       ON sr.id = srf.service_request_id
            WHERE f.id = ?
            LIMIT 1
        ";
        $row = $this->db->query($sql, [$fileId])->getRowArray();

        if (!$row) {
            return $this->response->setStatusCode(404)->setBody('File not found');
        }

        $isPublic = (int) ($row['allow_public_view'] ?? 0) === 1;
        $ownerId  = (int) ($row['owner_user_id'] ?? 0);

        if (!$isPublic) {
            if ($uid <= 0) {
                return $this->response->setStatusCode(401)->setBody('Unauthorized');
            }
            if ($ownerId !== $uid) {
                return $this->response->setStatusCode(403)->setBody('Forbidden');
            }
        }

        $path = (string) $row['file_path'];
        $name = (string) $row['file_name'];
        $mime = (string) ($row['mime_type'] ?: 'application/octet-stream');

        if ($path === '' || !is_file($path)) {
            return $this->response->setStatusCode(404)->setBody('File missing on disk');
        }

        // تحديد أسلوب العرض (inline/attachment)
        $forceDisposition = strtolower((string) $forceDisposition);
        $isImage = str_starts_with($mime, 'image/');
        $disposition = ($forceDisposition === 'inline')
            ? 'inline'
            : (($forceDisposition === 'attachment') ? 'attachment' : ($isImage ? 'inline' : 'attachment'));

        // بث/إرسال الملف مع دعم Range للفيديو/الصوت
        return $this->streamFile($path, $name, $mime, $disposition);
    }

    /**
     * بث الملف مع دعم Range (مهم للفيديو/الصوت)، أو إرسال كامل المحتوى للأنواع الأخرى.
     */
    private function streamFile(string $path, string $name, string $mime, string $disposition): ResponseInterface
    {
        $filesize = filesize($path);
        $safeName = $this->safeContentDispositionFilename($name);

        // إعداد هيدرز أساسية
        $this->response->setHeader('Content-Type', $mime);
        $this->response->setHeader('Accept-Ranges', 'bytes');
        $this->response->setHeader('Content-Disposition', $disposition . '; filename="' . $safeName . '"');

        $rangeHeader = (string) ($this->request->getHeaderLine('Range') ?? '');
        $start = 0;
        $end   = $filesize > 0 ? $filesize - 1 : 0;
        $status = 200;

        // ===== دعم Range للفيديو/الصوت =====
        // مثال: Range: bytes=0- or bytes=1000-2000
        if ($rangeHeader !== '' && preg_match('/bytes=(\d*)-(\d*)/i', $rangeHeader, $m)) {
            $rangeStart = $m[1] !== '' ? (int) $m[1] : 0;
            $rangeEnd   = $m[2] !== '' ? (int) $m[2] : $end;

            // تحقق صحة النطاق
            if ($rangeStart > $rangeEnd || $rangeEnd >= $filesize) {
                // نطاق غير صالح
                $this->response->setHeader('Content-Range', "bytes */{$filesize}");
                return $this->response->setStatusCode(416); // Range Not Satisfiable
            }

            $start = $rangeStart;
            $end   = $rangeEnd;
            $status = 206; // Partial Content

            $contentLength = $end - $start + 1;
            $this->response->setHeader('Content-Range', "bytes {$start}-{$end}/{$filesize}");
            $this->response->setHeader('Content-Length', (string) $contentLength);
        } else {
            // إرسال كامل الملف
            $this->response->setHeader('Content-Length', (string) $filesize);
        }

        // فتح الملف والبث على أجزاء
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return $this->response->setStatusCode(500)->setBody('Failed to open file');
        }

        // تحريك المؤشر للبداية المطلوبة في حالة Range
        if ($start > 0) {
            fseek($fh, $start);
        }

        $this->response->setStatusCode($status);

        // ملاحظة: عند التفريغ اليدوي لا نستخدم setBody لاحقًا
        // اختر حجم chunk مناسب (8192/1048576 حسب Hostinger)
        $chunkSize = 8192;

        while (!feof($fh)) {
            $pos = ftell($fh);
            if ($pos === false) {
                break;
            }
            if ($pos > $end) {
                break;
            }
            $remaining = ($end - $pos) + 1;
            $readLen   = ($remaining < $chunkSize) ? $remaining : $chunkSize;
            $buffer    = fread($fh, $readLen);
            if ($buffer === false) {
                break;
            }
            echo $buffer;
            // تفادي امتلاء البفر
            @ob_flush();
            flush();
        }

        fclose($fh);
        return $this->response;
    }

    /**
     * تجهيز اسم الملف ليلائم Content-Disposition بأمان.
     */
    private function safeContentDispositionFilename(string $name): string
    {
        // إزالة محارف تحكم/اقتباسات قد تسبب مشاكل في الهيدر
        $name = str_replace(['"', "\r", "\n"], ['\'', '', ''], $name);
        // إن كان فارغًا لأي سبب
        if ($name === '') {
            $name = 'download';
        }
        return $name;
    }
}

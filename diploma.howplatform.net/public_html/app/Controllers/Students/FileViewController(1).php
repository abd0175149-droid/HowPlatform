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
     * GET /file-view?id={file_id}&disposition=inline|attachment
     *
     * - يتحقق أن الملف مرتبط بواحد من طلبات المستخدم عبر service_request_files -> service_requests.
     * - إن كان allow_public_view = 1 يسمح بالعرض للجميع دون التحقق (اختياري حسب تصميمك الحالي).
     * - الصور تُعرض inline، وباقي الأنواع attachment افتراضيًا (يمكن إجبار inline عبر &disposition=inline).
     */
    public function show(): ResponseInterface
    {
        $fileId = (int) ($this->request->getGet('id') ?? 0);
        if ($fileId <= 0) {
            return $this->response->setStatusCode(400)->setBody('Bad request');
        }

        $uid = $this->currentUserId();

        // ===== جلب بيانات الملف مع تحقق الملكية =====
        // المنطق:
        // 1) لو allow_public_view=1 → مسموح بدون تحقق ملكية.
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
            // يجب أن يكون صاحب الطلب أو لديك لاحقًا أدوار إدارية (خارج نطاقنا الآن)
            if ($ownerId !== $uid) {
                return $this->response->setStatusCode(403)->setBody('Forbidden');
            }
        }

        $path = (string) $row['file_path'];
        $name = (string) $row['file_name'];
        $mime = (string) $row['mime_type'];

        // تحقّق المسار (لا نسمح بأي traversal — نخزّن مسارًا مطلقًا لدينا)
        if ($path === '' || !is_file($path)) {
            return $this->response->setStatusCode(404)->setBody('File missing on disk');
        }

        // تحديد أسلوب العرض
        $forceDisposition = strtolower((string) $this->request->getGet('disposition'));
        $isImage = str_starts_with($mime, 'image/');
        $disposition = ($forceDisposition === 'inline')
            ? 'inline'
            : ($isImage ? 'inline' : 'attachment');

        // إعداد الهيدرز
        $this->response->setHeader('Content-Type', $mime ?: 'application/octet-stream');
        $this->response->setHeader('Content-Length', (string) filesize($path));
        // ملاحظة: اسم الملف يفضّل ترميزه بشكل آمن في Content-Disposition
        $safeName = $this->safeContentDispositionFilename($name);
        $this->response->setHeader('Content-Disposition', $disposition . '; filename="' . $safeName . '"');

        // لمنع التخزين المؤقت إذا رغبت
        // $this->response->setHeader('Cache-Control', 'private, max-age=0, no-store');

        // إرسال الملف (Hostinger لا يدعم X-Sendfile افتراضيًا، نقرأه يدويًا)
        // CI4 يسمح بـ setFile() لكن لدينا مسار خارج FCPATH، سنقرأه يدويًا:
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return $this->response->setStatusCode(500)->setBody('Failed to open file');
        }

        // تفريغ الإخراج مباشرة (stream)
        while (!feof($fh)) {
            $chunk = fread($fh, 8192);
            if ($chunk === false) {
                break;
            }
            echo $chunk;
            // فلاش للمخرجات الطويلة
            if (function_exists('fastcgi_finish_request')) {
                // لا نريد إنهاء الطلب مبكرًا هنا، فقط نضمن التدفق
            }
        }
        fclose($fh);

        // IMPORTANT: عند الطباعة اليدوية، لا تستخدم setBody لاحقًا
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

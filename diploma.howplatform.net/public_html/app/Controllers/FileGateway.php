<?php
declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class FileGateway extends BaseController
{
    /** موقع جذور الملفات الفعلي على الاستضافة (Hostinger) */
    private string $basePublicDir;

    public function __construct()
    {
        // في Hostinger: public_html/ عادةً = FCPATH
        $this->basePublicDir = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * GET /api/files/{id}/check?action=view|download
     * فحص سريع للسماحية وإرجاع الكوتا/السبب للواجهة.
     */
    public function check(int $fileId): ResponseInterface
    {
        $file = $this->fetchFile($fileId);
        if (!$file) {
            return $this->failNotFound('File not found');
        }

        $action = strtolower((string) $this->request->getGet('action') ?: 'view');
        $isDownload = $action === 'download';

        $userId = $this->resolveUserId();
        $policy = service('fileAccessPolicy');
        $check  = $policy->canAccess($userId, $file, $isDownload);

        return $this->response->setJSON([
            'status' => $check['allowed'] ? 'ok' : 'fail',
            'allowed' => $check['allowed'],
            'reason'  => $check['reason'] ?? null,
            'caps'    => $check['caps'] ?? null,
        ]);
    }

    /**
     * GET /api/files/{id}/stream
     * بث الصوت/الفيديو مع دعم Range (لا يُسجَّل كتنزيل).
     */
    public function stream(int $fileId): ResponseInterface
    {
        $file = $this->fetchFile($fileId);
        if (!$file) {
            return $this->failNotFound('File not found');
        }

        // فحص السماحية للعرض (وليس تنزيل)
        $userId = $this->resolveUserId();
        $policy = service('fileAccessPolicy');
        $check  = $policy->canAccess($userId, $file, false);

        if (!$check['allowed']) {
            return $this->response->setStatusCode(403)->setJSON([
                'status'  => 'fail',
                'reason'  => $check['reason'],
                'message' => 'غير مسموح بعرض هذا المحتوى حالياً',
            ]);
        }

        $absPath = $this->resolveAbsolutePath($file['file_path']);
        if ($absPath === null || !is_file($absPath)) {
            return $this->failNotFound('File not found on disk');
        }

        $mime  = $file['mime_type'] ?: mime_content_type($absPath);
        $size  = (int) filesize($absPath);

        $this->response->setHeader('Content-Type', $mime);
        $this->response->setHeader('Accept-Ranges', 'bytes');
        $this->response->noCache();

        $range = $this->request->getHeaderLine('Range');
        if ($range && preg_match('/bytes=(\d+)-(\d+)?/', $range, $m)) {
            $start = (int) $m[1];
            $end   = isset($m[2]) ? (int) $m[2] : $size - 1;
            if ($start > $end || $start >= $size) {
                // نطاق غير صالح
                return $this->response->setStatusCode(416)
                    ->setHeader('Content-Range', "bytes */{$size}");
            }
            $length = $end - $start + 1;

            $this->response->setStatusCode(206);
            $this->response->setHeader('Content-Length', (string) $length);
            $this->response->setHeader('Content-Range', "bytes {$start}-{$end}/{$size}");

            $this->outputFileChunk($absPath, $start, $length);
            return $this->response; // تم إرسال البث
        }

        // بدون Range: أرسل الملف كاملاً
        $this->response->setHeader('Content-Length', (string) $size);
        $this->outputFileChunk($absPath, 0, $size);
        return $this->response;
    }

    /**
     * GET /api/files/{id}/download
     * تنزيل الملف مع فرض السقوف اليومية والشهرية وتسجيل العملية.
     * ملاحظة: يتطلب تسجيل دخول لتطبيق سقوف المستخدم.
     */
  public function download(int $fileId): ResponseInterface
{
    $file = $this->fetchFile($fileId);
    if (!$file) {
        return $this->failNotFound('File not found');
    }

    $userId = $this->resolveUserId();
    if ($userId <= 0) {
        return $this->response->setStatusCode(401)->setJSON([
            'status'  => 'fail',
            'reason'  => 'login_required',
            'message' => 'الرجاء تسجيل الدخول لتنزيل الملفات.',
        ]);
    }

    $policy = service('fileAccessPolicy');
    $check  = $policy->canAccess($userId, $file, true);
    if (!$check['allowed']) {
        return $this->response->setStatusCode(403)->setJSON([
            'status'  => 'fail',
            'reason'  => $check['reason'],
            'message' => 'غير مسموح بتنزيل هذا الملف حالياً',
            'caps'    => $check['caps'] ?? null,
        ]);
    }

    $absPath = $this->resolveAbsolutePath($file['file_path']);
    if ($absPath === null || !is_file($absPath)) {
        return $this->failNotFound('File not found on disk');
    }

    $size = (int) filesize($absPath);

    // 🕘 خزّن وقت التنزيل بمنطقة Asia/Amman (متناسق مع FileAccessPolicy)
    $nowAmman = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Amman'));

    db_connect()->table('file_downloads')->insert([
        'user_id'       => $userId,
        'file_id'       => (int) $file['id'],
        'bytes_sent'    => $size,
        'ip_address'    => $this->request->getIPAddress(),
        'user_agent'    => (string) $this->request->getUserAgent(),
        'downloaded_at' => $nowAmman->format('Y-m-d H:i:s'), // ← المهم
    ]);

    // تنزيل (Content-Disposition: attachment)
    $this->response->noCache();
    return $this->response->download($absPath, null)
        ->setFileName($file['file_name'] ?: basename($absPath));
}


    /**
     * جلب سجل الملف من قاعدة البيانات.
     */
    private function fetchFile(int $fileId): ?array
    {
        $row = db_connect()->table('files')
            ->select('id, file_name, file_path, mime_type, is_premium, allow_public_view')
            ->where('id', $fileId)
            ->get()->getRowArray();
        return $row ?: null;
    }

    /**
     * استنتاج رقم المستخدم من السيشن/الهيدر بشكل مرن.
     * عدّل المفاتيح هنا لتناسب نظام التوثيق عندك.
     */
    private function resolveUserId(): int
    {
        $s = session();
        $candidates = [
            (int) ($s->get('auth_user.id') ?? 0),
            (int) ($s->get('user.id') ?? 0),
            (int) ($s->get('user_id') ?? 0),
            (int) ($this->request->getHeaderLine('X-User-Id') ?: 0),
        ];
        foreach ($candidates as $id) {
            if ($id > 0) return $id;
        }
        return 0; // ضيف/غير مسجّل
    }

    /**
     * تحويل مسار DB النسبي إلى مسار فعلي آمن داخل مجلد public_html.
     * عدّل المنطق لو تخزّن خارج public.
     */
    private function resolveAbsolutePath(string $dbPath): ?string
    {
        $rel = ltrim($dbPath, '/\\');
        $abs = realpath($this->basePublicDir . $rel);
        if ($abs === false) return null;

        // حماية: لا نسمح بالخروج من مجلد public
        if (str_starts_with($abs, $this->basePublicDir)) {
            return $abs;
        }
        return null;
    }

    /**
     * إخراج جزء من الملف إلى الاستجابة (stream) بكفاءة.
     * يدعم الملفات الكبيرة دون تحميلها كاملة إلى الذاكرة.
     */
    private function outputFileChunk(string $path, int $start, int $length): void
    {
        $chunk = 1024 * 1024; // 1MB
        $sent  = 0;

        $fp = fopen($path, 'rb');
        if ($fp === false) {
            $this->response->setStatusCode(500)->setBody('Failed to open file.');
            return;
        }

        try {
            if ($start > 0) {
                fseek($fp, $start);
            }
            while (!feof($fp) && $sent < $length) {
                $read = min($chunk, $length - $sent);
                $buf  = fread($fp, $read);
                if ($buf === false) break;

                echo $buf;
                $sent += strlen($buf);
                if (function_exists('fastcgi_finish_request')) {
                    // لا تستخدمه أثناء الإرسال المتقطع
                }
                @ob_flush();
                flush();
                if (connection_status() !== CONNECTION_NORMAL) {
                    break;
                }
            }
        } finally {
            fclose($fp);
        }
    }
}

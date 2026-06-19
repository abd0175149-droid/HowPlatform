<?php
declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class PublicFileController extends BaseController
{
    /** مجلد public_html الفعلي */
    private string $basePublicDir;

    public function __construct()
    {
        $this->basePublicDir = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR;
    }
    public function accessCheck(): ResponseInterface
    {
    $userId = (int) $this->request->getPost('user_id');
    $fileId = (int) $this->request->getPost('file_id');
    $action = strtolower((string) $this->request->getPost('action') ?: 'view');
    $isDownload = $action === 'download';

    $file = db_connect()->table('files')
        ->select('id, mime_type, is_premium, allow_public_view')
        ->where('id', $fileId)
        ->get()->getRowArray();

    if (!$file) {
        return $this->response->setStatusCode(404)
            ->setJSON(['allowed'=>false,'reason'=>'file_not_found']);
    }

    $policy = new \App\Services\FileAccessPolicy(db_connect(), service('subscriptionService'));
    $check  = $policy->canAccess($userId, $file, $isDownload);

    return $this->response->setJSON($check);
}

    /**
     * عرض مباشر في المتصفح أو داخل التطبيق.
     * GET /api/public-files/{id}/view
     * (يدعم Range للبث)
     */
    public function view(int $fileId): ResponseInterface
    {
        return $this->serveFile($fileId, false);
    }

    /**
     * تنزيل الملف مباشرة.
     * GET /api/public-files/{id}/download
     */
    public function download(int $fileId): ResponseInterface
    {
        return $this->serveFile($fileId, true);
    }

    /**
     * الدالة المركزية: تجلب السجل وتبث أو تنزل الملف.
     */
    private function serveFile(int $fileId, bool $forceDownload): ResponseInterface
    {
        // جلب السجل من جدول files
        $file = db_connect()->table('files')
            ->select('id, file_name, file_path, mime_type')
            ->where('id', $fileId)
            ->get()->getRowArray();

        if (!$file) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'File not found']);
        }

        $absPath = $this->resolveAbsolutePath($file['file_path']);
        if ($absPath === null || !is_file($absPath)) {
            return $this->response->setStatusCode(404)->setJSON([
                'error' => 'File missing on disk',
                'expected_path' => $file['file_path'],
            ]);
        }

        $mime = $file['mime_type'] ?: mime_content_type($absPath);
        $size = filesize($absPath);

        $this->response->setHeader('Content-Type', $mime);
        $this->response->setHeader('Accept-Ranges', 'bytes');
        $this->response->noCache();

        // إذا كان طلب download → Content-Disposition: attachment
        $disposition = $forceDownload ? 'attachment' : (
            str_starts_with($mime, 'image/') ||
            str_starts_with($mime, 'video/') ||
            str_starts_with($mime, 'audio/') ||
            $mime === 'application/pdf'
                ? 'inline'
                : 'attachment'
        );
        $this->response->setHeader('Content-Disposition', $disposition . '; filename="' . $this->safeName($file['file_name']) . '"');

        // دعم Range (للفيديو والصوت)
        $range = $this->request->getHeaderLine('Range');
        if ($range && preg_match('/bytes=(\d+)-(\d+)?/', $range, $m)) {
            $start = (int) $m[1];
            $end   = isset($m[2]) ? (int) $m[2] : $size - 1;

            if ($start > $end || $start >= $size) {
                return $this->response->setStatusCode(416)
                    ->setHeader('Content-Range', "bytes */{$size}");
            }

            $length = $end - $start + 1;
            $this->response->setStatusCode(206);
            $this->response->setHeader('Content-Length', (string) $length);
            $this->response->setHeader('Content-Range', "bytes {$start}-{$end}/{$size}");
            $this->outputFileChunk($absPath, $start, $length);
            return $this->response;
        }

        // بدون Range → إرسال كامل الملف
        $this->response->setHeader('Content-Length', (string) $size);
        $this->outputFileChunk($absPath, 0, $size);
        return $this->response;
    }

    /** تأمين المسار */
    private function resolveAbsolutePath(string $dbPath): ?string
    {
        $rel = ltrim($dbPath, '/\\');
        $abs = realpath($this->basePublicDir . $rel);
        if ($abs === false) return null;
        if (str_starts_with($abs, $this->basePublicDir)) {
            return $abs;
        }
        return null;
    }

    /** بث Chunk */
    private function outputFileChunk(string $path, int $start, int $length): void
    {
        $chunk = 1024 * 1024; // 1MB
        $sent  = 0;
        $fp = fopen($path, 'rb');
        if ($fp === false) {
            echo 'Failed to open file';
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
                @ob_flush();
                flush();
                if (connection_status() !== CONNECTION_NORMAL) break;
            }
        } finally {
            fclose($fp);
        }
    }

    /** تنظيف الاسم للـ headers */
    private function safeName(?string $name): string
    {
        $name = $name ?: 'download';
        return str_replace(['"', "\r", "\n"], ['\'', '', ''], $name);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use App\Services\FileAccessPolicy;
use CodeIgniter\Database\BaseConnection;

class FilesController extends BaseController
{
    /** @var BaseConnection */
    protected $db;

    private ?FileAccessPolicy $filePolicy = null;

    public function __construct()
    {
        $this->db = db_connect();

        if (class_exists('\App\Services\SubscriptionService')) {
            try {
                $subscriptionService = service('subscriptionService');
            } catch (\Throwable $e) {
                $subscriptionService = null;
            }
            if (!$subscriptionService) {
                try {
                    $subscriptionService = new \App\Services\SubscriptionService();
                } catch (\Throwable $e) {
                    $subscriptionService = null;
                }
            }
            if ($subscriptionService) {
                $this->filePolicy = new FileAccessPolicy($this->db, $subscriptionService);
            }
        }
    }

    private function currentUserId(): int
    {
        $s = session();
        foreach ([
            $s->get('user_id'),
            $s->get('auth_user.user_id'),
            $s->get('auth_user.id'),
            $s->get('user.id'),
            $s->get('student.user_id'),
        ] as $v) {
            $id = (int) ($v ?? 0);
            if ($id > 0) return $id;
        }
        return 0;
    }

    private function json(array $payload, int $status = 200): ResponseInterface
    {
        return $this->response->setJSON($payload)->setStatusCode($status);
    }

    /** جرّب تحويل أي مسار مخزّن إلى مسار مطلق صالح على القرص */
    private function normalizeAbsolutePath(string $stored): string
    {
        $p = trim($stored);

        // 1) إن كان مطلقًا وموجودًا — استخدمه
        if ($p !== '' && ($p[0] === '/' || preg_match('#^[A-Za-z]:\\\\#', $p))) {
            if (is_file($p)) return $p;
        }

        // 2) لو بدأ بـ writable/… أو /writable/… → لحّقه على جذر المشروع
        $rel = ltrim($p, "/\\");
        if (stripos($rel, 'writable/') === 0) {
            $abs = rtrim(WRITEPATH, "/\\") . DIRECTORY_SEPARATOR . substr($rel, strlen('writable/'));
            if (is_file($abs)) return $abs;
            // احتمال أن القيمة كانت "writable/..." كاملة — جرّب إرجاع WRITEPATH + الباقي كما هو
            $abs2 = rtrim(FCPATH, "/\\") . DIRECTORY_SEPARATOR . $rel;
            if (is_file($abs2)) return $abs2;
        }

        // 3) لو المسار مخزّن نسبيًا أصلاً (بدون writable/) — جرّب لصقه على WRITEPATH
        $abs3 = rtrim(WRITEPATH, "/\\") . DIRECTORY_SEPARATOR . $rel;
        if (is_file($abs3)) return $abs3;

        // 4) كحل أخير: جرّب لصقه على FCPATH (public_html)
        $abs4 = rtrim(FCPATH, "/\\") . DIRECTORY_SEPARATOR . $rel;
        if (is_file($abs4)) return $abs4;

        return $p; // أعد الأصلي (قد لا يكون موجودًا)
    }

    /**
     * GET /file-view?id=123&disposition=inline|attachment
     */
    public function view(): ResponseInterface
    {
        $fileId      = (int) ($this->request->getGet('id') ?? 0);
        $disposition = strtolower((string) ($this->request->getGet('disposition') ?? 'inline'));
        if (!in_array($disposition, ['inline','attachment'], true)) {
            $disposition = 'inline';
        }

        if ($fileId <= 0) {
            return $this->json(['success'=>false,'message'=>'invalid id'], 400);
        }

        // جلب صف الملف
        $file = $this->db->table('files')->where('id', $fileId)->get()->getRowArray();
        if (!$file) {
            return $this->json(['success'=>false,'message'=>'not found'], 404);
        }

        $uid = $this->currentUserId();
        $isDownload = strtolower($disposition) === 'attachment';
        if ($this->filePolicy) {
            $policyFile = $file;
            $policyFile['id'] = (int) ($policyFile['id'] ?? $fileId);
            $access = $this->filePolicy->canAccess($uid, $policyFile, $isDownload);
            if (empty($access['allowed'])) {
                return $this->json(['success'=>false,'message'=>'forbidden','reason'=>$access['reason'] ?? null], 403);
            }
        }

        // صلاحيات
        $isPublic        = (int)($file['allow_public_view'] ?? 0) === 1;
        $isOwnerUploader = $uid > 0 && (int)($file['uploader_id'] ?? 0) === $uid;

        $isOwnerService = false;
        if ($uid > 0) {
            $row = $this->db->table('service_request_files srf')
                ->select('sr.user_id')
                ->join('service_requests sr', 'sr.id = srf.service_request_id', 'left')
                ->where('srf.file_id', $fileId)
                ->get()
                ->getRowArray();
            if ($row && (int)$row['user_id'] === $uid) {
                $isOwnerService = true;
            }
        }

        $role = (string) (session('auth_user.role') ?? '');
        $isStaff = in_array($role, ['admin','support','staff'], true);

        if (!$isPublic && !$isOwnerUploader && !$isOwnerService && !$isStaff) {
            return $this->json(['success'=>false,'message'=>'forbidden'], 403);
        }

        // تطبيع مسار القرص
        $stored   = (string) ($file['file_path'] ?? '');
        $absPath  = $this->normalizeAbsolutePath($stored);
        if ($absPath === '' || !is_file($absPath)) {
            log_message('error', "[file-view] missing file on disk id={$fileId} path_stored={$stored} path_used={$absPath}");
            return $this->json(['success'=>false,'message'=>'file missing'], 404);
        }

        // تحديد MIME
        $mime = (string) ($file['mime_type'] ?? '');
        if ($mime === '') {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = @finfo_file($finfo, $absPath) ?: 'application/octet-stream';
            @finfo_close($finfo);
        }

        $size     = filesize($absPath);
        $filename = (string) ($file['file_name'] ?? basename($absPath));

        $this->response->setHeader('Content-Type', $mime);
        $this->response->setHeader('Accept-Ranges', 'bytes');
        $this->response->setHeader('Content-Disposition', $disposition . '; filename="' . str_replace('"','\"',$filename) . '"');

        // دعم Range
        $range = $this->request->getHeaderLine('Range');
        if ($range && preg_match('/bytes=(\d*)-(\d*)/i', $range, $m)) {
            $start = ($m[1] !== '') ? (int)$m[1] : 0;
            $end   = ($m[2] !== '') ? (int)$m[2] : ($size - 1);
            if ($start > $end || $start >= $size) {
                $this->response->setStatusCode(416);
                $this->response->setHeader('Content-Range', "bytes */{$size}");
                return $this->response;
            }

            $length = $end - $start + 1;
            $this->response->setStatusCode(206);
            $this->response->setHeader('Content-Range', "bytes {$start}-{$end}/{$size}");
            $this->response->setHeader('Content-Length', (string)$length);

            $chunk = 1024 * 1024;
            $fh = fopen($absPath, 'rb');
            if ($fh === false) {
                return $this->json(['success'=>false,'message'=>'cannot open file'], 500);
            }
            try {
                fseek($fh, $start);
                $remaining = $length;
                while ($remaining > 0 && !feof($fh)) {
                    $read = ($remaining > $chunk) ? $chunk : $remaining;
                    $buffer = fread($fh, $read);
                    if ($buffer === false) break;
                    echo $buffer;
                    $remaining -= strlen($buffer);
                    @ob_flush();
                    flush();
                }
            } finally {
                fclose($fh);
            }
            return $this->response;
        }

        // بدون Range → نزّل/اعرض الملف كاملًا
        $this->response->setHeader('Content-Length', (string)$size);
        return $this->response
            ->download($absPath, null)
            ->setFileName($filename)
            ->setContentType($mime)
            ->setStatusCode(200);
    }
}

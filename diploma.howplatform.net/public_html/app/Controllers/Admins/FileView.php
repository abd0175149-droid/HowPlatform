<?php

declare(strict_types=1);

namespace App\Controllers\Admins;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * عرض/بث الملفات مع تفويض أدوار الإدمن
 * - role=1: وصول كامل
 * - role=2/4: فقط إن كان الطلب مُسنداً له (assigned_to = session user)
 * - يدعم Content-Disposition (inline/attachment)
 * - يدعم Range للبث (فيديو/صوت)
 */
class FileView extends BaseController
{
    public function view(): ResponseInterface
    {
        $db   = db_connect();
        $req  = $this->request;
        $id   = (int) ($req->getGet('id') ?? 0);
        $disp = strtolower((string) ($req->getGet('disposition') ?? 'attachment')) === 'inline' ? 'inline' : 'attachment';

        if ($id <= 0) {
            return $this->response->setStatusCode(400)->setBody('Bad id');
        }

        // ===== جلب الملف + الطلب المرتبط به (إن وجد) =====
        $row = $db->table('files f')
            ->select('f.*, srf.service_request_id, sr.assigned_to, sr.user_id AS request_user_id')
            ->join('service_request_files srf', 'srf.file_id = f.id', 'left')
            ->join('service_requests sr', 'sr.id = srf.service_request_id', 'left')
            ->where('f.id', $id)
            ->get()
            ->getRowArray();

        if (!$row) {
            return $this->response->setStatusCode(404)->setBody('Not found');
        }

        $filePath = (string) $row['file_path'];
        if (!is_file($filePath) || !is_readable($filePath)) {
            return $this->response->setStatusCode(404)->setBody('File missing');
        }

        // ===== تفويض الوصول لأدوار الإدمن =====
        $role   = (int) (session('role') ?? 0);
        $admin  = (int) (session('user_id') ?? session('auth_user.id') ?? 0);
        $allow  = false;

        if ($role === 1) {
            // مدير شامل
            $allow = true;
        } elseif ($role === 2 || $role === 4) {
            // يجب أن يكون مُكلفًا بنفس الطلب
            $allow = ((int) ($row['assigned_to'] ?? 0) === $admin);
        } else {
            // (اختياري) السماح للطالب مالك الطلب بتحميل الملف
            $studentId = (int) (session('student.user_id') ?? session('user_id') ?? 0);
            if ($studentId > 0 && (int) ($row['request_user_id'] ?? 0) === $studentId) {
                $allow = true;
            }
        }

        if (!$allow) {
            return $this->response->setStatusCode(403)->setBody('Forbidden');
        }

        // ===== إعداد الهيدر =====
        $mime = (string) ($row['mime_type'] ?: 'application/octet-stream');
        $name = (string) ($row['file_name'] ?: basename($filePath));
        $size = (int) filesize($filePath);

        $this->response->setHeader('Content-Type', $mime);
        $this->response->setHeader('Content-Length', (string) $size);
        $this->response->setHeader('Accept-Ranges', 'bytes');
        $this->response->setHeader('Content-Disposition', $disp . '; filename="' . addslashes($name) . '"');

        // ===== دعم Range للبث (فيديو/صوت) =====
        $range = $this->request->getServer('HTTP_RANGE');
        if ($range && preg_match('/bytes=(\d+)-(\d*)/i', $range, $m)) {
            $start = (int) $m[1];
            $end   = ($m[2] !== '') ? (int) $m[2] : ($size - 1);
            if ($start > $end || $end >= $size) {
                return $this->response->setStatusCode(416)
                    ->setHeader('Content-Range', "bytes */{$size}")
                    ->setBody('');
            }

            $length = ($end - $start + 1);
            $this->response->setStatusCode(206);
            $this->response->setHeader('Content-Range', "bytes {$start}-{$end}/{$size}");
            $this->response->setHeader('Content-Length', (string) $length);

            $fp = fopen($filePath, 'rb');
            if ($fp === false) {
                return $this->response->setStatusCode(500)->setBody('Open failed');
            }
            @fseek($fp, $start);
            $chunk = 8192;
            $sent  = 0;
            while (!feof($fp) && $sent < $length) {
                $read = ($length - $sent) > $chunk ? $chunk : ($length - $sent);
                $buf  = fread($fp, $read);
                if ($buf === false) break;
                echo $buf;
                $sent += strlen($buf);
                @flush();
            }
            fclose($fp);
            return $this->response;
        }

        // بدون Range — أرسل الملف كاملًا
        return $this->response->download($filePath, null)->setFileName($name)->setContentType($mime)->setStatusCode(200);
    }
}

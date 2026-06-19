<?php
// /app/Controllers/Admin/LibraryUpload.php
declare(strict_types=1);

namespace App\Controllers\Admins;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class LibraryUpload extends BaseController
{
    public function index()
    {
        return view('admins/pages/library_upload');
    }
/*
    public function store(): ResponseInterface
    {
        try {
            // استلام الملف من الفورم
            $file = $this->request->getFile('file');
            if (!$file || !$file->isValid()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'ok' => false,
                    'error' => 'الملف غير صالح أو غير موجود',
                ]);
            }

            // تجهيز الحقول
            $fields = [
                'grade_id'    => (string) $this->request->getPost('grade_id'),
                'subject_id'  => (string) $this->request->getPost('subject_id'),
                'category_id' => (string) $this->request->getPost('category_id'),
                'semester_id' => $this->request->getPost('semester_id') !== null ? (string) $this->request->getPost('semester_id') : '',
                'title'       => (string) ($this->request->getPost('title') ?? ''),
                'year_label'  => (string) ($this->request->getPost('year_label') ?? ''),
                'disk'        => (string) ($this->request->getPost('disk') ?? 'public'),
                'force_new'   => $this->request->getPost('force_new') ? '1' : '0',
            ];

            // إضافة الملف بـ \CURLFile (multipart صحيح)
            $fields['file'] = new \CURLFile(
                $file->getTempName(),
                $file->getClientMimeType() ?: 'application/octet-stream',
                $file->getClientName()
            );

            // إرسال إلى Laravel
            $apiBase = rtrim((string) env('LIBRARY_API_BASE'), '/');
            $ch = curl_init($apiBase . '/uploads');

            $headers = [
                'Accept: application/json',
                'X-Api-Key: ' . (string) env('LIBRARY_API_KEY'),
            ];

            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $fields,          // يضبط multipart/form-data تلقائيًا
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_FOLLOWLOCATION => true,
            ]);

            $body   = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err    = curl_error($ch);
            curl_close($ch);

            if ($body === false || $status < 100) {
                log_message('error', 'LibraryUpload cURL failed: ' . $err);
                return $this->response->setStatusCode(500)->setJSON([
                    'ok' => false,
                    'error' => 'Proxy upload failed (curl error)',
                    'message' => $err,
                ]);
            }

            return $this->response
                ->setStatusCode($status)
                ->setHeader('Content-Type', 'application/json')
                ->setBody($body);

        } catch (\Throwable $e) {
            log_message('error', 'LibraryUpload::store exception: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'ok' => false,
                'error' => 'Proxy upload failed (exception)',
            ]);
        }
    }
*/

    public function store(): ResponseInterface
    {
        try {
            // استلام الملف الرئيسي
            $file = $this->request->getFile('file');
            if (!$file || !$file->isValid()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'ok'    => false,
                    'error' => 'الملف غير صالح أو غير موجود',
                ]);
            }

            // استلام صورة المعاينة (اختيارية)
            $preview = $this->request->getFile('preview');

            // تجهيز الحقول النصية
            $fields = [
                'grade_id'    => (string) $this->request->getPost('grade_id'),
                'subject_id'  => (string) $this->request->getPost('subject_id'),
                'category_id' => (string) $this->request->getPost('category_id'),
                'semester_id' => $this->request->getPost('semester_id') !== null ? (string) $this->request->getPost('semester_id') : '',
                'title'       => (string) ($this->request->getPost('title') ?? ''),
                'year_label'  => (string) ($this->request->getPost('year_label') ?? ''),
                'disk'        => (string) ($this->request->getPost('disk') ?? 'public'),          // قرص تخزين الملف الرئيسي
                'preview_disk'=> (string) ($this->request->getPost('preview_disk') ?? 'public'),  // قرص تخزين صورة المعاينة
                'force_new'   => $this->request->getPost('force_new') ? '1' : '0',
            ];

            // إلحاق الملف الرئيسي بصيغة \CURLFile
            $fields['file'] = new \CURLFile(
                $file->getTempName(),
                $file->getClientMimeType() ?: 'application/octet-stream',
                $file->getClientName()
            );

            // إن وُجدت صورة معاينة صالحة نلحقها كذلك
            if ($preview && $preview->isValid()) {
                $fields['preview'] = new \CURLFile(
                    $preview->getTempName(),
                    $preview->getClientMimeType() ?: 'image/*',
                    $preview->getClientName()
                );
            }

            // إرسال إلى Laravel
            $apiBase = rtrim((string) env('LIBRARY_API_BASE'), '/');
            $ch = curl_init($apiBase . '/uploads');

            $headers = [
                'Accept: application/json',
                'X-Api-Key: ' . (string) env('LIBRARY_API_KEY'),
            ];

            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $fields, // multipart/form-data تلقائيًا
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_FOLLOWLOCATION => true,
            ]);

            $body   = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err    = curl_error($ch);
            curl_close($ch);

            if ($body === false || $status < 100) {
                log_message('error', 'LibraryUpload cURL failed: ' . $err);
                return $this->response->setStatusCode(500)->setJSON([
                    'ok'      => false,
                    'error'   => 'Proxy upload failed (curl error)',
                    'message' => $err,
                ]);
            }

            return $this->response
                ->setStatusCode($status)
                ->setHeader('Content-Type', 'application/json')
                ->setBody($body);

        } catch (\Throwable $e) {
            log_message('error', 'LibraryUpload::store exception: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'ok'    => false,
                'error' => 'Proxy upload failed (exception)',
            ]);
        }
    }

}

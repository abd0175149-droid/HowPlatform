<?php
// /app/Controllers/Admin/LibraryMeta.php
declare(strict_types=1);

namespace App\Controllers\Admins;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use RuntimeException;
use Throwable;

class LibraryMeta extends BaseController
{
    public function debug(): ResponseInterface
    {
        return $this->response->setJSON([
            'base'   => env('LIBRARY_API_BASE'),
            'hasKey' => env('LIBRARY_API_KEY') ? true : false,
            'curl'   => function_exists('curl_version'),
        ]);
    }

    /** 📌 المسار الأساس لمورد الملفات على الـ API البعيد (قابل للتهيئة من .env) */
    private function filesBasePath(): string
    {
        $path = (string) env('LIBRARY_API_FILES_PATH');
        return $path !== '' ? $path : '/uploads'; // الافتراضي /uploads (نمط REST: index/show/store/destroy)
    }

    /** ⚙️ GET قديم للمسارات التعريفية (grades/subjects/...) */
    private function remote(string $path): array
    {
        $client = Services::curlrequest(['timeout' => 30, 'http_errors' => false]);

        $base = rtrim((string) env('LIBRARY_API_BASE'), '/');
        if ($base === '') {
            throw new RuntimeException('LIBRARY_API_BASE is empty or not loaded.');
        }
        $url = $base . $path;

        $resp = $client->get($url, [
            'headers' => [
                'X-Api-Key' => (string) env('LIBRARY_API_KEY'),
                'Accept'    => 'application/json',
            ],
        ]);

        $body = (string) $resp->getBody();
        $json = json_decode($body, true);

        if ($json === null) {
            $json = ['ok' => false, 'error' => 'Remote returned non-JSON', 'status' => $resp->getStatusCode()];
        }

        return [$resp->getStatusCode(), $json];
    }

    /** 🔧 GET عام مع Query */
    private function remoteGet(string $path, array $query = []): array
    {
        $client = Services::curlrequest(['timeout' => 30, 'http_errors' => false]);
        $base = rtrim((string) env('LIBRARY_API_BASE'), '/');
        if ($base === '') {
            throw new RuntimeException('LIBRARY_API_BASE is empty or not loaded.');
        }
        $url = $base . $path;

        $resp = $client->get($url, [
            'headers' => [
                'X-Api-Key' => (string) env('LIBRARY_API_KEY'),
                'Accept'    => 'application/json',
            ],
            'query' => $query,
        ]);

        $body = (string) $resp->getBody();
        $json = json_decode($body, true);

        if ($json === null) {
            log_message(
                'error',
                'LibraryMeta::remoteGet non-JSON response. URL={url} Query={query} Status={status} Body={body}',
                [
                    'url'    => $url,
                    'query'  => json_encode($query, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'status' => $resp->getStatusCode(),
                    'body'   => mb_substr($body, 0, 1000),
                ]
            );
            $json = ['ok' => false, 'error' => 'Remote returned non-JSON', 'status' => $resp->getStatusCode()];
        }

        return [$resp->getStatusCode(), $json];
    }

    /** 🔧 DELETE عام */
    private function remoteDelete(string $path): array
    {
        $client = Services::curlrequest(['timeout' => 30, 'http_errors' => false]);
        $base = rtrim((string) env('LIBRARY_API_BASE'), '/');
        if ($base === '') {
            throw new RuntimeException('LIBRARY_API_BASE is empty or not loaded.');
        }
        $url = $base . $path;

        $resp = $client->delete($url, [
            'headers' => [
                'X-Api-Key' => (string) env('LIBRARY_API_KEY'),
                'Accept'    => 'application/json',
            ],
        ]);

        $body = (string) $resp->getBody();
        $json = json_decode($body, true);
        if ($json === null) {
            $json = ['ok' => false, 'error' => 'Remote returned non-JSON', 'status' => $resp->getStatusCode()];
        }

        return [$resp->getStatusCode(), $json];
    }

    /** 🧪 جرّب عدة مسارات وعدة صيغ للبارامترات حتى نجد استجابة سليمة */
    private function tryListEndpoints(array $filters): array
    {
        // مرشّحات بديلة للأسماء (grade_id|grade|gradeId ... إلخ)
        $variants = [
            array_filter([
                'grade_id'    => $filters['grade_id']    ?? null,
                'subject_id'  => $filters['subject_id']  ?? null,
                'semester_id' => $filters['semester_id'] ?? null,
            ], static fn($v) => $v !== null),
            array_filter([
                'grade'       => $filters['grade_id']    ?? null,
                'subject'     => $filters['subject_id']  ?? null,
                'semester_id' => $filters['semester_id'] ?? null,
            ], static fn($v) => $v !== null),
            array_filter([
                'gradeId'     => $filters['grade_id']    ?? null,
                'subjectId'   => $filters['subject_id']  ?? null,
                'semesterId'  => $filters['semester_id'] ?? null,
            ], static fn($v) => $v !== null),
        ];

        // مسارات محتملة (يُفضّل ضبط الصحيح من .env)
        $paths = array_values(array_unique([
            $this->filesBasePath(), // من .env أو /uploads
            '/uploads',
            '/files',
        ]));

        $lastStatus = null;
        $lastJson   = null;

        foreach ($paths as $p) {
            foreach ($variants as $q) {
                [$s, $j] = $this->remoteGet($p, $q);
                // نعتبر النجاح أي حالة < 400 (للسماح بـ 204 مثلاً)
                if ($s >= 200 && $s < 400) {
                    return [$s, $j];
                }
                $lastStatus = $s;
                $lastJson   = $j;
            }
        }

        // لم ننجح بأي توليفة
        log_message(
            'error',
            'LibraryMeta::tryListEndpoints failed. filters={filters} lastStatus={status} lastJson={json}',
            [
                'filters' => json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status'  => $lastStatus,
                'json'    => json_encode($lastJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]
        );

        // نعيد 200 مع رسالة ودّية حتى لا تسقط الواجهة، ونمرّر تفاصيل للتشخيص
        return [200, [
            'ok'           => false,
            'error'        => 'تعذر جلب القائمة من الخادم البعيد.',
            'proxy_status' => $lastStatus,
            'proxy_body'   => $lastJson,
            'data'         => [],
        ]];
    }

    /** /admin/library/meta/grades */
    public function grades(): ResponseInterface
    {
        try {
            [$s, $j] = $this->remote('/meta/grades');
            return $this->response->setStatusCode($s)->setJSON($j ?? ['ok' => false]);
        } catch (Throwable $e) {
            return $this->fail('فشل جلب الصفوف: ' . $e->getMessage(), 500);
        }
    }

    /** /admin/library/meta/grades/{id}/subjects */
    public function subjects(int $gradeId): ResponseInterface
    {
        try {
            [$s, $j] = $this->remote('/meta/grades/' . rawurlencode((string) $gradeId) . '/subjects');
            return $this->response->setStatusCode($s)->setJSON($j ?? ['ok' => false]);
        } catch (Throwable $e) {
            return $this->fail('فشل جلب المواد: ' . $e->getMessage(), 500);
        }
    }

    /** /admin/library/meta/semesters */
    public function semesters(): ResponseInterface
    {
        try {
            [$s, $j] = $this->remote('/meta/semesters');
            return $this->response->setStatusCode($s)->setJSON($j ?? ['ok' => false]);
        } catch (Throwable $e) {
            return $this->fail('فشل جلب الفصول: ' . $e->getMessage(), 500);
        }
    }

    /** /admin/library/meta/categories */
    public function categories(): ResponseInterface
    {
        try {
            [$s, $j] = $this->remote('/meta/categories');
            return $this->response->setStatusCode($s)->setJSON($j ?? ['ok' => false]);
        } catch (Throwable $e) {
            return $this->fail('فشل جلب التصنيفات: ' . $e->getMessage(), 500);
        }
    }

    /** ✅ /admin/library/files — جلب قائمة الملفات مع الفلاتر */
    public function files(): ResponseInterface
    {
        try {
            // 1) قراءة الفلاتر
            $gradeId    = trim((string) $this->request->getGet('grade_id'));
            $subjectId  = trim((string) $this->request->getGet('subject_id'));
            $semesterId = trim((string) $this->request->getGet('semester_id')); // نتوقع أرقام

            // 2) تطبيع الفصل لو وصل نصيًا
            if ($semesterId !== '' && !ctype_digit($semesterId)) {
                $map = [
                    'الفصل الاول'  => '1', 'الفصل الأول' => '1', 'first' => '1', '1' => '1',
                    'الفصل الثاني' => '2', 'second'      => '2', '2' => '2',
                ];
                $key        = mb_strtolower($semesterId, 'UTF-8');
                $semesterId = $map[$key] ?? '';
            }

            // 3) تجميع الفلاتر المفيدة
            $filters = array_filter([
                'grade_id'    => $gradeId    !== '' ? $gradeId    : null,
                'subject_id'  => $subjectId  !== '' ? $subjectId  : null,
                'semester_id' => $semesterId !== '' ? $semesterId : null,
            ], static fn($v) => $v !== null);

            // 4) بدون فلاتر: لا نضرب الـ API (نُعيد مصفوفة فارغة)
            if ($filters === []) {
                return $this->response->setStatusCode(200)->setJSON([
                    'ok'   => true,
                    'data' => [],
                ]);
            }

            // 5) نحاول عدّة مسارات/صيغ حتى نحصل على 2xx/3xx
            [$s, $j] = $this->tryListEndpoints($filters);

            // عند 5xx من البعيد قمنا بالفعل بتحويلها إلى 200 برسالة ودية
            return $this->response->setStatusCode($s)->setJSON($j ?? ['ok' => false]);
        } catch (Throwable $e) {
            log_message('error', 'LibraryMeta::files exception: ' . $e->getMessage());
            return $this->response->setStatusCode(200)->setJSON([
                'ok'    => false,
                'error' => 'فشل جلب الملفات: ' . $e->getMessage(),
                'data'  => [],
            ]);
        }
    }

    /** ✅ DELETE /admin/library/files/{id} — حذف ملف (مع مسارات بديلة) */
    public function delete(int $fileId): ResponseInterface
    {
        try {
            $paths = array_values(array_unique([
                $this->filesBasePath(), // من .env أو /uploads
                '/uploads',
                '/files',
            ]));

            foreach ($paths as $p) {
                [$s, $j] = $this->remoteDelete($p . '/' . rawurlencode((string) $fileId));
                if ($s >= 200 && $s < 400) {
                    return $this->response->setStatusCode($s)->setJSON($j ?? ['ok' => true]);
                }
            }

            // فشل جميع المحاولات
            log_message(
                'error',
                'LibraryMeta::delete failed for id={id} on all paths',
                ['id' => $fileId]
            );

            return $this->response->setStatusCode(200)->setJSON([
                'ok'    => false,
                'error' => 'تعذر حذف الملف من الخادم البعيد.',
            ]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode(200)->setJSON([
                'ok'    => false,
                'error' => 'فشل حذف الملف: ' . $e->getMessage(),
            ]);
        }
    }

    /** مُساعد أخطاء موحّد */
    private function fail(string $msg, int $code = 400): ResponseInterface
    {
        return $this->response->setStatusCode($code)->setJSON(['ok' => false, 'error' => $msg]);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers\Admins;

use App\Controllers\BaseController;
use App\Models\ServiceMessageModel;
use App\Models\ServiceRequestFileModel;
use App\Models\ServiceRequestModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Admin • حذف الرسائل/المرفقات بأمان
 * - يتحقق من وجود الطلب والرسالة
 * - يحذف روابط المرفقات
 * - يحذف الملف فعليًا فقط إن لم يعد مربوطًا بأي رسالة/طلب
 * - يطبّق Soft-Delete على الرسالة إن أمكن، وإلا يحذفها نهائيًا
 */
class ServiceMessages extends BaseController
{
    /** @var ServiceMessageModel */
    protected $messageModel;

    /** @var ServiceRequestFileModel */
    protected $srfModel;

    /** @var ServiceRequestModel */
    protected $requestModel;

    /** @var string جذر ملفات الخدمة (public/uploads/service) */
    protected string $uploadRoot;

    public function __construct()
    {
        helper('security');

        $this->messageModel = model(ServiceMessageModel::class);
        $this->srfModel     = model(ServiceRequestFileModel::class);
        $this->requestModel = model(ServiceRequestModel::class);

        // public_path/uploads/service
        $this->uploadRoot = rtrim(FCPATH . 'uploads/service', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * POST /admins/api/service-requests/{reqId}/messages/{msgId}/delete
     */
    public function delete(int $requestId, int $messageId): ResponseInterface
    {
        if (!$this->request->is('post')) {
            return $this->jsonFail('Invalid method', 405);
        }

        // (اختياري) تأكد أن المستخدم إدمن — لو عندك جلسة/صلاحيات خاصة بالإدمن
        $adminId = (int) (session()->get('admin_id') ?? session()->get('user_id') ?? 0);
        if ($adminId <= 0) {
            return $this->jsonFail('Unauthorized', 401);
        }

        // الطلب موجود؟
        $req = $this->requestModel->select('id')->find($requestId);
        if (!$req) {
            return $this->jsonFail('Request not found', 404);
        }

        // الرسالة تخص هذا الطلب؟
        $msg = $this->messageModel
            ->where('id', $messageId)
            ->where('service_request_id', $requestId)
            ->first();

        if (!$msg) {
            return $this->jsonFail('Message not found', 404);
        }

        $db = db_connect();
        $db->transStart();

        try {
            // 1) جميع ملفات الرسالة مع بياناتهم
            $files = $this->srfModel->getFilesForMessage($messageId); // يحتوي file_path, file_id ...

            // 2) إزالة روابط المرفقات + حذف فعلي للملف إن أصبح孤ف + حذف سجل files إن孤ف
            foreach ($files as $f) {
                $fileId   = (int) ($f['id'] ?? $f['file_id'] ?? 0);
                $filePath = (string) ($f['file_path'] ?? '');

                // كم ربطًا لهذا الملف خارج هذه الرسالة؟
                $usedElsewhere = $db->table('service_request_files')
                    ->where('file_id', $fileId)
                    ->where('message_id <>', $messageId)
                    ->countAllResults();

                // احذف الربط الحالي
                $this->srfModel->removeFileFromMessage($messageId, $fileId);

                // إذا لم يعد مستخدمًا في أي مكان: احذف الملف فعليًا + سجل files
                if ($usedElsewhere === 0 && $fileId > 0) {
                    // حذف فعلي
                    if ($filePath) {
                        $abs = $this->resolveAbsolutePath($filePath);
                        if ($abs !== null && is_file($abs)) {
                            @unlink($abs);
                        }
                    }
                    // حذف سجل الملف
                    $db->table('files')->where('id', $fileId)->delete();
                }
            }

            // 3) تنظيف قراءات الرسالة (اختياري)
            $db->table('service_message_reads')->where('message_id', $messageId)->delete();

            // 4) حذف الرسالة: نحاول Soft-Delete بإعداد deleted_at، وإن فشل نلجأ للحذف النهائي
            $softDeleted = false;
            try {
                $softDeleted = (bool) $this->messageModel->update($messageId, [
                    'deleted_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable $e) {
                $softDeleted = false;
            }

            if (!$softDeleted) {
                // حذف نهائي
                $this->messageModel->delete($messageId, true);
            }

            $db->transComplete();
            if ($db->transStatus() === false) {
                return $this->jsonFail('Delete failed (transaction)', 500);
            }

            return $this->jsonOk('Message deleted', [
                'soft_deleted' => $softDeleted ? 1 : 0,
                'message_id'   => $messageId,
                'request_id'   => $requestId,
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Admin delete message error: {err}', ['err' => $e->getMessage()]);
            return $this->jsonFail('Server error during delete', 500, ['ref' => 'ADM-DEL-500']);
        }
    }

    /* ================= Utils ================= */

    protected function resolveAbsolutePath(string $storedPath): ?string
    {
        // يقبل مسارات مثل: "uploads/service/xxx" أو "/uploads/service/xxx"
        $relative = ltrim($storedPath, '/\\');

        // لو كانت تبدأ بـ uploads/service/ نزِل البادئة
        $prefix = 'uploads/service/';
        if (strpos($relative, $prefix) === 0) {
            $relative = substr($relative, strlen($prefix));
        }

        // مسار مطلق متوقع تحت $uploadRoot
        $abs  = $this->uploadRoot . ltrim($relative, DIRECTORY_SEPARATOR);
        $root = realpath($this->uploadRoot) ?: $this->uploadRoot;

        $real = @realpath($abs);
        if ($real === false) {
            // الملف قد لا يكون موجودًا — تأكد فقط أن الهدف داخل الجذر
            if (!str_starts_with($abs, $root)) {
                return null;
            }
            return $abs;
        }

        // أمان: لا نسمح بالخروج عن الجذر
        if (!str_starts_with($real, $root)) {
            return null;
        }
        return $real;
    }

    protected function jsonOk(string $message, array $data = []): ResponseInterface
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data'    => $data,
            '_csrf'   => ['name' => csrf_token(), 'hash' => csrf_hash()],
        ];
        return $this->response->setJSON($payload);
    }

    protected function jsonFail(string $message, int $code = 400, array $data = []): ResponseInterface
    {
        $payload = [
            'success' => false,
            'message' => $message,
            'data'    => $data,
            '_csrf'   => ['name' => csrf_token(), 'hash' => csrf_hash()],
        ];
        return $this->response->setStatusCode($code)->setJSON($payload);
    }
}

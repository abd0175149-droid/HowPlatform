<?php

declare(strict_types=1);

namespace App\Controllers\Students;

use App\Controllers\BaseController;
use App\Models\ServiceRequestModel;
use App\Models\ServiceMessageCascadeModel;
use CodeIgniter\HTTP\ResponseInterface;

class ServiceMessages extends BaseController
{
    protected ServiceRequestModel $requestModel;
    protected ServiceMessageCascadeModel $cascade;

    public function __construct()
    {
        $this->requestModel = model(ServiceRequestModel::class);
        $this->cascade      = model(ServiceMessageCascadeModel::class);
    }

    /**
     * POST /students/service-requests/{reqId}/messages/{msgId}/delete
     */
    public function delete(int $requestId, int $messageId): ResponseInterface
    {
        // الطريقة
        if (! $this->request->is('post')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid method'])->setStatusCode(405);
        }

        // المستخدم
        $userId = (int) (session()->get('user_id') ?? 0);
        if ($userId <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Unauthorized'])->setStatusCode(401);
        }

        // تحقق ملكية الطلب: service_requests.user_id
        $req = $this->requestModel->select('id, user_id')->find($requestId);
        if (! $req) {
            return $this->response->setJSON(['success' => false, 'message' => 'Request not found'])->setStatusCode(404);
        }
        if ((int) $req['user_id'] !== $userId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Forbidden'])->setStatusCode(403);
        }

        // حذف تراتبي للرسالة + روابطها + ملفاتها (مع حذف فيزيائي آمن)
        $ok = $this->cascade->deleteMessageWithFiles($requestId, $messageId, $userId);

        if (! $ok) {
            return $this->response->setJSON(['success' => false, 'message' => 'Delete failed'])->setStatusCode(500);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Message deleted']);
    }
}

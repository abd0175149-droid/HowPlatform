<?php

declare(strict_types=1);

namespace App\Controllers\Students;

use App\Controllers\BaseController;
use App\Models\ServiceRequestModel;
use App\Models\ServiceMessageModel;
use App\Models\ServiceRequestFileModel;
use App\Models\ServiceFileStorageModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * حذف الرسائل/المرفقات بأمان (نسخة مقيّدة بملكية الرسالة للطالب)
 */
class ServiceMessages extends BaseController
{
    protected ServiceRequestModel $requestModel;
    protected ServiceMessageModel $messageModel;
    protected ServiceRequestFileModel $linkModel;
    protected ServiceFileStorageModel $fileStorage;

    public function __construct()
    {
        // ✅ استخدام موديلاتك كما هي + موديل التخزين الجديد
        $this->requestModel = model(ServiceRequestModel::class);
        $this->messageModel = model(ServiceMessageModel::class);
        $this->linkModel    = model(ServiceRequestFileModel::class);
        $this->fileStorage  = model(ServiceFileStorageModel::class);
    }

    /**
     * POST /students/service-requests/{reqId}/messages/{msgId}/delete
     */
    public function delete(int $requestId, int $messageId): ResponseInterface
    {
        // تأمين الطريقة
        if (! $this->request->is('post')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid method'])->setStatusCode(405);
        }

        // المستخدم الحالي
        $userId = (int) (session()->get('user_id') ?? 0);
        if ($userId <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Unauthorized'])->setStatusCode(401);
        }

        // ✅ تحقق ملكية الطلب عبر user_id (وليس student_id)
        $req = $this->requestModel->select('id, user_id')->find($requestId);
        if (! $req) {
            return $this->response->setJSON(['success' => false, 'message' => 'Request not found'])->setStatusCode(404);
        }
        if ((int) $req['user_id'] !== $userId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Forbidden'])->setStatusCode(403);
        }

        // ✅ جلب الرسالة والتأكد أنها تتبع الطلب وأن مرسلها هو الطالب الحالي
        $msg = $this->messageModel
            ->select('id, service_request_id, sender_user_id')
            ->where('id', $messageId)
            ->where('service_request_id', $requestId)
            ->first();

        if (! $msg) {
            return $this->response->setJSON(['success' => false, 'message' => 'Message not found'])->setStatusCode(404);
        }

        // 🚫 منع حذف رسائل الآخرين (الإدمن/الدعم أو مستخدم آخر)
        if ((int) $msg['sender_user_id'] !== $userId) {
            return $this->response->setJSON(['success' => false, 'message' => 'You can only delete your own messages'])->setStatusCode(403);
        }

        // تنفيذ الحذف ضمن Transaction
        $db = db_connect();
        $db->transBegin();

        try {
            // 1) روابط هذه الرسالة من جدول الربط
            $links = $this->linkModel
                ->where('message_id', $messageId)
                ->findAll();

            // 2) لكل رابط: إن كان الملف غير مستخدم في روابط أخرى، احذفه فعليًا + من جدول files،
            //    وإلا احذف الرابط فقط واترك الملف (لأنه مشترك).
            foreach ($links as $lnk) {
                $linkId = (int) ($lnk['id'] ?? 0);
                $fid    = (int) ($lnk['file_id'] ?? 0);

                // احذف الرابط أولاً
                if ($linkId > 0) {
                    $this->linkModel->delete($linkId);
                } else {
                    // احتياط: لو ما في link_id، احذف بالشرط
                    $this->linkModel->where('message_id', $messageId)->where('file_id', $fid)->delete();
                }

                if ($fid > 0) {
                    // هل يوجد روابط أخرى تشير لنفس الملف؟
                    $otherRefs = (int) $this->linkModel
                        ->where('file_id', $fid)
                        ->countAllResults();

                    if ($otherRefs === 0) {
                        // لا توجد روابط أخرى ⇒ احذف الملف الفيزيائي وسجله
                        $this->fileStorage->deleteOneSafe($fid);
                    }
                }
            }

            // 3) تنظيف أي روابط باقية لهذه الرسالة (لو بقي شيء لسبب ما)
            $this->linkModel->where('message_id', $messageId)->delete();

            // 4) حذف الرسالة نفسها (موديلك لا يفعّل SoftDeletes)
            $this->messageModel->delete($messageId, true);

            // إتمام المعاملة
            $db->transCommit();

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Message deleted',
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'ServiceMessages::delete error: {err}', ['err' => $e->getMessage()]);

            return $this->response->setJSON([
                'success' => false,
                'message' => 'Server error during delete',
                'ref'     => 'SM-DEL-500',
            ])->setStatusCode(500);
        }
    }
}

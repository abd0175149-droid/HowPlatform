<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * كاتب "إشارة" المحادثة اللحظية في Firestore (نمط: MySQL مصدر الحقيقة، Firestore إشارة فقط).
 *
 * يكتب مستنداً واحداً لكل محادثة: conversations/{requestId} بلا أي محتوى حسّاس،
 * فقط مؤشرات تُخبر العملاء أن هناك جديداً ليسحبوه من MySQL.
 *
 * كل العمليات غير قاتلة (try/catch) — تعطّل Firebase يجب ألا يكسر الشات إطلاقاً.
 */
class ChatSignal
{
    public const UID_PREFIX = 'u';

    /** uid موحّد للمستخدم في Firebase Auth. */
    public static function uid(int $userId): string
    {
        return self::UID_PREFIX . $userId;
    }

    /**
     * تحديث إشارة المحادثة بعد حفظ رسالة في MySQL.
     *
     * @param int      $requestId        معرّف طلب الخدمة
     * @param int      $lastMessageId    معرّف آخر رسالة في MySQL
     * @param string   $lastSenderRole   student|admin|ai
     * @param int      $studentUserId    صاحب الطلب (الطالب)
     * @param int|null $assignedAdminId  الآدمن المُسنَد (إن وُجد)
     */
    public static function bump(
        int $requestId,
        int $lastMessageId,
        string $lastSenderRole,
        int $studentUserId,
        ?int $assignedAdminId = null
    ): void {
        try {
            $fb = new FirebaseAdmin();
            $fb->firestoreSet('conversations/' . $requestId, [
                'requestId'        => $requestId,
                'lastMessageId'    => $lastMessageId,
                'lastMessageAt'    => FirebaseAdmin::nowTimestamp(),
                'lastSenderRole'   => $lastSenderRole,
                'studentUid'       => self::uid($studentUserId),
                'assignedAdminUid' => $assignedAdminId ? self::uid($assignedAdminId) : null,
                'updatedAt'        => FirebaseAdmin::nowTimestamp(),
            ], true);
        } catch (\Throwable $e) {
            log_message('error', 'ChatSignal::bump failed for request ' . $requestId . ': ' . $e->getMessage());
        }
    }
}

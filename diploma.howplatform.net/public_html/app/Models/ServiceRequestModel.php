<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * ServiceRequestModel
 * - متوافق مع جداول:
 *   - service_requests
 *   - service_messages + service_message_reads (للاحتسابات الفرعية مثل unread_count)
 * - لا يعتمد على جدول users الخارجي (الاسم/الإيميل تُجلب عبر API خارجي عند الحاجة).
 */
class ServiceRequestModel extends Model
{
    protected $table            = 'service_requests';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $useAutoIncrement = true;

    protected $allowedFields = [
        'user_id',
        'course_offering_id',
        'subject',
        'description',
        'status',
        'priority',
        'assigned_to',
        'closed_at',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** ڤاليديشن متوافق مع ENUMs */
    protected $validationRules = [
        'user_id'            => 'permit_empty|is_natural',
        'course_offering_id' => 'permit_empty|is_natural_no_zero',
        'assigned_to'        => 'permit_empty|is_natural',
        'subject'            => 'required|min_length[3]|max_length[255]',
        'description'        => 'required|min_length[5]',
        'status'             => 'permit_empty|in_list[pending,in_progress,resolved,closed,cancelled]',
        'priority'           => 'permit_empty|in_list[low,medium,high,urgent]',
        'closed_at'          => 'permit_empty|valid_date[Y-m-d H:i:s]',
    ];

    /* ===================== استعلامات الجدول (للأدمن) ===================== */

    /**
     * باني الاستعلام المشترك لقائمة الأدمن مع:
     * - آخر نشاط (آخر رسالة أو آخر تحديث)
     * - عداد غير مقروء للمشاهد الحالي من service_message_reads
     */
    private function buildAdminListBase(?int $viewerUserId, ?string $status, ?int $assignedTo, bool $unassignedOnly, ?string $q)
    {
        $db = $this->db;

        // ⏱️ آخر نشاط (آخر رسالة)
        $lastActivityExpr = "(SELECT MAX(sm.created_at)
                                FROM service_messages sm
                               WHERE sm.service_request_id = sr.id)";

        // 🔔 غير مقروء (يشمل رسائل أرسلها غيري ولم تُسجل قراءة من قِبَلي)
        $viewer = (int) ($viewerUserId ?? 0);
        $unreadExpr = "(SELECT COUNT(*)
                         FROM service_messages sm
                    LEFT JOIN service_message_reads mr
                           ON mr.message_id = sm.id
                          AND mr.reader_user_id = {$viewer}
                        WHERE sm.service_request_id = sr.id
                          AND sm.sender_user_id <> {$viewer}
                          AND mr.message_id IS NULL)";

        $b = $db->table($this->table . ' sr')
                ->select("
                    sr.id,
                    sr.user_id,
                    sr.course_offering_id,
                    sr.subject,
                    sr.description,
                    sr.status,
                    sr.priority,
                    sr.assigned_to,
                    sr.created_at,
                    sr.updated_at,
                    {$lastActivityExpr} AS last_message_at,
                    COALESCE({$lastActivityExpr}, sr.updated_at, sr.created_at) AS last_activity_at,
                    {$unreadExpr} AS unread_count
                ", false);

        if ($status !== null && $status !== '') {
            $b->where('sr.status', $status);
        }

        if ($unassignedOnly) {
            $b->where('sr.assigned_to IS NULL', null, false);
        } elseif (! empty($assignedTo)) {
            $b->where('sr.assigned_to', (int) $assignedTo);
        }

        if ($q !== null && $q !== '') {
            $b->groupStart()
                  ->like('sr.subject', $q)
                  ->orLike('sr.description', $q)
              ->groupEnd();
        }

        return $b;
    }

    /** عدّ الإجمالي لنفس فلاتر قائمة الأدمن */
    public function countForAdmin(?int $assignedTo, ?string $status, bool $unassignedOnly, ?string $q): int
    {
        $b = $this->buildAdminListBase(null, $status, $assignedTo, $unassignedOnly, $q);
        return (int) $b->countAllResults();
    }

    /**
     * جلب القائمة مُرقّمة
     * @param int|null $viewerUserId   المستخدم الحالي (لاحتساب unread_count)
     */
    public function getForAdminPaginated(
        ?int $assignedTo,
        ?string $status,
        bool $unassignedOnly,
        ?string $q,
        int $limit,
        int $offset,
        ?int $viewerUserId = null
    ): array {
        $b = $this->buildAdminListBase($viewerUserId, $status, $assignedTo, $unassignedOnly, $q);
        $rows = $b->orderBy('last_activity_at', 'DESC', false)
                  ->limit($limit, $offset)
                  ->get()
                  ->getResultArray();

        // تطبيع القيم
        foreach ($rows as &$r) {
            $r['id']                 = (int) $r['id'];
            $r['user_id']            = isset($r['user_id']) ? (int) $r['user_id'] : null;
            $r['course_offering_id'] = isset($r['course_offering_id']) ? (int) $r['course_offering_id'] : null;
            $r['assigned_to']        = isset($r['assigned_to']) ? (int) $r['assigned_to'] : null;
            $r['unread_count']       = isset($r['unread_count']) ? (int) $r['unread_count'] : 0;
            $r['priority']           = $r['priority'] ?? 'medium';
        }

        return $rows;
    }

    /* ===================== إحصائيات عامة ===================== */

    /**
     * إحصائيات عامة:
     * - يمكن فلترة حسب صاحب الطلب ($userId) أو المكلّف ($adminId)
     */
    public function getStats(?int $userId = null, ?int $adminId = null): array
    {
        $mk = function () use ($userId, $adminId) {
            $b = $this->db->table($this->table);
            if (! empty($userId)) {
                $b->where('user_id', (int) $userId);
            }
            if (! empty($adminId)) {
                $b->where('assigned_to', (int) $adminId);
            }
            return $b;
        };

        $count = static function ($b, $status = null): int {
            if ($status !== null) {
                $b->where('status', $status);
            }
            return (int) $b->countAllResults();
        };

        $statuses = ['pending','in_progress','resolved','closed','cancelled'];

        $out = [
            'total'       => $count($mk()),
            'pending'     => 0,
            'in_progress' => 0,
            'resolved'    => 0,
            'closed'      => 0,
            'cancelled'   => 0,
        ];

        foreach ($statuses as $st) {
            $out[$st] = $count($mk(), $st);
        }

        return $out;
    }

    /* ===================== تحديثات الحالة/الإسناد ===================== */

    public function updateStatus(int $id, string $status, ?int $actorUserId = null): bool
    {
        $data = [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($status === 'closed') {
            $data['closed_at'] = date('Y-m-d H:i:s');
        }

        // إن لم يكن الطلب مُسندًا، وأرسلنا actorUserId (أدمن يعدّل)، سجّل أول إسناد ضمنيًا
        if (! empty($actorUserId)) {
            $row = $this->select('assigned_to')->find($id);
            if ($row && empty($row['assigned_to'])) {
                $data['assigned_to'] = (int) $actorUserId;
            }
        }

        return (bool) $this->update($id, $data);
    }

    public function assignToAdmin(int $requestId, int $adminUserId): bool
    {
        // سياسة: التكليف يضع الحالة "in_progress"
        return (bool) $this->update($requestId, [
            'assigned_to' => (int) $adminUserId,
            'status'      => 'in_progress',
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }
    
    // داخل class ServiceRequestModel extends Model
    
    /**
     * إرجاع عدّادات الحالات مباشرةً من service_requests.status
     * يدعم فلتر اختياري عبر $where (مثال: ['assigned_to' => $adminId])
     */
    public function getStatusBuckets(array $where = []): array
    {
        $statuses = ['pending','in_progress','resolved','closed','cancelled'];
        $map = array_merge(['total' => 0], array_fill_keys($statuses, 0));
    
        $b = $this->db->table($this->table)->select('status, COUNT(*) AS cnt')->groupBy('status');
    
        if (! empty($where)) {
            foreach ($where as $k => $v) {
                // ملاحظة: استخدم where عادية؛ قيم NULL تضبط بـ where("$k IS NULL")
                if ($v === null) {
                    $b->where("$k IS NULL", null, false);
                } else {
                    $b->where($k, $v);
                }
            }
        }
    
        // total
        $totalBuilder = $this->db->table($this->table);
        if (! empty($where)) {
            foreach ($where as $k => $v) {
                if ($v === null) $totalBuilder->where("$k IS NULL", null, false);
                else $totalBuilder->where($k, $v);
            }
        }
        $map['total'] = (int) $totalBuilder->countAllResults();
    
        // تفصيل الحالات
        foreach ($b->get()->getResultArray() as $r) {
            $st = (string) ($r['status'] ?? '');
            if (isset($map[$st])) {
                $map[$st] = (int) $r['cnt'];
            }
        }
    
        return $map;
    }
    
    /**
     * عدد الطلبات غير المكلّفة: assigned_to IS NULL أو = 0
     */
    public function countUnassigned(): int
    {
        return (int) $this->db->table($this->table)
            ->groupStart()
                ->where('assigned_to IS NULL', null, false)
                ->orWhere('assigned_to', 0)
            ->groupEnd()
            ->countAllResults();
    }

}

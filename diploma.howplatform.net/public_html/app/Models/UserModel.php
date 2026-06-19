<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table         = 'users';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    // 🟢 تنبيهات: حذف كاش الأدمن بعد insert/update/delete
    protected $afterInsert = ['purgeAdminsCache'];
    protected $afterUpdate = ['purgeAdminsCache'];
    protected $afterDelete = ['purgeAdminsCache'];

    protected function purgeAdminsCache(array $data)
    {
        cache()->delete('admins_1_2_4_json');
        return $data;
    }

    protected $allowedFields = [
        'name',
        'email',
        'phone_number',
        'password',
        'verification_token',
        'is_verified',
        'google_id',
        'profile_image_url',
        'created_at',
        'account_type_id',
        'university_id',
        'academic_year_id',
        'specialty_id',
        'consent_given',
        'consent_date',
        'is_premium',
        'last_login_at',
        'last_seen_at',
        'is_complete', // ✅ مهم حتى لو ما بتستخدم save() له
    ];

    /**
     * يحسب عدد المستخدمين الذين last_login_at لديهم ضمن نطاق (day|week|month).
     * الاستعلام مُحصّن: لو فشل لأي سبب يرجّع 0 بدل 500.
     */
    public function countActiveByRange(string $range = 'day'): int
    {
        $range = in_array($range, ['day', 'week', 'month'], true) ? $range : 'day';

        $tz  = new \DateTimeZone('Asia/Amman');
        $now = new \DateTime('now', $tz);

        switch ($range) {
            case 'week':
                $start = (clone $now)->setTime(0, 0, 0);
                $w     = (int) $start->format('w'); // 0..6 (0=الأحد)
                $start->modify("-{$w} day");
                $end = (clone $start)->modify('+1 week');
                break;

            case 'month':
                $start = new \DateTime($now->format('Y-m-01 00:00:00'), $tz);
                $end   = (clone $start)->modify('+1 month');
                break;

            case 'day':
            default:
                $start = (clone $now)->setTime(0, 0, 0);
                $end   = (clone $start)->modify('+1 day');
                break;
        }

        $builder = $this->builder(); // users
        $builder->select('COUNT(*) AS c', false)
                ->where('last_login_at >=', $start->format('Y-m-d H:i:s'))
                ->where('last_login_at <',  $end->format('Y-m-d H:i:s'));

        $q = $builder->get();
        if ($q === false) {
            // 🔒 لا تسقط 500، ارجع 0 وخلّي الكنترولر يشتغل
            log_message('error', 'countActiveByRange query failed: '. json_encode($this->db->error()));
            return 0;
        }

        $row = $q->getRowArray();
        return (int) ($row['c'] ?? 0);
    }

    /**
     * يرجّع أعداد اكتمال التسجيل:
     * - completed: is_complete = 1
     * - incomplete: كل ما عدا ذلك (0 أو NULL)
     */
    public function getRegistrationCompletionCounts(): array
    {
        $builder = $this->builder();
        $builder->select("
            SUM(CASE WHEN is_complete = 1 THEN 1 ELSE 0 END)    AS completed,
            SUM(CASE WHEN is_complete IS NULL OR is_complete <> 1 THEN 1 ELSE 0 END) AS incomplete
        ", false);

        $q = $builder->get();
        if ($q === false) {
            log_message('error', 'getRegistrationCompletionCounts query failed: '. json_encode($this->db->error()));
            return ['completed' => 0, 'incomplete' => 0];
        }

        $row = $q->getRowArray() ?? ['completed' => 0, 'incomplete' => 0];

        return [
            'completed'  => (int) ($row['completed']  ?? 0),
            'incomplete' => (int) ($row['incomplete'] ?? 0),
        ];
    }
}

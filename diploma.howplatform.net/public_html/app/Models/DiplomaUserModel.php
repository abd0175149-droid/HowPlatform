<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * DiplomaUserModel
 * - التعامل مع جدول diploma_users فقط
 * - ملاحظة مهمة: الأسماء تُجلب عبر RemoteUsersClient (API خارجي)،
 *   لذلك هذا الموديل لا يقرأ أي عمود اسم من قاعدة البيانات.
 */
class DiplomaUserModel extends Model
{
    protected $table            = 'diploma_users';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $useAutoIncrement = true;

    protected $allowedFields = [
        'user_id',
        'phone_number',
        'account_type_id',
        'university_id',
        'specialty_id',
        'diploma_type_id',
        'academic_year_id',
        'is_premium',
        'premium_until',
        'is_complete',
        'gender',
        'birth_date',
        'last_login_at',
        'last_seen_at',
        'consent_given',
        'consent_date',
        'terms_accepted',
        'terms_accepted_at',
        'created_at',
        'updated_at',
    ];

    /**
     * جلب قائمة المكلّفين المحتملين (IDs) مع اسم نوع الحساب فقط
     * - الشرط: account_type_id != 3 (أي ليس طالب)
     * - الإخراج: user_id, account_type_name
     * - الأسماء (display names) تُجلب لاحقاً من RemoteUsersClient في الكنترولر.
     */
    public function getAssignableAdmins(): array
    {
        // نستخدم MAX للاسم لتجنّب ONLY_FULL_GROUP_BY في حال كانت هناك تكرارات عرضية
        return $this->select([
                'diploma_users.user_id',
                'MAX(account_types.name) AS account_type_name',
            ])
            ->join('account_types', 'account_types.id = diploma_users.account_type_id', 'left')
            ->where('diploma_users.account_type_id !=', 3)         // استبعاد الطلاب
            ->where('diploma_users.user_id IS NOT NULL', null, false)
            ->where('diploma_users.user_id !=', 0)
            ->groupBy('diploma_users.user_id')
            ->orderBy('diploma_users.user_id', 'ASC')
            ->findAll();
    }

    /**
     * (اختياري/توافقي) مخرجات جاهزة للعرض إن لزم:
     * - يبني Label مبدئي باستخدام رقم المستخدم ونوع الحساب فقط.
     * - الأسماء الحقيقية تبقى مسؤولية الكنترولر عبر RemoteUsersClient.
     */
    public function getAssignableAdminsForTable(): array
    {
        $rows = $this->getAssignableAdmins();

        return array_map(static function (array $r): array {
            $uid   = (int) ($r['user_id'] ?? 0);
            $type  = (string) ($r['account_type_name'] ?? '');
            $label = ($type !== '')
                ? ('#' . $uid . ' — ' . $type)
                : ('#' . $uid);

            return [
                'user_id'            => $uid,
                'account_type_name'  => $type,
                'label'              => $label,
            ];
        }, $rows);
    }
}

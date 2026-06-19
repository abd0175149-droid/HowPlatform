<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class VoucherModel extends Model
{
    protected $table         = 'vouchers';
    protected $primaryKey    = 'code'; // الكود مميز
    protected $returnType    = 'array';
    protected $allowedFields = [
        'code', 'plan_id', 'duration_days', 'issued_at', 'expires_at',
        'is_redeemed', 'redeemed_by_user_id', 'redeemed_at', 'redeemed_ip', 'redeemed_ua',
    ];

    /** جلب الفاوتشر بحساسية غير صارمة للأحرف (Normalize من الواجهة) */
    public function findByCode(string $code): ?array
    {
        $row = $this->where('code', $code)->first();
        return $row ?: null;
    }
}

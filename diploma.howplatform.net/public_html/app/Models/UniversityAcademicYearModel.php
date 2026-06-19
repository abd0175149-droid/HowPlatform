<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * يربط جامعة بعام دراسي
 */
class UniversityAcademicYearModel extends Model
{
    /** اسم الجدول */
    protected $table      = 'university_academic_years';

    /** المفتاح الأساسي (id موجود فعلاً في الجدول) */
    protected $primaryKey = 'id';

    /** الحقول المسموح إدخالها أو تعديلها */
    protected $allowedFields = [
        'university_id',
        'academic_year_id',
    ];

    /** نوع البيانات المُرجَعة */
    protected $returnType = 'array';

    /** لا نستخدم حقول created_at / updated_at في هذا الجدول */
    protected $useTimestamps = false;
}

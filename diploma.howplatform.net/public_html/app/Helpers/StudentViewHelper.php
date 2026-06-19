<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * StudentViewHelper
 * 
 * Helper functions لإدارة استعراض الأدمن لواجهة الطالب
 * باستخدام طالب افتراضي مع إعدادات قابلة للتعديل
 */
class StudentViewHelper
{
    /**
     * التحقق من أن الأدمن في وضع استعراض واجهة الطالب
     */
    public static function isAdminViewingAsStudent(): bool
    {
        $session = session();
        return (bool) ($session->get('admin_viewing_as_student') ?? false);
    }

    /**
     * جلب جميع إعدادات الطالب الافتراضي من session
     * تعمل فقط في وضع الاستعراض النشط
     * 
     * @return array|null
     */
    public static function getStudentViewPreferences(): ?array
    {
        if (!self::isAdminViewingAsStudent()) {
            return null;
        }

        return self::getSavedPreferences();
    }

    /**
     * جلب الإعدادات المحفوظة مسبقاً من session بغض النظر عن وضع الاستعراض
     * تُستخدم لعرض القيم الحالية في نموذج الإعدادات
     * 
     * @return array|null
     */
    public static function getSavedPreferences(): ?array
    {
        $session = session();
        $prefs = $session->get('admin_student_preferences');
        
        return is_array($prefs) ? $prefs : null;
    }

    /**
     * جلب user_id الفعال
     * إذا كان الأدمن في وضع الاستعراض، يرجع admin_id
     * وإلا يرجع user_id الحقيقي من session
     */
    public static function getEffectiveUserId(): ?int
    {
        if (self::isAdminViewingAsStudent()) {
            $session = session();
            return (int) ($session->get('original_admin_id') ?? 0) ?: null;
        }

        $authUser = session('auth_user') ?? [];
        $uid = (int)($authUser['id'] ?? 0);
        return $uid > 0 ? $uid : null;
    }

    /**
     * جلب university_id الفعال
     * إذا كان الأدمن في وضع الاستعراض، يرجع من الإعدادات
     * وإلا يرجع من diploma_users
     */
    public static function getEffectiveUniversityId(): ?int
    {
        if (self::isAdminViewingAsStudent()) {
            $prefs = self::getStudentViewPreferences();
            $uniId = (int)($prefs['university_id'] ?? 0);
            return $uniId > 0 ? $uniId : null;
        }

        // للطلاب الحقيقيين: قراءة من diploma_users
        $authUser = session('auth_user') ?? [];
        $uid = (int)($authUser['id'] ?? 0);
        
        if ($uid <= 0) {
            return null;
        }

        $db = \Config\Database::connect();
        $du = $db->table('diploma_users')
            ->select('university_id')
            ->where('user_id', $uid)
            ->get()
            ->getRowArray();

        $uniId = (int)($du['university_id'] ?? 0);
        return $uniId > 0 ? $uniId : null;
    }

    /**
     * جلب specialty_id الفعال
     */
    public static function getEffectiveSpecialtyId(): ?int
    {
        if (self::isAdminViewingAsStudent()) {
            $prefs = self::getStudentViewPreferences();
            $specId = (int)($prefs['specialty_id'] ?? 0);
            return $specId > 0 ? $specId : null;
        }

        // للطلاب الحقيقيين
        $authUser = session('auth_user') ?? [];
        $uid = (int)($authUser['id'] ?? 0);
        
        if ($uid <= 0) {
            return null;
        }

        $db = \Config\Database::connect();
        $du = $db->table('diploma_users')
            ->select('specialty_id')
            ->where('user_id', $uid)
            ->get()
            ->getRowArray();

        $specId = (int)($du['specialty_id'] ?? 0);
        return $specId > 0 ? $specId : null;
    }

    /**
     * جلب academic_year_id الفعال
     */
    public static function getEffectiveAcademicYearId(): ?int
    {
        if (self::isAdminViewingAsStudent()) {
            $prefs = self::getStudentViewPreferences();
            $yearId = (int)($prefs['academic_year_id'] ?? 0);
            return $yearId > 0 ? $yearId : null;
        }

        // للطلاب الحقيقيين
        $authUser = session('auth_user') ?? [];
        $uid = (int)($authUser['id'] ?? 0);
        
        if ($uid <= 0) {
            return null;
        }

        $db = \Config\Database::connect();
        $du = $db->table('diploma_users')
            ->select('academic_year_id')
            ->where('user_id', $uid)
            ->get()
            ->getRowArray();

        $yearId = (int)($du['academic_year_id'] ?? 0);
        return $yearId > 0 ? $yearId : null;
    }

    /**
     * جلب diploma_type_id الفعال
     */
    public static function getEffectiveDiplomaTypeId(): ?int
    {
        if (self::isAdminViewingAsStudent()) {
            $prefs = self::getStudentViewPreferences();
            $diplomaTypeId = (int)($prefs['diploma_type_id'] ?? 0);
            return $diplomaTypeId > 0 ? $diplomaTypeId : null;
        }

        // للطلاب الحقيقيين
        $authUser = session('auth_user') ?? [];
        $uid = (int)($authUser['id'] ?? 0);
        
        if ($uid <= 0) {
            return null;
        }

        $db = \Config\Database::connect();
        $du = $db->table('diploma_users')
            ->select('diploma_type_id')
            ->where('user_id', $uid)
            ->get()
            ->getRowArray();

        $diplomaTypeId = (int)($du['diploma_type_id'] ?? 0);
        return $diplomaTypeId > 0 ? $diplomaTypeId : null;
    }

    /**
     * جلب معلومات الأدمن الأصلي
     */
    public static function getOriginalAdminInfo(): ?array
    {
        if (!self::isAdminViewingAsStudent()) {
            return null;
        }

        $session = session();
        $adminId = (int)($session->get('original_admin_id') ?? 0);
        $adminRole = (int)($session->get('original_admin_role') ?? 0);

        if ($adminId <= 0) {
            return null;
        }

        return [
            'id' => $adminId,
            'role' => $adminRole,
        ];
    }
}


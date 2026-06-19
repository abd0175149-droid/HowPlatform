<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class SessionProfile
{
    /**
     * يبني ويخزن سنابشوت كامل في الجلسة:
     * session('auth_profile') => [id, name, email, avatar, verified, diploma=>[...] ]
     */
    public static function refresh(?int $userId = null): void
    {
        $userId = $userId ?? auth()->id();
        if (!$userId) return;

        // بيانات auth من نفس قاعدة Laravel
        $auth = DB::table('users')
            ->select('id','name','email','avatar','email_verified_at'
                /* لو عندك أعمدة إضافية مهمة للجلسة أضفها هنا: 'account_type_id' مثلاً */)
            ->where('id', $userId)
            ->first();

        // بيانات الدبلوم من اتصال 'diploma'
        $du = DB::connection('diploma')
            ->table('diploma_users')
            ->where('user_id', $userId)
            ->first();

        $profile = [
            'id'       => $auth->id ?? $userId,
            'name'     => $auth->name ?? null,
            'email'    => $auth->email ?? null,
            'avatar'   => $auth->avatar ?? null,
            'verified' => !empty($auth?->email_verified_at),

            // سنابشوت الدبلوم
            'diploma'  => [
                'phone_number'     => $du->phone_number     ?? null,
                'university_id'    => $du->university_id    ?? null,
                'specialty_id'     => $du->specialty_id     ?? null,
                'diploma_type_id'  => $du->diploma_type_id  ?? null,
                'academic_year_id' => $du->academic_year_id ?? null,
                'gender'           => $du->gender           ?? null, // male|female|null
                'birth_date'       => $du->birth_date       ?? null, // Y-m-d
                'is_complete'      => (int)($du->is_complete ?? 0),
                'last_login_at'    => $du->last_login_at    ?? null,
            ],
        ];

        // خزّنه تحت مفتاح واحد واضح
        Session::put('auth_profile', $profile);

        // (اختياري) حافظ على مفاتيح مسطحة لو في كود قديم يعتمد عليها:
        Session::put('university_id', $profile['diploma']['university_id']);
        Session::put('specialty_id',  $profile['diploma']['specialty_id']);
        Session::put('is_diploma_complete', $profile['diploma']['is_complete']);
    }

    /** تفريغ السنابشوت عند تسجيل الخروج مثلاً */
    public static function forget(): void
    {
        Session::forget([
            'auth_profile','university_id','specialty_id','is_diploma_complete'
        ]);
    }
}

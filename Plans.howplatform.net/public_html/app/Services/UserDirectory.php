<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * دليل المستخدمين: يحلّ الاسم/الإيميل من قاعدة الهوية (auth) والهاتف من قاعدة diploma،
 * ويُرجِع المعرّف العالمي (auth.users.id) لربطه بجداول Plans.
 *
 * fail-soft: أي تعذّر في الوصول لقاعدة auth/diploma لا يكسر الصفحة — يعود ببيانات ناقصة.
 */
class UserDirectory
{
    /**
     * بحث بالاسم/الإيميل/الهاتف أو المعرّف الرقمي. يُرجِع [['id','name','email','phone'], ...].
     * $extended = بحث أوسع (صفحة المستخدمين): الجامعة/التخصص/نوع الدبلوم/السنة/نوع الحساب + كود بطاقة أو بيانات مشتريها.
     */
    public function search(string $q, int $limit = 25, bool $extended = false): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        $byId = [];
        $order = [];

        // (أ) معرّف رقمي قصير = user_id مباشر. الهواتف أطول (≥8 خانات) فلا تُعامَل كمعرّف.
        if (ctype_digit($q) && strlen($q) <= 7) {
            $order[(int) $q] = true;
        }

        // (ب) الاسم/الإيميل من قاعدة auth
        try {
            $rows = DB::connection('auth')->table('users')
                ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"))
                ->limit($limit)
                ->get(['id', 'name', 'email']);
            foreach ($rows as $r) {
                $id = (int) $r->id;
                $byId[$id] = ['id' => $id, 'name' => $r->name, 'email' => $r->email, 'phone' => null];
                $order[$id] = true;
            }
        } catch (Throwable $e) {
            Log::warning('UserDirectory auth search failed: ' . $e->getMessage());
        }

        // (ج) الهاتف من قاعدة diploma — مع تطبيع الأرقام (خام + بلا فواصل + الرقم الوطني بلا 00962/962/0)
        try {
            $digits = preg_replace('/\D+/', '', $q);                       // الأرقام فقط
            $nat    = preg_replace('/^(00962|962|0)/', '', (string) $digits); // الرقم الوطني الدالّ
            $qb = DB::connection('diploma')->table('diploma_users')
                ->where('phone_number', 'like', "%{$q}%");
            if ($digits !== '' && $digits !== $q) {
                $qb->orWhere('phone_number', 'like', "%{$digits}%");
            }
            if ($nat !== '' && strlen($nat) >= 6 && $nat !== $digits) {
                $qb->orWhere('phone_number', 'like', "%{$nat}%");
            }
            foreach ($qb->limit($limit)->pluck('user_id')->all() as $pid) {
                $order[(int) $pid] = true;
            }
        } catch (Throwable $e) {
            Log::warning('UserDirectory diploma phone search failed: ' . $e->getMessage());
        }

        if ($extended) {
            // (د) البيانات الأكاديمية من diploma (أسماء الجامعة/التخصص/...)
            try {
                $like = "%{$q}%";
                $pids = $this->academicQuery()
                    ->where(fn ($w) => $w->where('un.name', 'like', $like)
                        ->orWhere('sp.name', 'like', $like)
                        ->orWhere('dt.name', 'like', $like)
                        ->orWhere('ay.label', 'like', $like)
                        ->orWhere('at.name', 'like', $like))
                    ->orderByDesc('du.last_seen_at')
                    ->limit($limit)
                    ->pluck('du.user_id')->all();
                foreach ($pids as $pid) {
                    $order[(int) $pid] = true;
                }
            } catch (Throwable $e) {
                Log::warning('UserDirectory academic search failed: ' . $e->getMessage());
            }

            // (هـ) كود بطاقة (بلا شرطات) أو بيانات مشتريها → من استبدلها
            try {
                $code = preg_replace('/[\s-]+/', '', $q);
                $rids = DB::table('cards')->whereNotNull('redeemed_by')
                    ->where(function ($w) use ($q, $code) {
                        if (strlen($code) >= 4) {
                            $w->orWhere('code', 'like', "%{$code}%");
                        }
                        $w->orWhere('buyer_name', 'like', "%{$q}%")
                          ->orWhere('buyer_email', 'like', "%{$q}%")
                          ->orWhere('buyer_phone', 'like', "%{$q}%");
                    })
                    ->limit($limit)->pluck('redeemed_by')->all();
                foreach ($rids as $rid) {
                    $order[(int) $rid] = true;
                }
            } catch (Throwable $e) {
                Log::warning('UserDirectory card search failed: ' . $e->getMessage());
            }
        }

        $ids = array_slice(array_keys($order), 0, $limit);
        if (empty($ids)) {
            return [];
        }

        // أكمل الاسم/الإيميل للمعرّفات التي جاءت من الهاتف/المعرّف فقط
        $missing = array_values(array_filter($ids, fn ($id) => ! isset($byId[$id])));
        if ($missing) {
            try {
                $rows = DB::connection('auth')->table('users')->whereIn('id', $missing)->get(['id', 'name', 'email']);
                foreach ($rows as $r) {
                    $byId[(int) $r->id] = ['id' => (int) $r->id, 'name' => $r->name, 'email' => $r->email, 'phone' => null];
                }
            } catch (Throwable $e) {
                Log::warning('UserDirectory auth backfill failed: ' . $e->getMessage());
            }
            foreach ($missing as $id) {
                $byId[$id] ??= ['id' => $id, 'name' => null, 'email' => null, 'phone' => null];
            }
        }

        // أرفِق الهاتف، واستبعد المعرّف الرقمي المُدخَل يدويًا إن لم يوجد فعلاً (لا اسم/إيميل/هاتف)
        $phones = $this->phones($ids);
        $out = [];
        foreach ($ids as $id) {
            $row = $byId[$id] ?? ['id' => $id, 'name' => null, 'email' => null, 'phone' => null];
            $row['phone'] = $phones[$id] ?? null;
            if ($row['name'] === null && $row['email'] === null && ($row['phone'] === null || $row['phone'] === '')) {
                continue; // معرّف غير موجود فعلاً — لا تُرجِع صفًّا وهميًا
            }
            $out[] = $row;
        }

        return $out;
    }

    /** بيانات مستخدم واحد (اسم/إيميل من auth، هاتف من diploma). */
    public function resolve(int $id): array
    {
        $row = ['id' => $id, 'name' => null, 'email' => null, 'phone' => null];
        if ($id <= 0) {
            return $row;
        }
        try {
            $u = DB::connection('auth')->table('users')->where('id', $id)->first(['id', 'name', 'email']);
            if ($u) {
                $row['name']  = $u->name;
                $row['email'] = $u->email;
            }
        } catch (Throwable $e) {
            Log::warning('UserDirectory resolve failed: ' . $e->getMessage());
        }
        $row['phone'] = $this->phones([$id])[$id] ?? null;

        return $row;
    }

    /** بيانات الحساب الكاملة من auth (حالة/صلاحيات/آخر دخول/تاريخ التسجيل) — null إن تعذّر. */
    public function account(int $id): ?array
    {
        try {
            $u = DB::connection('auth')->table('users')->where('id', $id)->first([
                'is_active', 'is_admin', 'is_sales', 'google_id', 'email_verified_at',
                'last_login_at', 'last_login_ip', 'created_at',
            ]);
            return $u ? (array) $u : null;
        } catch (Throwable $e) {
            Log::warning('UserDirectory account failed: ' . $e->getMessage());
            return null;
        }
    }

    /** خريطة user_id => البيانات الأكاديمية من diploma (الجامعة/التخصص/الدبلوم/السنة/نوع الحساب/...). */
    public function academic(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            return [];
        }
        try {
            $out = [];
            $rows = $this->academicQuery()->whereIn('du.user_id', $ids)->get([
                'du.user_id', 'du.phone_number', 'du.gender', 'du.birth_date', 'du.is_complete',
                'du.is_premium', 'du.premium_until', 'du.last_login_at', 'du.last_seen_at', 'du.created_at',
                'un.name as university', 'sp.name as specialty', 'dt.name as diploma_type',
                'ay.label as academic_year', 'at.name as account_type',
            ]);
            foreach ($rows as $r) {
                $out[(int) $r->user_id] = (array) $r;
            }
            return $out;
        } catch (Throwable $e) {
            Log::warning('UserDirectory academic failed: ' . $e->getMessage());
            return [];
        }
    }

    private function academicQuery()
    {
        return DB::connection('diploma')->table('diploma_users as du')
            ->leftJoin('universities as un', 'un.id', '=', 'du.university_id')
            ->leftJoin('specialties as sp', 'sp.id', '=', 'du.specialty_id')
            ->leftJoin('diploma_types as dt', 'dt.id', '=', 'du.diploma_type_id')
            ->leftJoin('academic_years as ay', 'ay.id', '=', 'du.academic_year_id')
            ->leftJoin('account_types as at', 'at.id', '=', 'du.account_type_id');
    }

    /** خريطة user_id => name من قاعدة auth (لعرض الأسماء في القوائم دفعة واحدة). */
    public function names(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            return [];
        }
        try {
            return DB::connection('auth')->table('users')
                ->whereIn('id', $ids)
                ->pluck('name', 'id')->all();
        } catch (Throwable $e) {
            Log::warning('UserDirectory names failed: ' . $e->getMessage());
            return [];
        }
    }

    /** خريطة user_id => phone من diploma_users. */
    private function phones(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        try {
            return DB::connection('diploma')->table('diploma_users')
                ->whereIn('user_id', $ids)
                ->pluck('phone_number', 'user_id')->all();
        } catch (Throwable $e) {
            Log::warning('UserDirectory phones failed: ' . $e->getMessage());
            return [];
        }
    }
}

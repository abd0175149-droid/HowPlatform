<?php
declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class ProfileApiController extends Controller
{
    private function j($data, int $code = 200)
    {
        return response()->json($data, $code, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function uid(Request $r): int
    {
        return (int) $r->user()->id;
    }

    /* ---------- Helpers ---------- */

    private function avatarUrlFor(?string $value): string
    {
        $fallback = asset('images/Avatar.png');
        $raw = trim((string)($value ?? ''));
        if ($raw === '') return $fallback;

        if (Str::startsWith($raw, '/public/')) {
            $raw = Str::after($raw, '/public/');
        }
        if (preg_match('#^https?://#i', $raw)) {
            return $raw;
        }
        if (Str::startsWith($raw, '/media/')) {
            return url($raw);
        }
        if (Str::startsWith($raw, '/storage/')) {
            $raw = Str::after($raw, '/storage/');
        }
        if (Str::startsWith($raw, 'avatars/')) {
            $mtime = Storage::disk('public')->exists($raw)
                ? (Storage::disk('public')->lastModified($raw) ?: time())
                : time();
            // إن لم يكن لديك راوت media.public استخدم Storage::url:
            // return Storage::url($raw).'?v='.$mtime;
            return route('media.public', ['path' => $raw, 'v' => $mtime]);
        }
        return $fallback;
    }

    private function isDiplomaComplete(array $data): bool
    {
        foreach (['phone_number','university_id','specialty_id','diploma_type_id','academic_year_id','gender','birth_date'] as $f) {
            if (!isset($data[$f]) || $data[$f] === '' || $data[$f] === null) return false;
        }
        $g = strtolower((string)$data['gender']);
        if (!in_array($g, ['male','female'], true)) return false;
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$data['birth_date']);
    }

    /* =========================================================
     * GET /api/student/profile/me
     * =======================================================*/
    public function show(Request $request)
    {
        $user = $request->user();
        $db   = DB::connection('diploma');

        $du = $db->table('diploma_users')->where('user_id', $user->id)->first();
        if (!$du) {
            $du = (object)[
                'user_id'          => $user->id,
                'phone_number'     => null,
                'university_id'    => null,
                'specialty_id'     => null,
                'diploma_type_id'  => null,
                'academic_year_id' => null,
                'gender'           => null,
                'birth_date'       => null,
                'is_complete'      => 0,
            ];
        }

        return $this->j([
            'auth' => [
                'id'                => (int) $user->id,
                'name'              => $user->name,
                'email'             => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'avatar_url'        => $this->avatarUrlFor($user->avatar ?? null),
                'raw_avatar'        => $user->avatar, // إن احتجته
            ],
            'diploma' => [
                'phone_number'     => $du->phone_number,
                'university_id'    => $du->university_id,
                'specialty_id'     => $du->specialty_id,
                'diploma_type_id'  => $du->diploma_type_id,
                'academic_year_id' => $du->academic_year_id,
                'gender'           => $du->gender,       // male|female
                'birth_date'       => $du->birth_date,   // Y-m-d
                'is_complete'      => (int) ($du->is_complete ?? 0),
            ],
        ]);
    }

    /* =========================================================
     * GET /api/student/profile/meta
     * =======================================================*/
    public function meta()
    {
        $db = DB::connection('diploma');

        return $this->j([
            'universities' => $db->table('universities')->select('id','name')->orderBy('name')->get(),
            'specialties'  => $db->table('specialties')->select('id','name')->orderBy('name')->get(),
            'diploma_types'=> $db->table('diploma_types')->select('id','name')->orderBy('id')->get(),
            'academic_years'=> $db->table('academic_years')->select('id','label')->orderBy('id')->get(),
        ]);
    }

    /* =========================================================
     * PATCH /api/student/profile/auth
     * body: { name, email, avatar? (url) }
     * =======================================================*/
    public function updateAuth(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'   => ['required','string','max:255'],
            'avatar' => ['nullable','url','max:2048'],
        ]);

        $user->fill([
            'name'  => $data['name'],
        ]);

        if (array_key_exists('avatar', $data)) {
            $user->avatar = $data['avatar']; // كرابط جاهز (اختياري)
        }

        $user->save();

        return $this->j([
            'success' => true,
            'auth' => [
                'id'    => (int) $user->id,
                'name'  => $user->name,
                'avatar_url' => $this->avatarUrlFor($user->avatar ?? null),
            ],
        ]);
    }

    /* =========================================================
     * PATCH /api/student/profile/diploma
     * =======================================================*/
    public function updateDiploma(Request $request)
    {
        $uid = $this->uid($request);
        $db  = DB::connection('diploma');

        $request->validate([
            'phone_number'     => ['nullable','string','max:32'],
            'university_id'    => ['nullable','integer'],
            'specialty_id'     => ['nullable','integer'],
            'diploma_type_id'  => ['nullable','integer'],
            'academic_year_id' => ['nullable','integer'],
            'gender'           => ['nullable', Rule::in(['male','female'])],
            'birth_date'       => ['nullable','date_format:Y-m-d'],
        ]);

        // تحقّق من وجود المراجع
        $errors = [];
        foreach ([
            'university_id'    => 'universities',
            'specialty_id'     => 'specialties',
            'diploma_type_id'  => 'diploma_types',
            'academic_year_id' => 'academic_years',
        ] as $field => $table) {
            $val = $request->input($field);
            if ($val !== null && $val !== '') {
                $exists = $db->table($table)->where('id', $val)->exists();
                if (!$exists) $errors[$field] = "القيمة غير موجودة في جدول {$table}.";
            }
        }
        if ($errors) {
            return $this->j(['success'=>false,'errors'=>$errors], 422);
        }

        $payload = [
            'user_id'          => $uid,
            'phone_number'     => $request->input('phone_number') ?: null,
            'university_id'    => $request->input('university_id') ?: null,
            'specialty_id'     => $request->input('specialty_id') ?: null,
            'diploma_type_id'  => $request->input('diploma_type_id') ?: null,
            'academic_year_id' => $request->input('academic_year_id') ?: null,
            'gender'           => $request->input('gender') ?: null,
            'birth_date'       => $request->input('birth_date') ?: null,
        ];
        $payload['is_complete'] = $this->isDiplomaComplete($payload) ? 1 : 0;

        $db->table('diploma_users')->updateOrInsert(['user_id' => $uid], $payload);

        return $this->j([
            'success' => true,
            'diploma' => $payload,
        ]);
    }

    /* =========================================================
     * POST /api/student/profile/avatar  (multipart)
     * file: avatar
     * =======================================================*/
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required','file','image','mimes:jpeg,jpg,png,webp','max:5120','dimensions:max_width=3000,max_height=3000'],
        ]);

        $user = $request->user();

        $path = $request->file('avatar')->store('avatars', 'public'); // avatars/xxx.webp|png
        $mtime = Storage::disk('public')->lastModified($path) ?: time();

        // إن لم يتوفر راوت media.public استعمل:
        // $publicUrl = Storage::url($path).'?v='.$mtime;
        $publicUrl = route('media.public', ['path' => $path, 'v' => $mtime]);

        // حذف القديمة إن كانت محلية (اختياري مبسّط)
        $prev = $user->avatar;
        if ($prev) {
            $prevPath = parse_url((string)$prev, PHP_URL_PATH) ?: '';
            if (Str::startsWith($prevPath, '/storage/avatars/')) {
                $oldRel = Str::after($prevPath, '/storage/');
                if ($oldRel) Storage::disk('public')->delete($oldRel);
            }
        }

        $user->avatar = $publicUrl;
        $user->save();

        return $this->j(['success'=>true,'url'=>$publicUrl]);
    }

    /* =========================================================
     * PATCH /api/student/profile/password
     * =======================================================*/
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required','current_password'],
            'password'         => ['required','confirmed','min:8'],
        ]);

        $user = $request->user();
        $user->password = Hash::make($request->input('password'));
        $user->save();

        return $this->j(['success'=>true,'message'=>'تم تحديث كلمة المرور.']);
    }
}

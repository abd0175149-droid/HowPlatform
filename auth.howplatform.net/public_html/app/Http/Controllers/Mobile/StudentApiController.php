<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class StudentApiController extends Controller
{
    private function j($data, int $code = 200)
    {
        return response()->json($data, $code, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /* -----------------------------------------------------------------
     * GET /api/student/dashboard?user_id={USER_ID}
     * -----------------------------------------------------------------*/
    public function dashboard(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
        ]);
    
        $userId = $data['user_id'];
    
        // 👤 جلب المستخدم من قاعدة auth
        $user = DB::table('users')->select('id', 'name')->where('id', $userId)->first();
        $userName = $user?->name ?? 'Student';
    
        // 📋 جلب بروفايل الطالب من قاعدة الدبلوم
        $profile = DB::connection('diploma')
            ->table('diploma_users')
            ->select(
                'user_id',
                'university_id',
                'specialty_id',
                'academic_year_id',
                'diploma_type_id',
                'is_premium',
                'premium_until',
                'is_complete'
            )
            ->where('user_id', $userId)
            ->first();
    
        if (!$profile) {
            return $this->j([
                'user_name' => $userName,
                'weekly_tasks' => [],
                'reason' => 'no_profile',
                'message' => 'لم يتم العثور على ملف الطالب في منصة الدبلوم.',
            ]);
        }
    
        // التحقق من اكتمال بيانات الطالب
        if (
            empty($profile->university_id) ||
            empty($profile->academic_year_id) ||
            is_null($profile->specialty_id)
        ) {
            return $this->j([
                'user_name' => $userName,
                'weekly_tasks' => [],
                'reason' => 'profile_incomplete',
                'message' => 'الرجاء استكمال الملف الشخصي لتظهر المهام.',
            ]);
        }
    
        // 🎯 المهام المرتبطة بنفس الجامعة والسنة والتخصص
        $sql = "
        SELECT DISTINCT
            t.id,
            t.title,
            t.description,
            t.due_date,
            wc.week_number AS week_number,        
            co.id         AS course_offering_id,  
            c.name        AS course_name
        FROM tasks t
        JOIN weekly_contents wc       ON wc.id = t.weekly_content_id
        JOIN course_offerings co      ON co.id = wc.course_offering_id
        JOIN courses c                ON c.id = co.course_id
        JOIN semesters s              ON s.id = co.semester_id
        JOIN diploma_users du         ON du.user_id = ?
        LEFT JOIN task_specialties ts ON ts.task_id = t.id
        WHERE 
            co.university_id = du.university_id
            AND s.academic_year_id = du.academic_year_id
            AND (
                t.is_for_all_specialties = 1 
                OR ts.specialty_id = du.specialty_id
            )
            AND (t.visible_from_date IS NULL OR t.visible_from_date <= NOW())
            AND (t.due_date IS NULL OR t.due_date >= NOW())
        ORDER BY t.due_date IS NULL, t.due_date ASC, t.id DESC
        LIMIT 10;
    ";

    
        $tasks = DB::connection('diploma')->select($sql, [$userId]);
    
        // 🧰 الأدوات من قاعدة الأدوات
        $tools = [];
        if (Schema::connection('tools')->hasTable('tools')) {
            $tools = DB::connection('tools')
                ->table('tools')
                ->select(
                    'id',
                    'name',
                    'slug',
                    'url',
                    'image_url',
                    'icon_class',
                    'icon_color',
                    'gradient',
                    'description'
                )
                ->where('is_active', 1)
                ->orderByDesc('is_featured')
                ->orderBy('name')
                ->get()
                ->map(function ($tool) {
                    return [
                        'id'         => $tool->id,
                        'label'      => $tool->name,
                        'slug'       => $tool->slug,
                        'url'        => $tool->url,
                        'image_url'  => $tool->image_url ? url($tool->image_url) : null,
                        'icon'       => $tool->icon_class ?: 'fa-toolbox',
                        'color'      => $tool->icon_color ?: '#219ebc',
                        'gradient'   => $tool->gradient,
                        'desc'       => $tool->description,
                    ];
                })
                ->values()
                ->all();
        }
    
        return $this->j([
            'user_name'    => $userName,
            'weekly_tasks' => $tasks,
            'tools'        => $tools,
        ]);
    }


    /* -----------------------------------------------------------------
     * GET /api/student/courses?user_id={USER_ID}
     * -----------------------------------------------------------------*/
    private function fetchDiplomaProfile(int $userId): ?object
    {
        $row = DB::connection('diploma')
            ->table('diploma_users')
            ->select('user_id','university_id','academic_year_id','specialty_id','diploma_type_id','is_premium','is_complete')
            ->where('user_id', $userId)
            ->first();
    
        return $row ?: null;
    }
    
    /* -----------------------------------------------------------------
     * GET /api/student/courses?user_id={USER_ID}
     * الآن مع فلترة حسب بروفايل الطالب (جامعة/عام/تخصص إن وُجد)
     * -----------------------------------------------------------------*/
    public function courses(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required','integer','min:1'],
        ]);
    
        // ⚠️ احضر بروفايل الطالب من قاعدة الدبلوم
        $profile = $this->fetchDiplomaProfile((int)$data['user_id']);
        if (!$profile) {
            return $this->j([
                'courses' => [],
                'reason'  => 'no_profile',
                'message' => 'لا يوجد ملف للطالب في منصة الدبلوم.',
            ]);
        }
    
        if (empty($profile->university_id) || empty($profile->academic_year_id)) {
            return $this->j([
                'courses' => [],
                'reason'  => 'profile_incomplete',
                'message' => 'الرجاء إكمال الملف (الجامعة/العام الأكاديمي).',
            ]);
        }
    
        $q = DB::connection('diploma')
            ->table('course_offerings as co')
            ->join('courses as c', 'c.id', '=', 'co.course_id')
            ->join('semesters as s', 's.id', '=', 'co.semester_id')
            ->where('co.university_id', $profile->university_id)
            ->where('s.academic_year_id', $profile->academic_year_id);
    
        // ✅ دعم فلترة التخصص إن كان ذلك موجودًا في هيكل DB:
        // 1) لو عندك عمود specialty_id مباشرة في course_offerings
        if (Schema::connection('diploma')->hasColumn('course_offerings', 'specialty_id')) {
            // مساقات للجميع أو مطابقة لتخصص الطالب
            $q->where(function($w) use ($profile) {
                $w->whereNull('co.specialty_id')
                  ->orWhere('co.specialty_id', '=', $profile->specialty_id);
            });
        }
        // 2) أو لو عندك جدول وسيط course_offering_specialties(co_id, specialty_id)
        elseif (Schema::connection('diploma')->hasTable('course_offering_specialties')) {
            $q->where(function($w) use ($profile) {
                $w->whereExists(function($sub) use ($profile) {
                    $sub->from('course_offering_specialties as cos')
                        ->whereColumn('cos.course_offering_id', 'co.id')
                        ->where('cos.specialty_id', '=', $profile->specialty_id);
                })
                ->orWhereNotExists(function($sub) {
                    $sub->from('course_offering_specialties as cos2')
                        ->whereColumn('cos2.course_offering_id', 'co.id');
                }); // إذا لم يُحدد أي تخصص لهذا العرض اعتبره متاحًا للجميع
            });
        }
        // 3) لو لا يوجد تخصيص بالتخصص في الهيكل → لا شيء إضافي
    
        // ⛳ مساقات مميزة/نشِطة لو عندك أعمدة لذلك (اختياري):
        if (Schema::connection('diploma')->hasColumn('course_offerings', 'is_active')) {
            $q->where('co.is_active', 1);
        }
    
        // 👇 DISTINCT لتفادي التكرار عند تعدد العروض لنفس المساق
        $courses = $q->distinct()
            ->orderBy('c.name')
            ->get(['c.id','c.name'])
            ->map(fn($r) => ['id' => (int)$r->id, 'name' => $r->name])
            ->values()
            ->all();
    
        return $this->j(['courses' => $courses]);
    }

    /* -----------------------------------------------------------------
     * POST /api/support-requests  → قاعدة الدبلوم
     * -----------------------------------------------------------------*/
    public function storeSupportRequest(Request $request)
    {
        $data = $request->validate([
            'user_id'   => ['required','integer','min:1'],
            'title'     => ['required','string','max:200'],
            'details'   => ['required','string'],
            'course_id' => ['nullable','integer','min:1'],
            'files.*'   => ['nullable','file','max:20480'],
        ]);

        DB::connection('diploma')->beginTransaction();
        try {
            // 📨 إنشاء الطلب في قاعدة الدبلوم
            $requestId = DB::connection('diploma')
                ->table('support_requests')
                ->insertGetId([
                    'user_id'    => $data['user_id'],
                    'title'      => $data['title'],
                    'details'    => $data['details'],
                    'course_id'  => $data['course_id'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            // 📎 رفع الملفات إلى storage
            $uploaded = $request->file('files', []);
            if (!is_array($uploaded)) $uploaded = [$uploaded];

            $fileRows = [];
            foreach ($uploaded as $file) {
                if (!$file) continue;

                $path = $file->store('support/'.date('Y').'/'.date('m'));
                $fileRows[] = [
                    'request_id'    => $requestId,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type'     => $file->getMimeType(),
                    'file_size'     => $file->getSize(),
                    'path'          => $path,
                    'created_at'    => now(),
                ];
            }

            if (!empty($fileRows)) {
                DB::connection('diploma')
                    ->table('support_request_files')
                    ->insert($fileRows);
            }

            DB::connection('diploma')->commit();
            return $this->j(['status'=>'ok','request_id'=>$requestId], 201);

        } catch (\Throwable $e) {
            DB::connection('diploma')->rollBack();
            return $this->j(['status'=>'error','message'=>'تعذر إنشاء طلب الدعم.'], 500);
        }
    }
}

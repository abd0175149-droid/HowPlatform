<?php

namespace App\Http\Controllers\Mobile\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class ServiceRequestController extends Controller
{
    /** كل العمليات على قاعدة الدبلوم */
    private const DIP_CONN = 'diploma';

    // حدود الرفع (10MB) وأنواع الميم المقبولة
    private const MAX_FILE_BYTES = 10 * 1024 * 1024;
    private const ALLOWED_MIME = [
        'image/jpeg','image/png','image/gif','image/webp',
        'video/mp4','video/webm','video/ogg',
        'application/pdf',
        'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip','application/x-rar-compressed',
        'audio/mpeg','audio/mp4','audio/ogg','audio/webm','audio/wav','audio/x-m4a'
    ];

    private function dipDb()      { return DB::connection(self::DIP_CONN); }
    private function dipSchema()  { return Schema::connection(self::DIP_CONN); }

    private function ok(array $data=[], string $message='OK', int $status=200): JsonResponse
    {
        return response()->json(['success'=>true,'message'=>$message,'data'=>$data], $status);
    }
    private function fail(string $message, int $status=400, array $extra=[]): JsonResponse
    {
        return response()->json(['success'=>false,'message'=>$message]+$extra, $status);
    }

    /**
     * GET /api/mobile/support/meta
     * يجلب مساقات الطالب (diploma_users → course_offerings/courses/semesters) من نفس اتصال الدبلوم
     */
    public function meta(Request $request): JsonResponse
    {
        $userId = (int) ($request->user()->id ?? 0);
        if ($userId <= 0) return $this->fail('Unauthenticated', 401);

        $stu = $this->dipDb()->table('diploma_users')
            ->select('user_id','university_id','academic_year_id')
            ->where('user_id', $userId)
            ->first();

        if (!$stu) {
            return $this->ok([
                'courses' => [],
                'note'    => 'لا توجد بيانات للطالب في diploma_users'
            ]);
        }

        $courses = $this->dipDb()->table('course_offerings as co')
            ->join('courses as c', 'c.id', '=', 'co.course_id')
            ->join('semesters as s', 's.id', '=', 'co.semester_id')
            ->where('co.university_id', $stu->university_id)
            ->where('s.academic_year_id', $stu->academic_year_id)
            ->orderBy('c.name', 'ASC')
            ->get([
                'co.id as offering_id',
                'c.id  as course_id',
                'c.name as course_name',
                's.id as semester_id',
                's.name as semester_name'
            ]);

        return $this->ok(['courses' => $courses]);
    }

    /**
     * POST /api/mobile/support/requests
     * إنشاء طلب دعم + رفع مرفقات (الكل على اتصال الدبلوم)
     */
    public function store(Request $request): JsonResponse
    {
        $uid = (int) ($request->user()->id ?? 0);
        if ($uid <= 0) return $this->fail('Unauthenticated', 401);

        $subject     = trim((string) $request->input('subject',''));
        $description = trim((string) $request->input('description',''));
        $offeringId  = (int) ($request->input('course_offering_id') ?? 0);

        $errors = [];
        if (mb_strlen($subject) < 3)      $errors['subject'] = 'اكتب موضوعًا واضحًا (3 أحرف على الأقل).';
        if (mb_strlen($description) < 10) $errors['description'] = 'اكتب تفاصيل كافية (10 أحرف على الأقل).';
        if ($errors) return $this->fail('Validation error', 422, ['fields'=>$errors]);

        $priority = $this->decidePriorityFromSubscription($uid);

        $db = $this->dipDb();
        $db->beginTransaction();
        try {
            $reqId = $db->table('service_requests')->insertGetId([
                'user_id'            => $uid,
                'course_offering_id' => $offeringId ?: null,
                'subject'            => $subject,
                'description'        => $description,
                'priority'           => $priority,
                'status'             => 'pending',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            $saved = $this->handleUploadsForRequest($request, $reqId, $uid);

            $db->commit();
            return $this->ok([
                'request_id' => $reqId,
                'priority'   => $priority,
                'files'      => $saved,
            ], 'تم إرسال طلبك بنجاح');
        } catch (\Throwable $e) {
            $db->rollBack();
            return $this->fail('Server error while creating request', 500, ['ref'=>'SR-CRT-500']);
        }
    }

    /**
     * GET /api/mobile/support/requests
     * قائمة الطلبات + عدادات الحالات + عدّاد غير المقروء (كلها من نفس اتصال الدبلوم)
     */
    public function index(Request $request): JsonResponse
    {
        $uid = (int) ($request->user()->id ?? 0);
        if ($uid <= 0) return $this->fail('Unauthenticated', 401);

        $q      = trim((string) $request->query('q',''));
        $status = trim((string) $request->query('status',''));
        $offset = max(0, (int) $request->query('offset', 0));
        $limit  = min(20, max(1, (int) $request->query('limit', 5)));

        $db   = $this->dipDb();
        $base = $db->table('service_requests as sr')->where('sr.user_id', $uid);

        if ($status !== '') $base->where('sr.status', $status);
        if ($q !== '') {
            $base->where(function($w) use($q){
                $w->where('sr.subject','like',"%{$q}%")
                  ->orWhere('sr.description','like',"%{$q}%");
            });
        }

        $total = (clone $base)->count('sr.id');

        $readsEnabled = $this->dipSchema()->hasTable('service_message_reads')
                      && $this->dipSchema()->hasTable('service_messages');

        $select = [
            'sr.id','sr.subject','sr.description','sr.status','sr.priority','sr.created_at',
            'sr.course_offering_id',
        ];
        if ($readsEnabled) {
            $select[] = DB::raw(
                "(SELECT COUNT(1)
                   FROM service_messages m
              LEFT JOIN service_message_reads r
                     ON r.message_id = m.id AND r.reader_user_id = {$uid}
                  WHERE m.service_request_id = sr.id
                    AND m.sender_user_id <> {$uid}
                    AND r.message_id IS NULL) AS unread_count"
            );
        } else {
            $select[] = DB::raw("0 AS unread_count");
        }

        $items = $base->orderBy('sr.created_at','DESC')
            ->offset($offset)->limit($limit)
            ->get($select);

        // إلحاق أسماء المساق/الفصل من course_offerings (نفس الاتصال)
        $offeringIds = collect($items)->pluck('course_offering_id')->filter()->unique()->values()->all();
        $mapNames = [];
        if (!empty($offeringIds)) {
            $rows = $this->dipDb()->table('course_offerings as co')
                ->join('courses as c','c.id','=','co.course_id')
                ->join('semesters as s','s.id','=','co.semester_id')
                ->whereIn('co.id', $offeringIds)
                ->get(['co.id as offering_id','c.name as course_name','s.name as semester_name']);
            foreach ($rows as $r) $mapNames[$r->offering_id] = $r;
        }

        $items = collect($items)->map(function($r) use($mapNames){
            $extra = $mapNames[$r->course_offering_id] ?? null;
            $r->course_name   = $extra->course_name   ?? null;
            $r->semester_name = $extra->semester_name ?? null;
            return $r;
        });

        // عدادات الحالات
        $statuses = ['pending','in_progress','resolved','cancelled'];
        $counts = [];
        foreach ($statuses as $st) {
            $counts[$st] = $db->table('service_requests')->where('user_id',$uid)->where('status',$st)->count();
        }
        $counts['all'] = $db->table('service_requests')->where('user_id',$uid)->count();

        return $this->ok([
            'items'    => $items->values(),
            'total'    => $total,
            'offset'   => $offset,
            'limit'    => $limit,
            'has_more' => ($offset + $limit) < $total,
            'counts'   => $counts,
        ]);
    }

    /**
     * GET /api/mobile/support/requests/{id}
     * تفاصيل الطلب + المرفقات الأولية + أسماء المساق/الفصل (كلها على الدبلوم)
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $uid = (int) ($request->user()->id ?? 0);
        if ($uid <= 0) return $this->fail('Unauthenticated', 401);

        $db  = $this->dipDb();
        $req = $db->table('service_requests')->where('id',$id)->where('user_id',$uid)->first();
        if (!$req) return $this->fail('Request not found', 404);

        $files = $db->table('service_request_files as srf')
            ->join('files as f','f.id','=','srf.file_id')
            ->where('srf.service_request_id', $id)
            ->whereNull('srf.message_id')
            ->orderBy('srf.display_order','ASC')
            ->get([
                'f.id as file_id','f.file_name','f.file_path','f.mime_type',
                DB::raw("(COALESCE(f.file_size_bytes, f.file_size)) as file_size_bytes"),
                'srf.file_type',
                DB::raw("CASE WHEN srf.file_type='voice_recording' OR f.mime_type LIKE 'audio/%' THEN 1 ELSE 0 END as is_voice")
            ]);

        $course_name = $semester_name = null;
        if (!empty($req->course_offering_id)) {
            $row = $this->dipDb()->table('course_offerings as co')
                ->join('courses as c','c.id','=','co.course_id')
                ->join('semesters as s','s.id','=','co.semester_id')
                ->where('co.id', $req->course_offering_id)
                ->first(['c.name as course_name','s.name as semester_name']);
            if ($row) { $course_name = $row->course_name; $semester_name = $row->semester_name; }
        }

        $reqArr = (array) $req;
        $reqArr['course_name']   = $course_name;
        $reqArr['semester_name'] = $semester_name;

        return $this->ok([
            'request'     => $reqArr,
            'attachments' => $files
        ]);
    }

    /**
     * POST /api/mobile/support/requests/{id}/cancel
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $uid = (int) ($request->user()->id ?? 0);
        if ($uid <= 0) return $this->fail('Unauthenticated', 401);

        $db  = $this->dipDb();
        $row = $db->table('service_requests')->where('id',$id)->where('user_id',$uid)->first(['id','status']);
        if (!$row) return $this->fail('Request not accessible', 403);
        if (in_array($row->status, ['resolved','cancelled'], true)) {
            return $this->fail('Cannot cancel this request', 422);
        }

        $db->table('service_requests')->where('id',$id)->update(['status'=>'cancelled','updated_at'=>now()]);
        return $this->ok([], 'Request cancelled.');
    }

    /** تحويل مستوى الخطة إلى أولوية مرئية */
    private function levelToPriority(int $lvl): string
    {
        return $lvl >= 3 ? 'urgent' : ($lvl === 2 ? 'high' : ($lvl === 1 ? 'medium' : 'low'));
    }

    /** أولوية من user_subscriptions/plans على نفس اتصال الدبلوم */
    private function decidePriorityFromSubscription(int $userId): string
    {
        if (!$this->dipSchema()->hasTable('user_subscriptions') || !$this->dipSchema()->hasTable('plans')) return 'low';

        $lvl = (int) ($this->dipDb()->table('user_subscriptions as us')
            ->join('plans as p','p.id','=','us.plan_id')
            ->where('us.user_id',$userId)
            ->where('us.status','active')
            ->where('us.start_at','<=',now())
            ->where(function($w){ $w->whereNull('us.end_at')->orWhere('us.end_at','>=',now()); })
            ->max('p.level') ?? 0);

        return $this->levelToPriority($lvl);
    }

    /** رفع المرفقات وتسجيلها على نفس اتصال الدبلوم */
    private function handleUploadsForRequest(Request $request, int $reqId, int $uploaderId): array
    {
        $saved = [];
        if (!$request->hasFile('files')) return $saved;

        $files = $request->file('files');
        if (!is_array($files)) $files = [$files];

        $dir = "service_requests/{$reqId}";
        $order = 0;

        $db = $this->dipDb();

        foreach ($files as $file) {
            if (!$file->isValid()) continue;
            if ($file->getSize() > self::MAX_FILE_BYTES) continue;

            $mime = $file->getMimeType() ?: '';
            if (!in_array($mime, self::ALLOWED_MIME, true)) continue;

            $randomName = bin2hex(random_bytes(10)) . '.' . $file->getClientOriginalExtension();
            $path = Storage::disk('local')->putFileAs($dir, $file, $randomName); // storage/app/...

            $hasSizeBytes = $this->dipSchema()->hasColumn('files','file_size_bytes');
            $hasUploader  = $this->dipSchema()->hasColumn('files','uploader_id');
            $hasUploadedBy= $this->dipSchema()->hasColumn('files','uploaded_by');

            $filesRow = [
                'file_name'  => $file->getClientOriginalName(),
                'file_path'  => Storage::disk('local')->path($path),
                'mime_type'  => $mime,
                ($hasSizeBytes ? 'file_size_bytes':'file_size') => (int) $file->getSize(),
                ($hasUploader ? 'uploader_id':'uploaded_by')     => $uploaderId,
                'uploaded_at' => now(),
            ];

            $db->table('files')->insert($filesRow);
            $fileId = (int) $db->getPdo()->lastInsertId();

            $db->table('service_request_files')->insert([
                'service_request_id' => $reqId,
                'message_id'         => null,
                'file_id'            => $fileId,
                'file_type'          => 'attachment',
                'display_order'      => $order++,
            ]);

            $saved[] = [
                'file_id'         => $fileId,
                'file_name'       => $filesRow['file_name'],
                'file_size_bytes' => (int) $file->getSize(),
                'mime_type'       => $mime,
            ];
        }

        return $saved;
    }
}

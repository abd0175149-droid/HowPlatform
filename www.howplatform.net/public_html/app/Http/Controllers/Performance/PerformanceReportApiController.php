<?php
namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\{Week, Task, Student, Group, StudentTaskProgress};

class PerformanceReportApiController extends Controller
{
    // ========= Meta =========
    public function weeks(){
        return Week::orderBy('week_number')->get(['id','week_number','title']);
    }

    public function tasks(Request $r){
        $q = Task::with('week:id,week_number,title')->orderBy('id');
        if ($r->filled('week_id')) $q->where('week_id', (int)$r->query('week_id'));
        return $q->get(['id','title','task_url','week_id']);
    }

    public function groups(Request $r){
        if ($r->query('with') === 'members') {
            return Group::with(['students:id,full_name'])->orderBy('name')->get(['id','name']);
        }
        return Group::withCount('students')->orderBy('name')->get(['id','name']);
    }

    public function students(Request $r){
        $q = Student::query()->select(['id','full_name'])->orderBy('full_name');
        if ($r->filled('group_id')) {
            $gid = (int)$r->query('group_id');
            $q->whereHas('groups', fn($g)=> $g->where('groups.id',$gid));
        }
        return $q->get();
    }

    // ========= Helpers =========
    protected function statusToArabic(?string $s): string {
        return match(strtolower((string)$s)){
            'on_time'       => 'في الموعد',
            'late'          => 'متأخر',
            'not_submitted' => 'غير مُسلّم',
            default         => (string)$s,
        };
    }

    protected function weekLabel(?Week $w): ?string {
        if (!$w) return null;
        // مثال: "أسبوع 3 — التعلم النشط"
        return 'أسبوع '.$w->week_number.' — '.($w->title ?? '');
        // إن أردت اسم الأسبوع فقط: return $w->title ?? null;
    }

    protected function buildReportBaseQuery(Request $r)
    {
        $q = StudentTaskProgress::query()
            ->with([
                'student:id,full_name',
                'task:id,week_id,title',
                'task.week:id,week_number,title'   // ضروري لاسم الأسبوع
            ])
            ->select(['id','student_id','task_id','submission_status','grade']);

        if ($r->filled('week_id')) {
            $q->whereHas('task', fn($t)=> $t->where('week_id', (int)$r->query('week_id')));
        }
        if ($r->filled('task_id')) {
            $q->where('task_id', (int)$r->query('task_id'));
        }
        if ($r->filled('status')) {
            $q->whereIn('submission_status', (array)$r->input('status'));
        }
        if ($r->filled('group_id')) {
            $gid = (int)$r->query('group_id');
            $q->whereHas('student.groups', fn($g)=> $g->where('groups.id',$gid));
        }
        if ($r->filled('q')) {
            $term = '%'.$r->query('q').'%';
            $q->whereHas('student', fn($s)=> $s->where('full_name','like',$term));
        }

        // ترشيح رقمي اختياري للعلامة مع بقاء العمود نصيًا
        $expr = "CASE WHEN grade REGEXP '^-?[0-9]+(\\.[0-9]+)?$' THEN CAST(grade AS DECIMAL(10,2)) ELSE NULL END";
        if ($r->filled('grade_min')) $q->whereRaw("$expr >= ?", [(float)$r->query('grade_min')]);
        if ($r->filled('grade_max')) $q->whereRaw("$expr <= ?", [(float)$r->query('grade_max')]);

        return $q;
    }

    // ========= جدول التقرير التفصيلي =========
    public function reportsSummary(Request $r)
    {
        $rows = $this->buildReportBaseQuery($r)
            ->orderByDesc('id')
            ->get()
            ->map(function($p){
                $week = $p->task?->week;
                return [
                    'student'     => $p->student?->full_name,
                    'week_id'     => $week?->id,
                    'week_label'  => $this->weekLabel($week),     // اسم الأسبوع من القاعدة
                    'task_title'  => $p->task?->title,
                    'status'      => $p->submission_status,
                    'status_ar'   => $this->statusToArabic($p->submission_status), // عربي
                    'grade'       => $p->grade,
                ];
            });

        return response()->json($rows);
    }

    // ========= إحصاءات مقارنة حسب المهمة =========
    public function reportsAggregates(Request $r)
    {
        $base = $this->buildReportBaseQuery($r);

        $agg = (clone $base)
            ->select([
                'task_id',
                DB::raw('COUNT(DISTINCT student_id) AS students_cnt'),
                DB::raw("SUM(CASE WHEN submission_status='on_time' THEN 1 ELSE 0 END) AS on_time_cnt"),
                DB::raw("SUM(CASE WHEN submission_status='late' THEN 1 ELSE 0 END) AS late_cnt"),
                DB::raw("SUM(CASE WHEN submission_status='not_submitted' THEN 1 ELSE 0 END) AS not_submitted_cnt"),
                DB::raw("SUM(CASE WHEN grade IS NOT NULL AND grade<>'' THEN 1 ELSE 0 END) AS graded_cnt"),
                DB::raw("SUM(CASE WHEN grade IS NULL OR grade='' THEN 1 ELSE 0 END) AS need_grade_cnt")
            ])
            ->groupBy('task_id')
            ->get();

        if ($agg->isEmpty()) return response()->json([]);

        $tasks = Task::with('week:id,week_number,title')
            ->whereIn('id', $agg->pluck('task_id'))
            ->get(['id','title','week_id'])
            ->keyBy('id');

        $rows = $agg->map(function($a) use ($tasks){
            $t = $tasks[$a->task_id] ?? null;
            $w = $t?->week;
            return [
                'task_id'           => $a->task_id,
                'task_title'        => $t?->title ?? ('مهمة #'.$a->task_id),
                'week_id'           => $w?->id,
                'week_label'        => $w ? ('أسبوع '.$w->week_number.' — '.($w->title??'')) : null,
                'students_cnt'      => (int)$a->students_cnt,
                'on_time_cnt'       => (int)$a->on_time_cnt,
                'late_cnt'          => (int)$a->late_cnt,
                'not_submitted_cnt' => (int)$a->not_submitted_cnt,
                'graded_cnt'        => (int)$a->graded_cnt,
                'need_grade_cnt'    => (int)$a->need_grade_cnt,
            ];
        })->values();

        return response()->json($rows);
    }

    // ========= صفحة طباعة HTML (بدون تبويب جديد) =========
    public function printView(Request $r)
    {
        $rows = $this->buildReportBaseQuery($r)->orderBy('student_id')->get();
        // تُستخدم مع IFrame مخفي من الواجهة. إن مررت auto=1 سيتم استدعاء window.print().
        return response()->view('print.performance_report', [
            'rows'    => $rows,
            'filters' => $r->all(),
        ]);
    }

    // ========= تصدير Excel (أعدناه) =========
    public function exportExcel(Request $r)
    {
        // استعمال PhpSpreadsheet
        $rows = $this->buildReportBaseQuery($r)->orderBy('student_id')->get();

        // تحميل الكلاسات فقط عند الطلب حتى لا يفشل إن لم تُثبّت الحزمة بعد
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            return response()->json(['message'=>'حزمة PhpSpreadsheet غير مثبّتة.'], 500);
        }

        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->fromArray(['الطالب','الأسبوع','المهمة','حالة التسليم','العلامة'], null, 'A1');

        $i = 2;
        foreach ($rows as $p) {
            $week = $p->task?->week;
            $sheet->fromArray([
                $p->student?->full_name,
                $this->weekLabel($week),
                $p->task?->title,
                $this->statusToArabic($p->submission_status),
                $p->grade
            ], null, 'A'.$i++);
        }

        $file = 'performance_report_'.date('Ymd_His').'.xlsx';
        $path = storage_path('app/public/'.$file);
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($path);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    // ========= استيراد أسماء كنص =========
    public function importStudentsText(Request $r)
    {
        $data = $r->validate(['names_text'=>'required|string|max:65535']);
        $lines = preg_split("/\r\n|\r|\n/", $data['names_text']);
        $added = 0; $skipped = 0;

        DB::transaction(function () use ($lines, &$added, &$skipped) {
            foreach ($lines as $line) {
                $name = trim((string)$line);
                if ($name===''){ $skipped++; continue; }
                $student = Student::firstOrCreate(['full_name'=>$name], []);
                $student->wasRecentlyCreated ? $added++ : $skipped++;
            }
        });

        return response()->json(['status'=>'ok','added'=>$added,'skipped'=>$skipped,'message'=>"تم استيراد {$added} اسمًا (تجاهل {$skipped})."]);
    }

    // ========= Aliases قديمة =========
    public function reportSummary(Request $r){ return $this->reportsSummary($r); }
    public function reportProgress(Request $r){ return response()->json(['labels'=>[], 'data'=>[], 'title'=>'—']); }
}

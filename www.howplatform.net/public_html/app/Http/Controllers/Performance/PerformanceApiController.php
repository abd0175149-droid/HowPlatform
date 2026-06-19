<?php
namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use App\Models\{Week, Task, Student, Group, StudentTaskProgress};

class PerformanceApiController extends Controller
{
    // ================= Meta =================
    public function weeks(){
        return Week::orderBy('week_number')->get(['id','week_number','title']);
    }

    public function tasks(Request $r){
        $q = Task::with('week:id,week_number,title')->orderBy('id');
        if ($r->filled('week_id')) $q->where('week_id', $r->integer('week_id'));
        return $q->get(['id','title','task_url','week_id']);
    }

    // ===== NEW: Create Week =====
    public function createWeek(Request $r)
    {
        $data = $r->validate([
            'week_number' => ['required', 'integer', 'min:1', 'unique:weeks,week_number'],
            'title'       => ['required', 'string', 'max:255'],
            'start_date'  => ['nullable', 'date'],
            'end_date'    => ['nullable', 'date'],
        ]);
    
        $week = Week::create($data);
    
        return response()->json([
            'status'  => 'ok',
            'message' => 'تمت إضافة الأسبوع بنجاح.',
            'week'    => $week->only(['id', 'week_number', 'title']),
        ]);
    }


    // ===== NEW: Create Task =====
    public function createTask(Request $r)
    {
        $data = $r->validate([
            'week_id'  => ['required','integer','exists:weeks,id'],
            'title'    => ['required','string','max:255'],
            'task_url' => ['nullable','url','max:1024'],
            // أضف حقولك الأخرى إن كانت موجودة (type, due_at, ... إلخ)
        ]);

        $task = Task::create($data);

        return response()->json([
            'status'  => 'ok',
            'message' => 'تمت إضافة المهمة بنجاح.',
            'task'    => $task->only(['id','week_id','title','task_url']),
        ]);
    }

    // ================= Groups =================
    public function groups(Request $r){
        if ($r->query('with') === 'members') {
            return Group::with(['students:id,full_name'])->orderBy('name')->get(['id','name']);
        }
        return Group::withCount('students')->orderBy('name')->get(['id','name']);
    }

    public function students(Request $r){
        $q = Student::query()->select(['id','full_name'])->orderBy('full_name');
        if ($r->filled('group_id')) {
            $gid = $r->integer('group_id');
            $q->whereHas('groups', fn($g)=> $g->where('groups.id',$gid));
        }
        return $q->get();
    }

    public function renameGroup($id, Request $r){
        $data = $r->validate(['name'=>'required|string|max:255|unique:groups,name,'.$id]);
        $g = Group::findOrFail($id);
        $g->update($data);
        return response()->json(['status'=>'ok','message'=>'تم تحديث اسم المجموعة.']);
    }

    public function deleteGroup($id){
        $g = Group::findOrFail($id);
        $g->delete();
        return response()->json(['status'=>'ok','message'=>'تم حذف المجموعة.']);
    }

    public function groupMembers($id){
        return Group::with(['students:id,full_name'])->findOrFail($id)->students;
    }

    public function assignMany(Request $r){
        $data = $r->validate([
            'group_id' => 'required|exists:groups,id',
            'student_ids'=>'required|array|min:1',
            'student_ids.*'=>'integer|exists:students,id',
        ]);
        Group::findOrFail($data['group_id'])->students()->syncWithoutDetaching($data['student_ids']);
        return response()->json(['status'=>'ok','message'=>'تمت الإضافة للمجموعة.']);
    }

    public function unassignMany(Request $r){
        $data = $r->validate([
            'group_id' => 'required|exists:groups,id',
            'student_ids'=>'required|array|min:1',
            'student_ids.*'=>'integer|exists:students,id',
        ]);
        Group::findOrFail($data['group_id'])->students()->detach($data['student_ids']);
        return response()->json(['status'=>'ok','message'=>'تمت الإزالة من المجموعة.']);
    }

    public function assignBulk(Request $r){
        $data = $r->validate([
            'group_ids'=>'required|array|min:1',
            'group_ids.*'=>'integer|exists:groups,id',
            'student_ids'=>'required|array|min:1',
            'student_ids.*'=>'integer|exists:students,id',
        ]);
        foreach ($data['group_ids'] as $gid) {
            Group::find($gid)?->students()->syncWithoutDetaching($data['student_ids']);
        }
        return response()->json(['status'=>'ok','message'=>'تمت إضافة الطلبة إلى المجموعات المحددة.']);
    }

    // ================= Tracking Grid =================
    public function tracking(Request $r){
        $r->validate([
            'week_id'  => 'required|exists:weeks,id',
            'group_id' => 'nullable|exists:groups,id',
        ]);

        $weekId  = $r->integer('week_id');
        $groupId = $r->filled('group_id') ? $r->integer('group_id') : null;

        $tasks = Task::where('week_id',$weekId)->orderBy('id')->get(['id','title','task_url']);

        $studentsQ = Student::query()->orderBy('full_name')->select(['id','full_name']);
        if ($groupId) {
            $studentsQ->whereHas('groups', fn($q)=> $q->where('groups.id',$groupId));
        }
        $students = $studentsQ->get();

        $progressRows = StudentTaskProgress::whereIn('task_id',$tasks->pluck('id'))
            ->whereIn('student_id',$students->pluck('id'))
            ->get(['student_id','task_id','submission_status','grade']);

        $map = [];
        foreach ($progressRows as $p) { $map[$p->student_id][$p->task_id] = $p; }

        $grid = [];
        foreach ($students as $s){
            $row = ['student_id'=>$s->id,'student_name'=>$s->full_name,'tasks'=>[]];
            foreach ($tasks as $t){
                $p = $map[$s->id][$t->id] ?? null;
                $row['tasks'][] = [
                    'task_id'=>$t->id, 'title'=>$t->title, 'task_url'=>$t->task_url,
                    'submission_status'=>$p?->submission_status ?? 'not_submitted',
                    'grade'=>$p?->grade,
                ];
            }
            $grid[] = $row;
        }

        return response()->json(['tasks'=>$tasks,'students'=>$students,'grid'=>$grid]);
    }

    // ================= Bulk Update =================
    public function bulkUpdate(Request $r){
        $items = collect($r->input('items', []))->map(function ($it) {
            return [
                'student_id'        => (int)($it['student_id'] ?? 0),
                'task_id'           => (int)($it['task_id'] ?? 0),
                'submission_status' => isset($it['submission_status']) && $it['submission_status'] !== '' ? (string)$it['submission_status'] : null,
                'grade'             => isset($it['grade']) && $it['grade'] !== '' ? (string)$it['grade'] : null,
            ];
        })->values()->all();

        $v = Validator::make(['items'=>$items], [
            'items'                     => 'required|array|min:1',
            'items.*.student_id'        => 'required|integer|exists:students,id',
            'items.*.task_id'           => 'required|integer|exists:tasks,id',
            'items.*.submission_status' => ['nullable', Rule::in(['not_submitted','on_time','late'])],
            'items.*.grade'             => 'nullable|string|max:32',
        ]);
        if ($v->fails()) return response()->json(['status'=>'error','errors'=>$v->errors()],422);

        DB::transaction(function () use ($items) {
            foreach ($items as $it) {
                $row = StudentTaskProgress::firstOrNew([
                    'student_id'=>$it['student_id'],
                    'task_id'   =>$it['task_id'],
                ]);
                if (array_key_exists('submission_status',$it)) $row->submission_status = $it['submission_status'] ?? 'not_submitted';
                if (array_key_exists('grade',$it))             $row->grade = $it['grade'];
                $row->save();
            }
        });

        $refetch = StudentTaskProgress::whereIn('student_id', array_column($items,'student_id'))
                    ->whereIn('task_id', array_column($items,'task_id'))
                    ->get(['student_id','task_id','submission_status','grade']);

        return response()->json(['status'=>'ok','message'=>'تم الحفظ.','updated'=>$refetch]);
    }
}

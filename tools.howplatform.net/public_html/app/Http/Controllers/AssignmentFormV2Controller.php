<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\FormTemplate;
use App\Models\FormSubmission;
use App\Models\UserFormProfile;
use App\Models\Diploma\University;
use App\Models\Diploma\Specialty;
use App\Models\Diploma\TaskType;
use App\Models\Diploma\AcademicYear;
use App\Models\Diploma\Semester;
use App\Models\Diploma\Course;
use App\Services\FormTemplateResolver;
use App\Services\FormValidationFactory;
use App\Services\PdfStampService;

class AssignmentFormV2Controller extends Controller
{
    public function __construct(
        private FormTemplateResolver $resolver,
        private FormValidationFactory $validatorFactory,
        private PdfStampService $pdfStamp
    ) {}

    private function currentUserId(): ?int {
        $auth = session('auth_user');
        if (is_array($auth) && isset($auth['id'])) return (int)$auth['id'];
        if (is_object($auth) && isset($auth->id))   return (int)$auth->id;
        return null;
    }

    public function index(Request $r){
        $uid = $this->currentUserId();
        if (!$uid){
            $redirect = url()->current();
            return redirect()->route('login', ['redirect'=>$redirect])->with('status','الرجاء تسجيل الدخول أولاً.');
        }
        return view('tools.assignment-form-v2'); // الواجهة أدناه
    }

    /** DDL + Defaults */
    public function meta(Request $r){
        $uid = $this->currentUserId();
        abort_if(!$uid, 401, 'غير مصرح.');
        $defaults = UserFormProfile::forUser($uid)->where('scope','global')->first()?->values ?? [];

        $universities = University::orderBy('name')->get(['id','name']);
        $specialties  = Specialty::orderBy('name')->get(['id','name']);
        $task_types   = TaskType::orderBy('name')->get(['id','name']);
        $years        = AcademicYear::orderBy('label')->get(['id','label']);
        $semesters    = Semester::orderBy('name')->get(['id','name','academic_year_id']);
        $courses      = Course::orderBy('name')->get(['id','name']);

        return response()->json(compact('universities','specialties','task_types','years','semesters','courses','defaults'));
    }

    /** Resolve template by context */
    public function schema(Request $r){
        $ctx = $r->validate([
            'university_id'=>'nullable|integer',
            'course_id'=>'nullable|integer',
            'task_type_id'=>'nullable|integer',
            'academic_year_id'=>'nullable|integer',
            'semester_id'=>'nullable|integer',
            'week_id'=>'nullable|integer',
        ]);
        $tpl = $this->resolver->resolve($ctx);
        abort_if(!$tpl, 404, 'لا يوجد قالب مناسب لهذا السياق.');

        return response()->json([
            'template_id'  => $tpl->id,
            'template_key' => $tpl->key,
            'version'      => $tpl->version,
            'ui_schema'    => $tpl->ui_schema,
            'data_schema'  => $tpl->data_schema,
        ]);
    }

    /** Preview PDF (iframe) */
    public function preview(Request $r){
        // لأغراض المعاينة: نستخدم القالب المحلول + نُظهر صفحة PDF فارغة
        // استخدم نفس مسار V1 لو رغبت، أو اكتفِ بإطار PDF من مسار الملف
        return app(AssignmentFormController::class)->preview($r); // إعادة استخدام V1 إن شئت
    }

    /** Save submission JSON (+ remember profile optional) */
    public function submit(Request $r){
        $uid = $this->currentUserId();
        abort_if(!$uid, 401, 'غير مصرح.');

        $template = FormTemplate::findOrFail($r->integer('template_id'));
        [$rules,$names] = $this->validatorFactory->make($template->data_schema);
        $values = $r->validate($rules, [], $names);

        if ($r->boolean('remember_profile')){
            // احفظ الحقول الشائعة فقط
            $profile = array_intersect_key($values, array_flip([
              'student_name','student_id','specialty','course_teacher','academic_advisor'
            ]));
            UserFormProfile::remember($uid, 'global', $profile);
        }

        FormSubmission::create([
            'form_template_id' => $template->id,
            'template_version' => $template->version,
            'user_id'          => $uid,
            'university_id'    => $r->integer('university_id'),
            'course_id'        => $r->integer('course_id'),
            'task_id'          => $r->integer('task_id'),
            'values'           => $values,
            'status'           => 'submitted',
            'submitted_at'     => now(),
        ]);

        return response()->json(['ok'=>true]);
    }

    /** Generate PDF */
    public function stamp(Request $r){
        $uid = $this->currentUserId();
        abort_if(!$uid, 401, 'غير مصرح.');

        $template = FormTemplate::findOrFail($r->integer('template_id'));
        [$rules,$names] = $this->validatorFactory->make($template->data_schema);
        $values = $r->validate($rules, [], $names);

        // صياغة التاريخ (العربية)
        if (!empty($values['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$values['date'])){
            $daysAr = ['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'];
            $d = new \DateTime($values['date']); $dow=$daysAr[(int)$d->format('w')];
            $values['date'] = sprintf('%s %s/%s/%s', $dow, $d->format('d'), $d->format('m'), $d->format('Y'));
        }

        $binary = $this->pdfStamp->render($template, [
            'ctx'    => $r->only(['university_id','course_id','task_type_id']),
            'values' => $values,
        ]);

        $file = 'Task-Form-'.$template->key.'.pdf';
        return response($binary, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"$file\"",
            'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'              => 'no-cache',
        ]);
    }

    public function saveProfile(Request $r){
        $uid = $this->currentUserId();
        abort_if(!$uid, 401, 'غير مصرح.');
        $data = $r->validate(['values'=>'required|array','scope'=>'nullable|string']);
        \App\Models\UserFormProfile::remember($uid, $data['scope'] ?? 'global', $data['values']);
        return response()->json(['ok'=>true]);
    }

    public function rate(Request $r){
        // أعد استخدام منطق V1 (Tool/ToolStat) إن رغبت — أو تجاهل إن غير مطلوب الآن.
        return app(AssignmentFormController::class)->rate($r);
    }
}


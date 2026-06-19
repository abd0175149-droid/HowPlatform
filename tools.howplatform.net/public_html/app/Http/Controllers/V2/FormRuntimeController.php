<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FormRuntimeController extends Controller
{
    private function tools() { return DB::connection(); } // الافتراضي = قاعدة الأدوات

    public function resolve(Request $req)
    {
        // سياق الاختيار من الواجهة
        $university_id = (int) $req->query('university_id');
        $task_type_id  = (int) $req->query('task_type_id');

        // احضر جميع التعيينات التي قد تنطبق
        $q = $this->tools()->table('template_assignments')
            ->select('id','form_template_id','university_id','task_type_id','match_priority')
            ->orderBy('match_priority', 'asc');

        $candidates = $q->get();

        // احسب درجة التطابق: كل حقل غير null يجب أن يساوي المدخل
        $best = null; $bestScore = -1; $bestPriority = PHP_INT_MAX;
        foreach ($candidates as $row) {
            $ok = true; $score = 0;

            if (!is_null($row->university_id)) {
                if ((int)$row->university_id !== $university_id) $ok = false; else $score++;
            }
            if (!is_null($row->task_type_id)) {
                if ((int)$row->task_type_id !== $task_type_id)   $ok = false; else $score++;
            }

            if (!$ok) continue;

            // أفضّل الأعلى Score ثم الأقل Priority
            if ($score > $bestScore || ($score === $bestScore && $row->match_priority < $bestPriority)) {
                $best = $row; $bestScore = $score; $bestPriority = $row->match_priority;
            }
        }

        if (!$best) {
            return response()->json(['ok'=>false,'msg'=>'لم يُعثر على قالب مناسب لهذا السياق'], 404);
        }

        $tpl = $this->tools()->table('form_templates')->where('id',$best->form_template_id)->first();
        if (!$tpl || $tpl->status !== 'published') {
            return response()->json(['ok'=>false,'msg'=>'القالب غير منشور أو غير موجود'], 404);
        }

        return response()->json([
            'ok' => true,
            'template' => [
                'id'      => (int)$tpl->id,
                'key'     => $tpl->key,
                'title'   => $tpl->title,
                'version' => (int)$tpl->version,
                'status'  => $tpl->status,
            ],
        ]);
    }

    public function schema(Request $req)
    {
        $template_id = (int) $req->query('template_id');

        $tpl = $this->tools()->table('form_templates')->where('id',$template_id)->first();
        if (!$tpl) return response()->json(['ok'=>false,'msg'=>'Template not found'], 404);

        return response()->json([
            'ok'          => true,
            'template_id' => (int)$tpl->id,
            'version'     => (int)$tpl->version,
            'data_schema' => json_decode($tpl->data_schema, true),
            'ui_schema'   => json_decode($tpl->ui_schema, true),
        ]);
    }

    public function validateValues(Request $req)
    {
        $template_id = (int) $req->input('template_id');
        $values      = (array) $req->input('values', []);

        $tpl = $this->tools()->table('form_templates')->where('id',$template_id)->first();
        if (!$tpl) return response()->json(['ok'=>false,'msg'=>'Template not found'], 404);

        $dataSchema = json_decode($tpl->data_schema, true);
        [$rules, $messages] = $this->buildRules($dataSchema, $values);

        $v = \Validator::make($values, $rules, $messages);
        if ($v->fails()) {
            return response()->json(['ok'=>false,'errors'=>$v->errors()]);
        }
        return response()->json(['ok'=>true]);
    }

    public function submit(Request $req)
    {
        $userId      = (int) (session('auth_user.id') ?? auth()->id() ?? 0);
        $template_id = (int) $req->input('template_id');
        $values      = (array) $req->input('values', []);

        $tpl = $this->tools()->table('form_templates')->where('id',$template_id)->first();
        if (!$tpl) return response()->json(['ok'=>false,'msg'=>'Template not found'], 404);

        // تحقق قبل الحفظ
        $dataSchema = json_decode($tpl->data_schema, true);
        [$rules, $messages] = $this->buildRules($dataSchema, $values);
        $v = \Validator::make($values, $rules, $messages);
        if ($v->fails()) {
            return response()->json(['ok'=>false,'errors'=>$v->errors()], 422);
        }

        // حفظ الإرسالية
        $id = $this->tools()->table('form_submissions')->insertGetId([
            'form_template_id' => (int)$tpl->id,
            'template_version' => (int)$tpl->version,
            'user_id'          => $userId,
            'university_id'    => $values['university_id'] ?? null,
            'course_id'        => $values['course_id'] ?? null,
            'task_id'          => null,
            'values'           => json_encode($values, JSON_UNESCAPED_UNICODE),
            'status'           => 'submitted',
            'submitted_at'     => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // حفظ/تحديث بروفايل المستخدم إن اختار
        if (!empty($values['save_profile'])) {
            $this->tools()->table('user_profiles')->updateOrInsert(
                ['user_id'=>$userId],
                [
                    'full_name'        => $values['student_name'] ?? null,
                    'university_id'    => $values['university_id'] ?? null,
                    'student_id'       => $values['student_id'] ?? null,
                    'specialty'        => $values['specialty'] ?? null,
                    'course_teacher'   => $values['course_teacher'] ?? null,
                    'academic_advisor' => $values['academic_advisor'] ?? null,
                    'more'             => json_encode([], JSON_UNESCAPED_UNICODE),
                    'updated_at'       => now(),
                ]
            );
        }

        return response()->json(['ok'=>true,'submission_id'=>$id]);
    }

    public function prefill(Request $req)
    {
        $userId = (int) (session('auth_user.id') ?? auth()->id() ?? 0);
        $p = $this->tools()->table('user_profiles')->where('user_id',$userId)->first();

        if (!$p) return response()->json(['ok'=>true,'profile'=>null]);
        return response()->json([
            'ok'=>true,
            'profile'=>[
                'student_name'      => $p->full_name,
                'university_id'     => $p->university_id,
                'student_id'        => $p->student_id,
                'specialty'         => $p->specialty,
                'course_teacher'    => $p->course_teacher,
                'academic_advisor'  => $p->academic_advisor,
            ]
        ]);
    }

    /** يحوّل data_schema إلى قواعد Laravel + يدعم bypass للرقم الجامعي */
    private function buildRules(array $dataSchema, array $values): array
    {
        $rules = []; $messages = [];

        $fields = $dataSchema['fields'] ?? [];
        foreach ($fields as $f) {
            $name = $f['name'] ?? null;
            if (!$name) continue;

            $valRules = [];
            $v = $f['validation'] ?? [];

            // required مع bypass
            $required = !empty($v['required']);
            if ($required) {
                if (!empty($v['bypass_with']) && !empty($values[$v['bypass_with']])) {
                    // skip required
                } else {
                    $valRules[] = 'required';
                }
            }

            if (!empty($v['pattern']))     $valRules[] = 'regex:'.$v['pattern'];
            if (!empty($v['min']))         $valRules[] = 'min:'.(int)$v['min'];
            if (!empty($v['max']))         $valRules[] = 'max:'.(int)$v['max'];
            if (!empty($v['email']))       $valRules[] = 'email';
            if (!empty($v['date']))        $valRules[] = 'date';

            if ($valRules) $rules[$name] = $valRules;

            // رسائل
            $msgs = $v['messages'] ?? [];
            foreach ($msgs as $k=>$msg) {
                // أمثلة: required, pattern → regex
                if ($k === 'pattern') $k = 'regex';
                $messages[$name.'.'.$k] = $msg;
            }
        }

        return [$rules, $messages];
    }
}

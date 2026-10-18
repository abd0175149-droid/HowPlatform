<?php
declare(strict_types=1);

namespace App\Controllers\Admins;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;
use App\Models\AiJobTypeModel;
use App\Models\AiToolPlacementModel;
use App\Models\TaskTypeModel;
use Config\Database;

/**
 * AiToolsMeta — لوحة إدارة أدوات الذكاء الاصطناعي (الأدمن فقط، role=1).
 * تدير جدولَي ai_job_types (حقول آمنة) و ai_tool_placements (إسناد كامل).
 *
 * ملاحظة أمان: المجموعة محميّة بفلتر admin (أدوار 1+2)، ونضيف هنا بوابة role==1.
 * الحقول الحسّاسة (type_key, prompt_maker_class, ui_config_json, system_prompt)
 * غير قابلة للتعديل من هذه الواجهة (المرحلة الأولى).
 */
class AiToolsMeta extends Controller
{
    use ResponseTrait;

    /** الحقول الآمنة المسموح تعديلها على الأداة. */
    private const TOOL_EDITABLE = [
        'title', 'description', 'points_cost', 'is_enabled', 'is_admin_only',
        'provider', 'model_name', 'task_prompt_mode',
        'needs_task_context', 'needs_specialty',
    ];

    private const TASK_PROMPT_MODES = ['append', 'prepend', 'replace', 'ignore'];

    /** بوابة الأدمن (role=1) — دفاع في العمق فوق فلتر admin. */
    private function guard()
    {
        if ((int) (session('role') ?? 0) !== 1) {
            return $this->failForbidden('هذه الواجهة مخصّصة للأدمن فقط.');
        }
        return null;
    }

    // ==================================================================
    //  الأدوات (ai_job_types)
    // ==================================================================

    /** GET /admins/api/ai-tools/tools — قائمة الأدوات (حقول العرض). */
    public function tools()
    {
        if ($r = $this->guard()) return $r;

        $rows = (new AiJobTypeModel())
            ->select('id, type_key, title, description, provider, model_name, points_cost, is_enabled, is_admin_only, needs_task_context, needs_specialty, task_prompt_mode')
            ->orderBy('id', 'ASC')
            ->findAll();

        // تطبيع الأنواع للعرض
        $tools = array_map(static function (array $t): array {
            return [
                'id'                 => (int) $t['id'],
                'type_key'           => (string) $t['type_key'],
                'title'              => (string) ($t['title'] ?? ''),
                'description'        => (string) ($t['description'] ?? ''),
                'provider'           => (string) ($t['provider'] ?? ''),
                'model_name'         => (string) ($t['model_name'] ?? ''),
                'points_cost'        => $t['points_cost'] !== null ? (int) $t['points_cost'] : null,
                'is_enabled'         => (int) ($t['is_enabled'] ?? 0),
                'is_admin_only'      => (int) ($t['is_admin_only'] ?? 0),
                'needs_task_context' => (int) ($t['needs_task_context'] ?? 0),
                'needs_specialty'    => (int) ($t['needs_specialty'] ?? 0),
                'task_prompt_mode'   => (string) ($t['task_prompt_mode'] ?? 'ignore'),
            ];
        }, $rows);

        return $this->respond(['ok' => true, 'tools' => $tools]);
    }

    /**
     * GET /admins/api/ai-tools/tools/(:num) — التفاصيل الكاملة لأداة واحدة.
     * تُعيد كل ما يتعلّق بالأداة: الحقول الآمنة + التوجيه الأساسي (system_prompt) + الكلاسات
     * والقالب + تحليل ui_config (الأيقونات، الفورم، نوع الناتج) + جميع إسناداتها
     * مُثراةً بأسماء نوع المهمة والجامعة والمساق.
     */
    public function toolDetail(int $id)
    {
        if ($r = $this->guard()) return $r;

        $db  = Database::connect();
        $row = $db->table('ai_job_types')->where('id', $id)->get()->getRowArray();
        if (!$row) {
            return $this->failNotFound('الأداة غير موجودة');
        }

        // ── تحليل ui_config_json لاستخراج الأيقونة والفورم ونوع الناتج ──
        $ui = json_decode((string) ($row['ui_config_json'] ?? ''), true);
        $ui = is_array($ui) ? $ui : [];
        $panel  = $ui['panel']  ?? [];
        $form   = $ui['form']   ?? [];
        $result = $ui['result'] ?? [];

        // الأيقونة قد تكون في panel.button.icon أو panel.icon
        $panelIcon = (string) ($panel['button']['icon'] ?? $panel['icon'] ?? '');

        // ملخّص أقسام الفورم وحقوله (للعرض فقط)
        $formSections = [];
        foreach (($form['sections'] ?? []) as $sec) {
            if (!is_array($sec)) continue;
            $fields = [];
            foreach (($sec['fields'] ?? []) as $f) {
                if (!is_array($f)) continue;
                $fields[] = [
                    'key'      => (string) ($f['key'] ?? ''),
                    'label'    => (string) ($f['label'] ?? ''),
                    'type'     => (string) ($f['type'] ?? 'text'),
                    'required' => !empty($f['required']),
                ];
            }
            $formSections[] = [
                'title'  => (string) ($sec['title'] ?? ''),
                'layout' => (string) ($sec['layout'] ?? ''),
                'fields' => $fields,
            ];
        }

        $tool = [
            'id'                 => (int) $row['id'],
            'type_key'           => (string) $row['type_key'],
            'title'              => (string) ($row['title'] ?? ''),
            'description'        => (string) ($row['description'] ?? ''),
            'provider'           => (string) ($row['provider'] ?? ''),
            'model_name'         => (string) ($row['model_name'] ?? ''),
            'points_cost'        => $row['points_cost'] !== null ? (int) $row['points_cost'] : null,
            'is_enabled'         => (int) ($row['is_enabled'] ?? 0),
            'is_admin_only'      => (int) ($row['is_admin_only'] ?? 0),
            'needs_task_context' => (int) ($row['needs_task_context'] ?? 0),
            'needs_specialty'    => (int) ($row['needs_specialty'] ?? 0),
            'task_prompt_mode'   => (string) ($row['task_prompt_mode'] ?? 'ignore'),
            // حقول تقنية للعرض فقط (غير قابلة للتعديل من هنا)
            'prompt_maker_class' => (string) ($row['prompt_maker_class'] ?? ''),
            'template_path'      => (string) ($row['template_path'] ?? ''),
            'js_module_path'     => (string) ($row['js_module_path'] ?? ''),
            'system_prompt'      => (string) ($row['system_prompt'] ?? ''),
            'output_schema'      => (string) ($row['output_schema'] ?? ''),
            'created_at'         => (string) ($row['created_at'] ?? ''),
            'updated_at'         => (string) ($row['updated_at'] ?? ''),
            // تحليل ui_config
            'panel_icon'         => $panelIcon,
            'panel_title'        => (string) ($panel['title'] ?? ''),
            'panel_description'  => (string) ($panel['description'] ?? ''),
            'form_modal_title'   => (string) ($form['modal_title'] ?? ''),
            'form_submit_label'  => (string) ($form['submit_label'] ?? ''),
            'form_sections'      => $formSections,
            'result_type'        => (string) ($result['type'] ?? ''),
            'has_ui_config'      => $ui !== [],
        ];

        // ── إسنادات هذه الأداة مُثراة بالأسماء ──
        $tool['placements'] = $this->fetchPlacements($row['type_key']);

        return $this->respond(['ok' => true, 'tool' => $tool]);
    }

    /** POST /admins/api/ai-tools/tools/(:num) — تحديث الحقول الآمنة لأداة. */
    public function updateTool(int $id)
    {
        if ($r = $this->guard()) return $r;

        $model = new AiJobTypeModel();
        $tool  = $model->find($id);
        if (!$tool) {
            return $this->failNotFound('الأداة غير موجودة');
        }

        $in = $this->request->getJSON(true) ?? [];
        if (!is_array($in)) {
            return $this->failValidationErrors('صيغة البيانات غير صحيحة');
        }

        $data = [];
        foreach (self::TOOL_EDITABLE as $key) {
            if (!array_key_exists($key, $in)) {
                continue;
            }
            $val = $in[$key];
            switch ($key) {
                case 'points_cost':
                    $data[$key] = ($val === '' || $val === null) ? null : max(0, (int) $val);
                    break;
                case 'is_enabled':
                case 'is_admin_only':
                case 'needs_task_context':
                case 'needs_specialty':
                    $data[$key] = ($val === true || $val === 1 || $val === '1') ? 1 : 0;
                    break;
                case 'task_prompt_mode':
                    $mode = (string) $val;
                    $data[$key] = in_array($mode, self::TASK_PROMPT_MODES, true) ? $mode : 'ignore';
                    break;
                case 'provider':
                    $p = strtolower(trim((string) $val));
                    $data[$key] = in_array($p, ['gemini', 'openai', 'vertex'], true) ? $p : (string) $tool['provider'];
                    break;
                default: // title, description, model_name
                    $data[$key] = trim((string) $val);
            }
        }

        if (empty($data)) {
            return $this->failValidationErrors('لا توجد حقول صالحة للتحديث');
        }

        // العنوان إلزامي إن أُرسل
        if (array_key_exists('title', $data) && $data['title'] === '') {
            return $this->failValidationErrors('العنوان مطلوب');
        }

        $model->update($id, $data);

        // مزامنة المهام: إن تغيّر علم "خاص بالأدمن"، حدّث كل المهام التي تستخدم هذه الأداة
        // (tasks.ai_tool_version = type_key) فورًا — كي تُخفى/تظهر للطلاب دون انتظار إعادة حفظ المهمة.
        if (array_key_exists('is_admin_only', $data)) {
            Database::connect()->table('tasks')
                ->where('ai_tool_version', (string) $tool['type_key'])
                ->update(['is_admin_only' => (int) $data['is_admin_only']]);
        }

        return $this->respond(['ok' => true, 'message' => 'تم تحديث الأداة بنجاح']);
    }

    // ==================================================================
    //  الإسناد (ai_tool_placements)
    // ==================================================================

    /** GET /admins/api/ai-tools/task-types — أنواع المهام لقائمة الإسناد. */
    public function taskTypes()
    {
        if ($r = $this->guard()) return $r;

        $rows = (new TaskTypeModel())->select('id, name')->orderBy('name', 'ASC')->findAll();
        $types = array_map(static fn($t) => ['id' => (int) $t['id'], 'name' => (string) $t['name']], $rows);

        return $this->respond(['ok' => true, 'task_types' => $types]);
    }

    /** GET /admins/api/ai-tools/placements — قائمة الإسناد مع أسماء النوع والأداة والجامعة والمساق. */
    public function placements()
    {
        if ($r = $this->guard()) return $r;

        return $this->respond(['ok' => true, 'placements' => $this->fetchPlacements(null)]);
    }

    /**
     * جلب الإسنادات مُثراةً بأسماء نوع المهمة والأداة والجامعة والمساق.
     * @param string|null $typeKey فلترة على أداة محددة، أو null لكل الإسنادات.
     * @return array<int,array<string,mixed>>
     */
    private function fetchPlacements(?string $typeKey): array
    {
        $db = Database::connect();
        $builder = $db->table('ai_tool_placements p')
            ->select('p.id, p.type_key, p.task_type_id, p.university_id, p.course_id, p.min_subscription_level, p.button_label, p.button_icon, p.button_order, p.is_enabled, tt.name AS task_type_name, j.title AS tool_title, u.name AS university_name, c.name AS course_name')
            ->join('task_types tt', 'tt.id = p.task_type_id', 'left')
            ->join('ai_job_types j', 'j.type_key = p.type_key', 'left')
            ->join('universities u', 'u.id = p.university_id', 'left')
            ->join('courses c', 'c.id = p.course_id', 'left');

        if ($typeKey !== null) {
            $builder->where('p.type_key', $typeKey);
        }

        $rows = $builder->orderBy('p.button_order', 'ASC')
            ->orderBy('p.id', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(static function (array $p): array {
            return [
                'id'                     => (int) $p['id'],
                'type_key'               => (string) $p['type_key'],
                'tool_title'             => (string) ($p['tool_title'] ?? ''),
                'task_type_id'           => (int) ($p['task_type_id'] ?? 0),
                'task_type_name'         => (string) ($p['task_type_name'] ?? ''),
                'university_id'          => (int) ($p['university_id'] ?? 0),
                'university_name'        => (string) ($p['university_name'] ?? ''),
                'course_id'              => (int) ($p['course_id'] ?? 0),
                'course_name'            => (string) ($p['course_name'] ?? ''),
                'min_subscription_level' => (int) ($p['min_subscription_level'] ?? 0),
                'button_label'           => (string) ($p['button_label'] ?? ''),
                'button_icon'            => (string) ($p['button_icon'] ?? ''),
                'button_order'           => (int) ($p['button_order'] ?? 0),
                'is_enabled'             => (int) ($p['is_enabled'] ?? 0),
            ];
        }, $rows);
    }

    /** POST /admins/api/ai-tools/placements — إنشاء إسناد. */
    public function createPlacement()
    {
        if ($r = $this->guard()) return $r;

        [$data, $err] = $this->collectPlacement();
        if ($err !== null) {
            return $this->failValidationErrors($err);
        }

        $id = (new AiToolPlacementModel())->insert($data, true);

        return $this->respondCreated(['ok' => true, 'id' => (int) $id, 'message' => 'تم إنشاء الإسناد بنجاح']);
    }

    /** POST /admins/api/ai-tools/placements/(:num) — تحديث إسناد. */
    public function updatePlacement(int $id)
    {
        if ($r = $this->guard()) return $r;

        $model = new AiToolPlacementModel();
        if (!$model->find($id)) {
            return $this->failNotFound('الإسناد غير موجود');
        }

        [$data, $err] = $this->collectPlacement();
        if ($err !== null) {
            return $this->failValidationErrors($err);
        }

        $model->update($id, $data);

        return $this->respond(['ok' => true, 'message' => 'تم تحديث الإسناد بنجاح']);
    }

    /** DELETE /admins/api/ai-tools/placements/(:num) — حذف إسناد. */
    public function deletePlacement(int $id)
    {
        if ($r = $this->guard()) return $r;

        $model = new AiToolPlacementModel();
        if (!$model->find($id)) {
            return $this->failNotFound('الإسناد غير موجود');
        }

        $model->delete($id, true);

        return $this->respondDeleted(['ok' => true, 'message' => 'تم حذف الإسناد']);
    }

    /**
     * يجمّع ويتحقق من حمولة الإسناد من جسم الطلب.
     * @return array{0:array<string,mixed>,1:?string} [data, error]
     */
    private function collectPlacement(): array
    {
        $in = $this->request->getJSON(true) ?? [];
        if (!is_array($in)) {
            return [[], 'صيغة البيانات غير صحيحة'];
        }

        $typeKey = trim((string) ($in['type_key'] ?? ''));
        if ($typeKey === '') {
            return [[], 'يجب اختيار الأداة'];
        }

        // التأكد أن الأداة موجودة فعلاً
        $exists = (new AiJobTypeModel())->where('type_key', $typeKey)->countAllResults() > 0;
        if (!$exists) {
            return [[], 'الأداة المختارة غير موجودة'];
        }

        $data = [
            'type_key'               => $typeKey,
            'task_type_id'           => (int) ($in['task_type_id'] ?? 0),
            'university_id'          => (int) ($in['university_id'] ?? 0),
            'course_id'              => (int) ($in['course_id'] ?? 0),
            'min_subscription_level' => max(0, (int) ($in['min_subscription_level'] ?? 0)),
            'button_label'           => trim((string) ($in['button_label'] ?? '')) ?: null,
            'button_icon'            => trim((string) ($in['button_icon'] ?? '')) ?: null,
            'button_order'           => (int) ($in['button_order'] ?? 0),
            'is_enabled'             => (($in['is_enabled'] ?? 1) === true || (string) ($in['is_enabled'] ?? '1') === '1') ? 1 : 0,
        ];

        return [$data, null];
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers\Survey;

use App\Controllers\BaseController;
use App\Models\UniversityModel;
use App\Models\SpecialtyModel;
use App\Models\DiplomaTypeModel;
use App\Models\Survey\AlumniResponseModel;
use App\Models\Survey\Options\ChallengeOptionModel;
use App\Models\Survey\Options\SupportChannelOptionModel;
use App\Models\Survey\Options\FeatureOptionModel;
use App\Models\Survey\Options\ContentTypeOptionModel;
use App\Models\Survey\Options\TimingOptionModel;
use App\Models\Survey\Options\CurrentStatusOptionModel;
use App\Models\Survey\Options\PriceRangeOptionModel;
use App\Models\Survey\Pivot\ResponseChallengesModel;
use App\Models\Survey\Pivot\ResponseSupportChannelsModel;
use App\Models\Survey\Pivot\ResponseFeaturesModel;
use App\Models\Survey\Pivot\ResponseContentTypesModel;
use CodeIgniter\HTTP\ResponseInterface;

class Alumni extends BaseController
{
    /** @var array<string, string> */
    protected array $rules = [
        // موافقة شرط أساسي
        'consent' => 'required|in_list[1]',
        // قيَم 0..10 و 1..5
        'usage_probability'   => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[10]',
        'recommend_likelihood'=> 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[10]',
        'support_effectiveness'=> 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[5]',
        'stress_peak_level'   => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[5]',
        // بريد اختياري لكن إن وُجد يجب أن يكون صحيح
        'contact_email' => 'permit_empty|valid_email',
    ];

    public function index()
    {
        // تحميل الخيارات من الجداول الداعمة
        $data = [
            'universities' => (new UniversityModel())->orderBy('name','ASC')->findAll(),
            'specialties'  => (new SpecialtyModel())->orderBy('name','ASC')->findAll(),
            'diplomas'     => (new DiplomaTypeModel())->orderBy('name','ASC')->findAll(),

            'statusOptions'  => (new CurrentStatusOptionModel())->findAll(),
            'timingOptions'  => (new TimingOptionModel())->findAll(),
            'priceOptions'   => (new PriceRangeOptionModel())->findAll(),
            'challengeOptions' => (new ChallengeOptionModel())->findAll(),
            'supportOptions'   => (new SupportChannelOptionModel())->findAll(),
            'featureOptions'   => (new FeatureOptionModel())->findAll(),
            'contentOptions'   => (new ContentTypeOptionModel())->findAll(),

            // تهيئة قيم أولية (قد نملأها من جلسة المستخدم إن لزم)
            'prefill' => [
                'name'  => '',
                'email' => '',
                'phone' => '',
            ],
        ];

        return view('survey/alumni_form', $data);
    }

    public function submit()
    {
        $isAjax = $this->request->isAJAX();

        // إيقاف فوري إن لم يوافق
        if ($this->request->getPost('consent') !== '1') {
            $msg = 'لا يمكن متابعة الاستبيان بدون الموافقة.';
            if ($isAjax) {
                return $this->response->setJSON(['ok' => false, 'message' => $msg])->setStatusCode(ResponseInterface::HTTP_BAD_REQUEST);
            }
            return redirect()->to(site_url('survey/alumni'))->with('error', $msg);
        }

        if (! $this->validate($this->rules)) {
            if ($isAjax) {
                return $this->response->setJSON(['ok' => false, 'errors' => $this->validator->getErrors()])->setStatusCode(422);
            }
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $respModel = new AlumniResponseModel();

        // تجميع البيانات الرئيسية
        $payload = [
            // موافقة
            'consent' => 1,

            // بيانات عامة
            'completion_year' => (int) $this->request->getPost('completion_year'),
            'university_id'   => $this->request->getPost('university_id') ?: null,
            'diploma_type_id' => $this->request->getPost('diploma_type_id') ?: null,
            'specialty_id'    => $this->request->getPost('specialty_id') ?: null,
            'city'            => trim((string) $this->request->getPost('city')),
            'current_status_id'=> $this->request->getPost('current_status_id') ?: null,

            // الدراسة (1..5)
            'exp_plan_clarity'     => $this->request->getPost('exp_plan_clarity'),
            'exp_workload_balance' => $this->request->getPost('exp_workload_balance'),
            'exp_resources_avail'  => $this->request->getPost('exp_resources_avail'),

            // التدريب
            'field_req_clarity'       => $this->request->getPost('field_req_clarity'),
            'field_guidance_effective'=> $this->request->getPost('field_guidance_effective'),
            'field_life_balance'      => $this->request->getPost('field_life_balance'),

            // البيداغوجيا
            'pedu_lesson_planning' => $this->request->getPost('pedu_lesson_planning'),
            'pedu_strategy_choice' => $this->request->getPost('pedu_strategy_choice'),
            'pedu_feedback_timely' => $this->request->getPost('pedu_feedback_timely'),

            // المهام/التقييم
            'eval_reflective_clear' => $this->request->getPost('eval_reflective_clear'),
            'eval_analytic_clear'   => $this->request->getPost('eval_analytic_clear'),
            'inquiry_method_clear'  => $this->request->getPost('inquiry_method_clear'),
            'assess_design_easy'    => $this->request->getPost('assess_design_easy'),
            'rubric_clarity'        => $this->request->getPost('rubric_clarity'),

            // التحديات
            'stress_peak_level' => $this->request->getPost('stress_peak_level'),
            'hardest_three'     => trim((string) $this->request->getPost('hardest_three')),
            'best_resources'    => trim((string) $this->request->getPost('best_resources')),

            // الدعم
            'support_effectiveness' => $this->request->getPost('support_effectiveness'),
            'missing_support'       => trim((string) $this->request->getPost('missing_support')),

            // المنصة
            'timing_option_id'  => $this->request->getPost('timing_option_id') ?: null,
            'usage_probability' => $this->request->getPost('usage_probability'),
            'price_range_id'    => $this->request->getPost('price_range_id') ?: null,

            // الأثر و NPS
            'perf_improved_pck'                => (int) (bool) $this->request->getPost('perf_improved_pck'),
            'perf_improved_plan'               => (int) (bool) $this->request->getPost('perf_improved_plan'),
            'perf_improved_class_mgmt'         => (int) (bool) $this->request->getPost('perf_improved_class_mgmt'),
            'perf_improved_assessment'         => (int) (bool) $this->request->getPost('perf_improved_assessment'),
            'perf_improved_technology'         => (int) (bool) $this->request->getPost('perf_improved_technology'),
            'perf_improved_research'           => (int) (bool) $this->request->getPost('perf_improved_research'),
            'perf_improved_prof_communication' => (int) (bool) $this->request->getPost('perf_improved_prof_communication'),
            'perf_improved_other'              => (int) (bool) $this->request->getPost('perf_improved_other'),
            'recommend_likelihood'             => $this->request->getPost('recommend_likelihood'),

            // قصص
            'story'                 => trim((string) $this->request->getPost('story')),
            'allow_anonymous_quote' => (int) (bool) $this->request->getPost('allow_anonymous_quote'),

            // تواصل اختياري
            'contact_name'  => trim((string) $this->request->getPost('contact_name')),
            'contact_email' => trim((string) $this->request->getPost('contact_email')),
            'contact_phone' => trim((string) $this->request->getPost('contact_phone')),

            // تتبع
            'ip_address' => $this->request->getIPAddress(),
            'user_agent' => substr((string) $this->request->getUserAgent(), 0, 250),
        ];

        // ربط user_id إن كان مسجلاً (تعديل وفق نظام المصادقة لديك)
        $userId = session('user_id');
        if ($userId) {
            $payload['user_id'] = (int) $userId;
        }

        // honey-pot بسيط لمنع السبام
        if (! empty($this->request->getPost('website'))) { // حقل مخفي بالواجهة
            if ($isAjax) {
                return $this->response->setJSON(['ok' => true, 'redirect' => site_url('survey/alumni/thanks')]);
            }
            return redirect()->to(site_url('survey/alumni/thanks'));
        }

        // حفظ الإجابة الأساسية
        $responseId = $respModel->insert($payload, true);

        // جداول الربط مع ضبط الحدود (features<=5, contents<=3)
        $challenges = array_map('intval', (array) $this->request->getPost('challenges'));
        $supports   = array_map('intval', (array) $this->request->getPost('supports'));
        $features   = array_slice(array_map('intval', (array) $this->request->getPost('features')), 0, 5);
        $contents   = array_slice(array_map('intval', (array) $this->request->getPost('contents')), 0, 3);

        (new ResponseChallengesModel())->bulkAttach($responseId, $challenges);
        (new ResponseSupportChannelsModel())->bulkAttach($responseId, $supports);
        (new ResponseFeaturesModel())->bulkAttach($responseId, $features);
        (new ResponseContentTypesModel())->bulkAttach($responseId, $contents);

        if ($isAjax) {
            return $this->response->setJSON(['ok' => true, 'redirect' => site_url('survey/alumni/thanks')]);
        }

        return redirect()->to(site_url('survey/alumni/thanks'));
    }

    public function thanks()
    {
        return view('survey/thanks');
    }
}

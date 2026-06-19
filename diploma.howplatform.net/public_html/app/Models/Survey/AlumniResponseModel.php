<?php

declare(strict_types=1);

namespace App\Models\Survey;

use CodeIgniter\Model;

class AlumniResponseModel extends Model
{
    protected $table            = 'survey_alumni_responses';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = [
        // الحقول المسموح بها (نفس payload في الكنترولر)
        'user_id','consent','completion_year','university_id','diploma_type_id','specialty_id',
        'city','current_status_id',
        'exp_plan_clarity','exp_workload_balance','exp_resources_avail',
        'field_req_clarity','field_guidance_effective','field_life_balance',
        'pedu_lesson_planning','pedu_strategy_choice','pedu_feedback_timely',
        'eval_reflective_clear','eval_analytic_clear','inquiry_method_clear','assess_design_easy','rubric_clarity',
        'stress_peak_level','hardest_three','best_resources',
        'support_effectiveness','missing_support',
        'timing_option_id','usage_probability','price_range_id',
        'perf_improved_pck','perf_improved_plan','perf_improved_class_mgmt','perf_improved_assessment','perf_improved_technology','perf_improved_research','perf_improved_prof_communication','perf_improved_other',
        'recommend_likelihood',
        'story','allow_anonymous_quote',
        'contact_name','contact_email','contact_phone',
        'ip_address','user_agent','created_at',
    ];

    protected $useTimestamps = false;
}

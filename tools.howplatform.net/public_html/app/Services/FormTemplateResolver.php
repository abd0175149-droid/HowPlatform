<?php 
namespace App\Services;
use App\Models\TemplateAssignment;
use App\Models\FormTemplate;

class FormTemplateResolver {
  public function resolve(array $ctx): ?FormTemplate {
    $rows = TemplateAssignment::with('template')->get()->map(function($r){
      $r->specificity = collect(['university_id','course_id','task_type_id','academic_year_id','semester_id','week_id'])
        ->filter(fn($c)=>!is_null($r->$c))->count();
      return $r;
    })->filter(function($r) use($ctx){
      foreach (['university_id','course_id','task_type_id','academic_year_id','semester_id','week_id'] as $c){
        if (!is_null($r->$c) && (empty($ctx[$c]) || (string)$ctx[$c] !== (string)$r->$c)) return false;
      }
      return true; // كل المحدّدات طابقت
    })->sortByDesc(fn($r)=>[$r->match_priority, $r->specificity, $r->id])->values();

    return optional($rows->first())->template;
  }
}

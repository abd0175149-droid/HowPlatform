<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class WeeklySummary extends Model {
  protected $fillable = ['student_id','week_id','completed_tasks','late_tasks','average_grade'];
  public function student(){ return $this->belongsTo(Student::class); }
  public function week(){ return $this->belongsTo(Week::class); }
}

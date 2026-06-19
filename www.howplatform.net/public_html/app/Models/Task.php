<?php 
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Task extends Model {
  protected $fillable = ['week_id','title','task_url'];
  public function week(){ return $this->belongsTo(Week::class); }
  public function progresses(){ return $this->hasMany(StudentTaskProgress::class); }
}

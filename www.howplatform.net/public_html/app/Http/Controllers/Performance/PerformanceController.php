<?php 
namespace App\Http\Controllers\Performance;
use App\Http\Controllers\Controller;
use App\Models\Week;

class PerformanceController extends Controller {
public function index()
{
    $weeks = \App\Models\Week::select('id', 'title', 'week_number')->orderBy('week_number')->get();

    // الإحصاءات
    $weeksCount    = \App\Models\Week::count();
    $tasksCount    = \App\Models\Task::count();
    $studentsCount = \App\Models\Student::count();

    return view('performance.index', compact('weeks', 'weeksCount', 'tasksCount', 'studentsCount'));
}

  public function trackingView()
{
    $weeks   = \App\Models\Week::orderBy('week_number')->get(['id','week_number','title']);
    $groups  = \App\Models\Group::orderBy('name')->get(['id','name']);
    return view('performance.tracking', compact('weeks','groups'));
}
public function reportsView()
{
    return view('performance.reports');
}
public function groupsView()
{
    return view('performance.groups');
}

}

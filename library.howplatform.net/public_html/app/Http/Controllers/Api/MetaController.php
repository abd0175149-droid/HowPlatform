<?php
// /app/Http/Controllers/Api/MetaController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class MetaController extends Controller
{
    /** إرجاع الصفوف مرتبة */
    public function grades()
    {
        $rows = DB::table('grades')
            ->orderBy('sort_order')->orderBy('level')
            ->get(['id', 'name_ar', 'level']);

        return response()->json(['ok' => true, 'data' => $rows]);
    }

    /** المواد المرتبطة بصف محدّد عبر pivot */
    public function subjectsByGrade(int $grade)
    {
        $rows = DB::table('grade_subject AS gs')
            ->join('subjects AS s', 's.id', '=', 'gs.subject_id')
            ->where('gs.grade_id', $grade)
            ->orderBy('s.sort_order')->orderBy('s.name_ar')
            ->get(['s.id', 's.name_ar', 's.code']);

        return response()->json(['ok' => true, 'data' => $rows]);
    }

    /** الفصول */
    public function semesters()
    {
        $rows = DB::table('semesters')
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'name_ar']);

        return response()->json(['ok' => true, 'data' => $rows]);
    }

    /** التصنيفات */
    public function categories()
    {
        $rows = DB::table('categories')
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'name_ar', 'slug']);

        return response()->json(['ok' => true, 'data' => $rows]);
    }
}

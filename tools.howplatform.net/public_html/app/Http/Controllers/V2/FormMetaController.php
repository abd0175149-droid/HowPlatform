<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class FormMetaController extends Controller
{
    public function meta()
    {
        // اتصال الدبلوم فقط
        $diploma = DB::connection('diploma');

        $universities = $diploma->table('universities')
            ->select('id','name')->orderBy('name')->get();

        // في حال عدم وجود “مهمة قبلية/بعدية” أنشأتها أنت سابقًا – هنا فقط نقرأ
        $taskTypes = $diploma->table('task_types')
            ->select('id','name')->whereIn('name',['مهمة قبلية','مهمة بعدية'])
            ->orderBy('name')->get();

        // إن كان لديك جدول تخصصات في الدبلوم
        $specialties = $diploma->table('specialties')
            ->select('id','name')->orderBy('name')->get();

        // لو رغبت بمساقات مرتبطة بالجامعة – اتركها الآن عامّة (اختياري)
        // $courses = $diploma->table('courses')->select('id','name','university_id')->get();

        return response()->json([
            'universities' => $universities,
            'task_types'   => $taskTypes,
            'specialties'  => $specialties,
            // 'courses'   => $courses,
        ]);
    }
}

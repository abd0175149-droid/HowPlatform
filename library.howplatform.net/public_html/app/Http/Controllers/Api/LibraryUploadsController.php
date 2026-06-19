<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class LibraryUploadsController extends Controller
{
    /**
     * GET /uploads
     * ترجع قائمة الملفات مع فلاتر اختيارية: grade_id, subject_id, semester_id, category_id
     */
    public function index(Request $request): JsonResponse
    {
        // 🔎 قراءة الفلاتر (ندعم صيغ بديلة احتياطًا)
        $gradeId    = $request->query('grade_id',    $request->query('grade'));
        $subjectId  = $request->query('subject_id',  $request->query('subject'));
        $semesterId = $request->query('semester_id', $request->query('semester'));
        $categoryId = $request->query('category_id', $request->query('category'));

        // 🧹 تطبيع الفصل إلى رقم (يدعم العربية)
        $semesterId = $this->normalizeSemesterId($semesterId);

        // حد أقصى افتراضي
        $limit = max(1, (int) $request->query('limit', 200));

        // الاستعلام: join بين التصنيفات والملفات + أسماء الصف/المادة/الفصل
        $q = DB::table('file_classifications as fc')
        ->join('files as f', 'f.id', '=', 'fc.file_id')
        ->leftJoin('grades as g', 'g.id', '=', 'fc.grade_id')
        ->leftJoin('subjects as s', 's.id', '=', 'fc.subject_id')
        ->leftJoin('semesters as sem', 'sem.id', '=', 'fc.semester_id')
        ->leftJoin('categories as c', 'c.id', '=', 'fc.category_id') // ✅ إضافة join على التصنيفات
        ->select([
            'f.id',
            'fc.title',
            'f.original_name',
            'f.mime_type',
            'f.size_bytes',
            
            'fc.grade_id',
            'g.name_ar as grade_name',
    
            'fc.subject_id',
            's.name_ar as subject_name',
    
            'fc.semester_id',
            'sem.name_ar as semester_label',
    
            'fc.category_id',
            'c.name_ar as category_name', // ✅ اسم التصنيف
    
            'f.storage_disk as disk',
            'f.storage_path as path',
            'f.preview_storage_disk as preview_disk',
            'f.preview_storage_path as preview_path',
            'f.created_at as uploaded_at',
        ])
        ->orderByDesc('f.id');
    


        if ($gradeId    !== null && $gradeId    !== '') { $q->where('fc.grade_id',    (int) $gradeId); }
        if ($subjectId  !== null && $subjectId  !== '') { $q->where('fc.subject_id',  (int) $subjectId); }
        if ($semesterId !== null && $semesterId !== '') { $q->where('fc.semester_id', (int) $semesterId); }
        if ($categoryId !== null && $categoryId !== '') { $q->where('fc.category_id', (int) $categoryId); }

        $rows = $q->limit($limit)->get();

        $data = $rows->map(function ($r) {
            // ✅ نحول التاريخ إلى ISO 8601 ليسهل تنسيقه في FE
            $iso = null;
            if (!empty($r->uploaded_at)) {
                try {
                    $iso = Carbon::parse($r->uploaded_at)->toIso8601String();
                } catch (\Throwable $e) {
                    $iso = (string) $r->uploaded_at; // لو كان فورمات غير متوقع
                }
            }

            return [
                'id'              => (int) $r->id,
                'title'           => $r->title,
                'original_name'   => $r->original_name,
                'mime_type'       => $r->mime_type,
                'size'            => isset($r->size_bytes) ? (int) $r->size_bytes : null,

                'grade_id'        => isset($r->grade_id) ? (int) $r->grade_id : null,
                'grade_name'      => $r->grade_name,
                'subject_id'      => isset($r->subject_id) ? (int) $r->subject_id : null,
                'subject_name'    => $r->subject_name,

                'semester_id'     => isset($r->semester_id) ? (int) $r->semester_id : null,
                'semester_label'  => $r->semester_label,

                'category_id'     => isset($r->category_id) ? (int) $r->category_id : null,
                'category_name'   => $r->category_name,
                'disk'            => $r->disk,
                'path'            => $r->path,
                'preview_disk'    => $r->preview_disk ?: $r->disk,
                'preview_path'    => $r->preview_path,

                'uploaded_at'     => $r->uploaded_at,   // النص الأصلي من DB (UTC غالبًا)
                'uploaded_at_iso' => $iso,              // ✅ ISO 8601 للواجهة
            ];
        });

        return response()->json(['ok' => true, 'data' => $data], 200);
    }
    

    /**
     * DELETE /uploads/{id}
     * حذف ملف + إزالة ملفاته من التخزين، وسيحذف Laravel الصفوف المرتبطة في file_classifications تلقائيًا (FK CASCADE).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        // نجلب سجل الملف أولًا
        $file = DB::table('files')->where('id', $id)->first();
        if (!$file) {
            return response()->json(['ok' => false, 'error' => 'لم يتم العثور على الملف'], 404);
        }

        // حذف من التخزين إن وُجدت المسارات
        try {
            $disk = $file->storage_disk ?: 'public';
            if (!empty($file->storage_path) && Storage::disk($disk)->exists($file->storage_path)) {
                Storage::disk($disk)->delete($file->storage_path);
            }

            $pDisk = $file->preview_storage_disk ?: $disk;
            if (!empty($file->preview_storage_path) && Storage::disk($pDisk)->exists($file->preview_storage_path)) {
                Storage::disk($pDisk)->delete($file->preview_storage_path);
            }
        } catch (Throwable $e) {
            // لا نُفشل الطلب: نسجّل فقط
            logger()->warning('uploads.destroy: storage delete failed', [
                'id' => $id,
                'message' => $e->getMessage(),
            ]);
        }

        // حذف سجل الملف (وسيقوم FK بحذف التصنيفات المرتبطة تلقائيًا)
        DB::table('files')->where('id', $id)->delete();

        return response()->json(['ok' => true], 200);
    }

    /** تطبيع قيمة الفصل إلى 1/2 إن كانت عربية/نصية */
    private function normalizeSemesterId($v): ?int
    {
        if ($v === null || $v === '') { return null; }
        $s = mb_strtolower((string) $v, 'UTF-8');
        if (ctype_digit($s)) {
            $n = (int) $s;
            return in_array($n, [1, 2], true) ? $n : null;
        }
        $map = [
            'الفصل الاول'  => 1,
            'الفصل الأول'  => 1,
            'first'        => 1,
            'الفصل الثاني' => 2,
            'second'       => 2,
        ];
        return $map[$s] ?? null;
    }
}

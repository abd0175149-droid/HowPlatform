<?php
// /app/Http/Controllers/Api/UploadController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class UploadController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'file' => 'required|file|max:204800|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar',
            'preview'      => 'nullable|file|image|max:5120|mimes:jpg,jpeg,png,webp',
            'grade_id'     => 'required|integer|exists:grades,id',
            'subject_id'   => 'required|integer|exists:subjects,id',
            'category_id'  => 'required|integer|exists:categories,id',
            'semester_id'  => 'nullable|integer|exists:semesters,id',
            'title'        => 'nullable|string|max:255',
            'year_label'   => 'nullable|string|max:20',
            'disk'         => 'nullable|string|in:public,s3,local',
            'preview_disk' => 'nullable|string|in:public,s3,local',
            'force_new'    => 'nullable',
        ], [
            'file.max'     => 'حجم الملف يتجاوز الحد المسموح (حتى 200MB).'
        ]);

        $semesterId = $data['semester_id'] ?? null;
        $forceNew   = filter_var($data['force_new'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // تأكيد ارتباط الصف بالمادة
        $hasPivot = DB::table('grade_subject')
            ->where('grade_id', $data['grade_id'])
            ->where('subject_id', $data['subject_id'])
            ->exists();

        if (!$hasPivot) {
            return response()->json(['ok'=>false,'error'=>'هذه المادة غير مرتبطة بهذا الصف (grade_subject).'], 422);
        }

        $uploaded = $data['file'];
        $originalName = $uploaded->getClientOriginalName();
        $ext  = strtolower($uploaded->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION));
        $mime = $uploaded->getClientMimeType() ?: $uploaded->getMimeType();
        $size = $uploaded->getSize();
        $md5  = @md5_file($uploaded->getRealPath()) ?: null;

        $grade    = DB::table('grades')->select('id','level','name_ar')->where('id', $data['grade_id'])->first();
        $subject  = DB::table('subjects')->select('id','code','name_ar')->where('id', $data['subject_id'])->first();
        $category = DB::table('categories')->select('id','slug','name_ar')->where('id', $data['category_id'])->first();

        if (!$grade || !$subject || !$category) {
            return response()->json(['ok'=>false,'error'=>'Grade/Subject/Category غير موجودة'], 422);
        }

        // أقراص التخزين
        $disk        = $data['disk'] ?? 'public';
        $previewDisk = $data['preview_disk'] ?? $disk;

        $year  = date('Y');
        $month = date('m');

        $subjectSeg  = $subject->code ? Str::slug($subject->code) : 'subject-'.$subject->id;
        $dir         = "library/grade-{$grade->level}/{$subjectSeg}/{$category->slug}/{$year}/{$month}";
        $storedName  = Str::uuid().'.'.$ext;
        $storagePath = $dir.'/'.$storedName;

        // تجهيز المعاينة إن وُجدت
        $preview      = $request->file('preview');
        $previewName  = null;
        $previewPath  = null;
        $previewDir   = $dir.'/previews';

        if ($preview) {
            $pExt        = strtolower($preview->getClientOriginalExtension() ?: 'jpg');
            $previewName = 'pv-'.Str::uuid().'.'.$pExt;
            $previewPath = $previewDir.'/'.$previewName;
        }

        // دالة مساعدة للحصول على رابط عام مع fallback
        $publicUrlFor = function (?string $d, ?string $p) {
            if (!$d || !$p) return null;
            try {
                return Storage::disk($d)->url($p);
            } catch (Throwable $e) {
                $prefix = rtrim((string) env('FILES_PUBLIC_PREFIX', '/storage'), '/');
                return $prefix.'/'.ltrim($p, '/');
            }
        };

        try {
            return DB::transaction(function() use (
                $uploaded, $originalName, $mime, $size, $md5,
                $disk, $storagePath, $dir, $storedName, $data, $semesterId, $forceNew,
                $grade, $subject, $category, $preview, $previewDisk, $previewPath, $previewDir, $previewName, $publicUrlFor
            ) {

                // منع التكرار بالـ MD5 (إلا إذا force_new=true)
                $fileRow = null;
                if ($md5 && ! $forceNew) {
                    $fileRow = DB::table('files')->where('checksum_md5', $md5)->first();
                }

                if (!$fileRow) {
                    // رفع الملف
                    Storage::disk($disk)->putFileAs($dir, $uploaded, $storedName);

                    // رفع المعاينة إن وُجدت
                    if ($preview) {
                        Storage::disk($previewDisk)->putFileAs($previewDir, $preview, $previewName);
                    }

                    $fileId = DB::table('files')->insertGetId([
                        'original_name'        => $originalName,
                        'stored_name'          => $storedName,
                        'storage_disk'         => $disk,
                        'storage_path'         => $storagePath,
                        'preview_storage_disk' => $preview ? $previewDisk : null,
                        'preview_storage_path' => $preview ? $previewPath : null,
                        'mime_type'            => $mime,
                        'size_bytes'           => $size,
                        'checksum_md5'         => $md5,
                        'uploaded_by'          => auth()->id(),
                        'created_at'           => now(),
                        'updated_at'           => now(),
                    ]);

                    $fileRow = DB::table('files')->where('id', $fileId)->first();
                } else {
                    // ملف مكرر: استخدم الموجود
                    // إذا أُرسلت معاينة والملف لا يملك معاينة مسبقة -> ارفعها ثم حدّث الصف
                    if ($preview && (empty($fileRow->preview_storage_path) || empty($fileRow->preview_storage_disk))) {
                        Storage::disk($previewDisk)->putFileAs($previewDir, $preview, $previewName);

                        DB::table('files')
                            ->where('id', $fileRow->id)
                            ->update([
                                'preview_storage_disk' => $previewDisk,
                                'preview_storage_path' => $previewPath,
                                'updated_at'           => now(),
                            ]);

                        $fileRow = DB::table('files')->where('id', $fileRow->id)->first();
                    }
                }

                $title = $data['title'] ?? pathinfo($originalName, PATHINFO_FILENAME);

                $classificationExists = DB::table('file_classifications')
                    ->where('file_id',    $fileRow->id)
                    ->where('grade_id',   $data['grade_id'])
                    ->where('subject_id', $data['subject_id'])
                    ->where('category_id',$data['category_id'])
                    ->when($semesterId !== null && $semesterId !== '', fn($q) => $q->where('semester_id', $semesterId))
                    ->exists();

                if ($classificationExists) {
                    $fcId = DB::table('file_classifications')
                        ->where('file_id', $fileRow->id)
                        ->where('grade_id', $data['grade_id'])
                        ->where('subject_id', $data['subject_id'])
                        ->where('category_id',$data['category_id'])
                        ->when($semesterId !== null && $semesterId !== '', fn($q) => $q->where('semester_id', $semesterId))
                        ->value('id');
                } else {
                    $fcId = DB::table('file_classifications')->insertGetId([
                        'file_id'     => $fileRow->id,
                        'grade_id'    => $data['grade_id'],
                        'subject_id'  => $data['subject_id'],
                        'semester_id' => ($semesterId !== null && $semesterId !== '') ? (int) $semesterId : null,
                        'category_id' => $data['category_id'],
                        'year_label'  => $data['year_label'] ?? null,
                        'title'       => $title,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }

                $publicUrl     = $publicUrlFor($fileRow->storage_disk,  $fileRow->storage_path);
                $previewPubUrl = $publicUrlFor($fileRow->preview_storage_disk, $fileRow->preview_storage_path);

                $downloadUrl = null;
                try {
                    $downloadUrl = route('library.download', ['file' => $fileRow->id]);
                } catch (Throwable $e) { /* اتركه null */ }

                return response()->json([
                    'ok'   => true,
                    'file' => [
                        'id'                 => $fileRow->id,
                        'original_name'      => $fileRow->original_name,
                        'mime'               => $fileRow->mime_type,
                        'size_bytes'         => (int) $fileRow->size_bytes,
                        'size_mb'            => round($fileRow->size_bytes / 1048576, 1),
                        'checksum_md5'       => $fileRow->checksum_md5,
                        'disk'               => $fileRow->storage_disk,
                        'path'               => $fileRow->storage_path,
                        'public_url'         => $publicUrl,
                        'download_url'       => $downloadUrl,
                        'preview' => [
                            'disk'       => $fileRow->preview_storage_disk,
                            'path'       => $fileRow->preview_storage_path,
                            'public_url' => $previewPubUrl,
                        ],
                    ],
                    'classification' => [
                        'id'           => $fcId,
                        'grade_id'     => (int) $data['grade_id'],
                        'subject_id'   => (int) $data['subject_id'],
                        'category_id'  => (int) $data['category_id'],
                        'semester_id'  => ($semesterId !== null && $semesterId !== '') ? (int) $semesterId : null,
                        'title'        => $title,
                        'year_label'   => $data['year_label'] ?? null,
                        'grade_label'  => $grade->name_ar,
                        'subject_name' => $subject->name_ar,
                        'category'     => $category->slug,
                    ],
                    'deduplicated' => (bool) ($md5 && !empty($fileRow->checksum_md5) && $fileRow->checksum_md5 === $md5),
                ], 201);
            });
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'ok' => false,
                'error' => 'Upload failed',
                'message' => app()->hasDebugModeEnabled() ? $e->getMessage() : null,
            ], 500);
        }
    }
}


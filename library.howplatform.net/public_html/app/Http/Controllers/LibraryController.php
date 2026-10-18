<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\View as ViewFactory;

class LibraryController extends Controller
{
    /**
     * صفحة فهرس المكتبة: عرض الصفوف (Grades).
     */
    public function index(): View
    {
        $grades = DB::table('grades')
            ->select('id', 'name_ar as name', 'level')
            ->orderBy('level')
            ->get()
            ->map(function ($g) {
                return [
                    'name' => $g->name,
                    'slug' => 'grade-' . $g->level, // slug = grade-1, grade-2...
                ];
            });

        $seo = [
            'title'       => 'المكتبة الرقمية | كيف التعليمية',
            'description' => 'مكتبة رقمية منظَّمة حسب الصفوف الدراسية تتضمن الكتب، أدلة المعلم، التحضير، أوراق العمل وغير ذلك.',
            'canonical'   => url()->current(),
        ];

        return view('library.index', compact('grades', 'seo'));
    }

    /**
     * صفحة صفّ محدد:
     * - الطلب العادي يرجع Blade كاملة.
     * - الطلب مع ajax=1 أو X-Requested-With=XMLHttpRequest يرجع JSON (cards + pagination).
     */
    public function grade(Request $request, string $gradeSlug)
{
    // 1) جلب بيانات الصفّ
    $grade = DB::table('grades')
        ->whereRaw('CONCAT("grade-", level) = ?', [$gradeSlug])
        ->first() ?? abort(404);

    // 2) بيانات ثابتة للواجهة (للتحميل الأولي)
    $categories = DB::table('categories')
        ->select('id', 'name_ar', 'slug')
        ->orderBy('sort_order')
        ->get();

    $subjects = DB::table('grade_subject as gs')
        ->join('subjects as s', 's.id', '=', 'gs.subject_id')
        ->where('gs.grade_id', $grade->id)
        ->select('s.id', 's.name_ar')
        ->orderBy('s.sort_order')
        ->get();
    $semesters = DB::table('semesters')
        ->select('id', 'name_ar')
        ->orderBy('id')
        ->get();

    // 3) قراءة الفلاتر
    $q    = trim((string) $request->get('q', ''));
    $sort = $request->get('sort', 'recent');   // recent|az|za
    $sid  = $request->integer('subject_id');   // subject id
    $cat  = $request->get('category');         // category slug
    $sem = $request->filled('semester_id') ? (int) $request->input('semester_id') : null;

    // 4) بناء الاستعلام + الترتيب
    $query = $this->buildResourcesQuery(
        gradeId: $grade->id,
        q:       $q,
        sid:     $sid ?: null,
        cat:     $cat ?: null,
        sem:     $sem,
        sort:    $sort
    );

    // 5) ترقيم النتائج (8 عناصر) + إرفاق روابط العرض والمعاينة
    $resources = $query->paginate(8)->withQueryString();
    $this->attachPublicUrls($resources);

    // 6) إن كان الطلب AJAX نرجع JSON يحوي HTML جزئي
    if ($request->ajax() || $request->wantsJson() || $request->boolean('ajax')) {
        return $this->ajaxResourcesResponse($resources);
    }

    // 7) تحميل الصفحة كاملة (الطلب الأولي)
    $seo = [
        'title'       => 'المكتبة الرقمية | ' . $grade->name_ar,
        'description' => 'موارد الصف مرتبة حسب التصنيفات والمواد.',
        'canonical'   => url()->current(),
    ];

    return view('library.grade', compact('grade', 'categories', 'subjects','semesters', 'resources', 'seo'))
        ->with([
            'activeCategory' => $cat,
            'activeSubject'  => $sid,
            'activeSemester' => $sem,
            'activeSort'     => $sort,
            'searchTerm'     => $q,
        ]);
}


    /**
     * تنزيل ملف من التخزين مع Content-Disposition صحيح واسم احتياطي ASCII.
     */
    public function downloadOLD(Request $request, int $file)
    {
        $f = DB::table('files')->where('id', $file)->first();
        if (!$f) {
            abort(404);
        }

        $disk = $f->storage_disk ?: 'public';
        $path = ltrim($f->storage_path, '/'); // تأكد أن المسار نسبي للقرص

        if (!Storage::disk($disk)->exists($path)) {
            \Log::warning('Download missing', compact('disk', 'path'));
            abort(404, 'File not found on storage');
        }

        // اسم التنزيل + اسم ASCII احتياطي للأسماء غير اللاتينية
        $downloadName  = trim($f->original_name ?: basename($path));
        $downloadName  = str_replace(["\r", "\n"], '', $downloadName);
        $ext           = pathinfo($downloadName, PATHINFO_EXTENSION);
        $baseNoExt     = pathinfo($downloadName, PATHINFO_FILENAME);
        $fallbackAscii = Str::ascii($baseNoExt) ?: 'file';
        if ($ext) {
            $fallbackAscii .= '.' . $ext;
        }

        $headers = [
            'Content-Type' => $f->mime_type ?: 'application/octet-stream',
        ];

        /** @var \Illuminate\Filesystem\FilesystemAdapter $storageDisk */
        $storageDisk = Storage::disk($disk);
        $response = $storageDisk->download($path, $downloadName, $headers);

        // ✅ اضبط Content-Disposition عبر ResponseHeaderBag::makeDisposition
        $bag = $response->headers; // هو ResponseHeaderBag
        if (method_exists($bag, 'makeDisposition')) {
            $disposition = $bag->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $downloadName,
                $fallbackAscii
            );
            $bag->set('Content-Disposition', $disposition);
        } else {
            // احتياطي نادر
            $bag->set('Content-Disposition', 'attachment; filename="' . $fallbackAscii . '"');
        }

        // (اختياري) تحديد Content-Length إن أمكن
        try {
            $absolute = Storage::disk($disk)->path($path); // قد يرمي استثناء على S3
            if (is_file($absolute)) {
                $len = @filesize($absolute);
                if ($len !== false) {
                    $response->headers->set('Content-Length', (string) $len);
                }
            }
        } catch (\Throwable $e) {
            // الأقراص البعيدة لا تدعم path()؛ تجاهل
        }

        return $response;
    }
public function download(Request $request, int $file)
{
    // 1) جلب الملف
    $f = DB::table('files')->where('id', $file)->first();
    if (!$f) { abort(404); }

    $disk = $f->storage_disk ?: 'public';
    $path = ltrim($f->storage_path, '/');

    if (!Storage::disk($disk)->exists($path)) {
        \Log::warning('Download missing', compact('disk', 'path'));
        abort(404, 'File not found on storage');
    }

    // 1.5) ✅ بوابة الحصص (منصّة النقاط): التحميل يتطلب دخولًا + حصّة يومية
    //      (خطة: 10/25/50 حسب المستوى — بلا خطة: حصّة مجانية من الإعدادات).
    //      المعاينة تبقى مجانية للجميع. mode=off → السلوك القديم حرفيًا.
    $plans = new \App\Services\PlansClient();
    if ($plans->enabled()) {
        $userId = (int) (session('auth_user.id') ?? 0);

        if ($userId <= 0) {
            // ضيف → تسجيل الدخول ثم العودة لإكمال التحميل تلقائيًا
            return redirect()->away(
                'https://auth.howplatform.net/login?redirect=' . urlencode(route('library.download', $f->id))
            );
        }

        $freeLimit = (int) config('plans.free_daily_downloads', 3);
        $consume   = $plans->usageConsume($userId, 'downloads_per_day', 1, $freeLimit);

        // تعذّر الاتصال بـ Plans → اسمح (fail-open) وسجّل
        if (is_array($consume) && ($consume['allowed'] ?? true) === false) {
            $limit = $consume['limit'] ?? $freeLimit;
            return response()->view('library.quota', [
                'limit'   => $limit,
                'used'    => $consume['used'] ?? $limit,
                'back'    => url()->previous() ?: url('/'),
            ], 429);
        }
        if ($consume === null) {
            \Log::warning('Library quota check unavailable (fail-open)', ['user_id' => $userId, 'file_id' => $f->id]);
        }
    }

    // 2) أسماء التنزيل (مع بديل ASCII)
    $downloadName  = trim($f->original_name ?: basename($path));
    $downloadName  = str_replace(["\r", "\n"], '', $downloadName);
    $ext           = pathinfo($downloadName, PATHINFO_EXTENSION);
    $baseNoExt     = pathinfo($downloadName, PATHINFO_FILENAME);
    $fallbackAscii = Str::ascii($baseNoExt) ?: 'file';
    if ($ext) { $fallbackAscii .= '.' . $ext; }

    $headers = [
        'Content-Type' => $f->mime_type ?: 'application/octet-stream',
    ];

    // 3) ✅ سجّل التحميل (قبل إرجاع الاستجابة)
    try {
        DB::table('downloads')->insert([
            'file_id'    => $f->id,
            'user_id'    => session('auth_user.id'), // ✅ من السيشن بدل users table
            'ip'         => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 500),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    } catch (\Throwable $e) {
        // لا توقف التنزيل لو فشل التسجيل
        \Log::warning('Download log failed', ['file_id' => $f->id, 'err' => $e->getMessage()]);
    }

    // 4) ردّ التنزيل عبر Storage
    /** @var \Illuminate\Filesystem\FilesystemAdapter $storageDisk */
    $storageDisk = Storage::disk($disk);
    $response = $storageDisk->download($path, $downloadName, $headers);

    // 5) ضبط Content-Disposition مع اسم بديل
    $bag = $response->headers;
    if (method_exists($bag, 'makeDisposition')) {
        $disposition = $bag->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $downloadName,
            $fallbackAscii
        );
        $bag->set('Content-Disposition', $disposition);
    } else {
        $bag->set('Content-Disposition', 'attachment; filename="'.$fallbackAscii.'"');
    }

    // 6) (اختياري) Content-Length إن أمكن
    try {
        $absolute = Storage::disk($disk)->path($path); // قد لا يعمل على أقراص بعيدة
        if (is_file($absolute)) {
            $len = @filesize($absolute);
            if ($len !== false) {
                $response->headers->set('Content-Length', (string) $len);
            }
        }
    } catch (\Throwable $e) { /* تجاهل */ }

    return $response;
}
    /**
     * (اختياري) Endpoint منفصل للـ AJAX لمن يرغب بالفصل عن صفحة العرض.
     * اربطه بـ Route: library.grade.ajax
     */
    public function gradeAjax(Request $request, string $gradeSlug): JsonResponse
    {
        $grade = DB::table('grades')
            ->whereRaw('CONCAT("grade-", level) = ?', [$gradeSlug])
            ->first() ?? abort(404);

        $q    = trim((string) $request->get('q', ''));
        $sort = $request->get('sort', 'recent');
        $sid  = $request->integer('subject_id');
        $cat  = $request->get('category');
        $sem = $request->filled('semester_id') ? (int) $request->input('semester_id') : null;

        $resources = $this->buildResourcesQuery(
            gradeId: $grade->id,
            q:       $q,
            sid:     $sid ?: null,
            cat:     $cat ?: null,
            sem:     $sem,
            sort:    $sort
        )
            ->paginate(8)
            ->withQueryString();

        return $this->ajaxResourcesResponse($resources);
    }

    /* =====================[ Helpers ]===================== */

    /**
     * يبني استعلام الموارد حسب الفلاتر والترتيب.
     * يتضمن:
     * - updated_on: تاريخ فقط (Y-m-d)
     * - mime_type : لتحديد أيقونة نوع الملف
     */
public function preview(Request $request, int $file)
{
    $f = DB::table('files')->where('id', $file)->first();
    if (!$f) {
        abort(404);
    }

    // حدّد قرص ومسار المعاينة؛ إن لم تتوفر المعاينة فارجع 404 (أو بدّلها بـ placeholder إن رغبت)
    $disk = $f->preview_storage_disk ?: null;
    $path = $f->preview_storage_path ?: null;

    if (!$disk || !$path) {
        abort(404, 'Preview not available for this file');
        // أو:
        // return response()->file(public_path('assets/images/preview-placeholder.png'));
    }

    // لو القرص S3: أعد توجيه مؤقت لرابط موقّت (10 دقائق)
    if ($disk === 's3') {
        try {
            /** @var \Illuminate\Filesystem\FilesystemAdapter $s3Disk */
            $s3Disk = Storage::disk('s3');
            $tmpUrl = $s3Disk->temporaryUrl($path, now()->addMinutes(10));
            return redirect()->away($tmpUrl, 302);
        } catch (\Throwable $e) {
            abort(404, 'Preview not accessible on S3');
        }
    }

    // أقراص محلية: تأكد من وجود الملف ثم اعرضه inline مع ترويسات كاش مناسبة
    if (!Storage::disk($disk)->exists($path)) {
        abort(404, 'Preview file missing on storage');
    }

    // MIME للمعاينة نفسها (أدق من عمود mime_type للملف الأصلي)
    $mime = null;
    try {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $storageDisk */
        $storageDisk = Storage::disk($disk);
        $mime = $storageDisk->mimeType($path);
    } catch (\Throwable $e) {
        $mime = 'image/*';
    }

    $filename = basename($path);
    $fallback = Str::ascii(pathinfo($filename, PATHINFO_FILENAME)) ?: 'preview';
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    if ($ext) $fallback .= '.'.$ext;

    // بناء الاستجابة عبر Storage::response (inline) + كاش
    $headers = [
        'Content-Type'        => $mime ?: 'image/*',
        'Cache-Control'       => 'public, max-age=604800', // أسبوع
        'Content-Disposition' => 'inline; filename="'.$fallback.'"',
    ];

    // Last-Modified / ETag (تحسين الكاش)
    if (!empty($f->updated_at)) {
        try {
            $lm = \Illuminate\Support\Carbon::parse($f->updated_at)->toRfc7231String();
            $headers['Last-Modified'] = $lm;
            $headers['ETag'] = md5('pv|'.$file.'|'.$f->updated_at);
        } catch (\Throwable $e) { /* تجاهل */ }
    }

    /** @var \Illuminate\Filesystem\FilesystemAdapter $storageDisk */
    $storageDisk = Storage::disk($disk);
    return $storageDisk->response($path, $filename, $headers);
}

protected function buildResourcesQuery(
    int $gradeId,
    string $q = '',
    ?int $sid = null,
    ?string $cat = null,
    ?int $sem = null, 
    string $sort = 'recent'
) {
    $query = DB::table('file_classifications as fc')
        ->join('files as f', 'f.id', '=', 'fc.file_id')
        ->join('subjects as s', 's.id', '=', 'fc.subject_id')
        ->join('categories as c', 'c.id', '=', 'fc.category_id')
        ->leftJoin('semesters as sem', 'sem.id', '=', 'fc.semester_id') // ✅ جديد
        ->where('fc.grade_id', $gradeId)
        ->select([
            'f.id as file_id',
            'fc.subject_id',
            's.name_ar as subject_name',
            'c.id as category_id',
            'c.slug as category_slug',
            'c.name_ar as category_name',
            'fc.title',
            DB::raw('ROUND(f.size_bytes/1048576,1) as size_mb'),
            'f.updated_at',
            DB::raw('DATE(f.updated_at) as updated_on'),
            'f.mime_type as mime_type',
            'f.storage_disk',
            'f.storage_path',
            'f.original_name',
            'f.preview_storage_disk',
            'f.preview_storage_path',
            'fc.semester_id',
            'sem.name_ar as semester_name', // ✅ جديد
        ]);

    if ($sid)        $query->where('fc.subject_id', $sid);
    if (!empty($cat))$query->where('c.slug', $cat);
    if ($q !== '')   $query->where('fc.title', 'LIKE', '%'.$q.'%');

    // استخدم !is_null بدلاً من if ($sem) حتى لا تتجاهل القيمة 0 (احتياطيًا)
    if (!is_null($sem)) {
        $query->where('fc.semester_id', $sem); // ✅ فلترة الفصل
    }

    if ($sort === 'az')      $query->orderBy('fc.title','asc');
    elseif ($sort === 'za')  $query->orderBy('fc.title','desc');
    else                     $query->orderBy('f.updated_at','desc');

    return $query;
}



    /**
     * يبني استجابة JSON تحوي HTML جزئي لبطاقات الموارد وترقيمها.
     * يحاول استخدام الجزئيات، وإن فشلت/غابت يستخدم fallback داخلي.
     */
protected function ajaxResourcesResponseOLD($resources): JsonResponse
{
    try {
        // ✅ أرفق روابط الملف/المعاينة قبل التوليد
        $this->attachPublicUrls($resources);

        $gridHtml = '';
        $paginationHtml = '';

        if (ViewFactory::exists('library.partials.resources-cards')) {
            $gridHtml = view('library.partials.resources-cards', [
                'resources' => $resources
            ])->render();
        } else {
            // Fallback HTML
            $gridHtml = $this->renderResourcesCardsHtml($resources);
        }

        if ($resources->hasPages()) {
            $paginationHtml = view('library.partials.pagination', [
                'resources' => $resources
            ])->render();
        } else {
            $paginationHtml = view('library.partials.pagination', [
                'resources' => $resources
            ])->render();
        }


        return response()->json([
            'grid'       => $gridHtml,
            'pagination' => $paginationHtml,
            'noResults'  => $resources->count() === 0,
        ]);
    } catch (\Throwable $e) {
        \Log::error('Library AJAX render failed', [
            'message' => $e->getMessage(),
            'trace'   => $e->getTraceAsString(),
        ]);

        $msg = config('app.debug')
            ? ('Render failed: ' . $e->getMessage())
            : 'Render failed';
        return response()->json(['error' => $msg], 500);
    }
}
protected function ajaxResourcesResponse($resources): JsonResponse
{
    try {
        $this->attachPublicUrls($resources);

        // HTML الشبكة
        $gridHtml = ViewFactory::exists('library.partials.resources-cards')
            ? view('library.partials.resources-cards', ['resources' => $resources])->render()
            : $this->renderResourcesCardsHtml($resources);

        // HTML الترقيم (الغلاف + nav)
        $paginationHtml = view('library.partials.pagination', [
            'resources' => $resources->withQueryString()
        ])->render();

        // ميتاداتا الترقيم لإعادة البناء من الجهة الأمامية
        $payload = $resources->toArray(); // Laravel يحوّل LengthAwarePaginator إلى مصفوفة
        $links   = collect($payload['links'] ?? [])->map(function ($l) {
            return [
                'url'    => $l['url'],
                'label'  => trim(strip_tags((string) $l['label'])),
                'active' => (bool) ($l['active'] ?? false),
            ];
        })->values();

        $pmeta = [
            'current_page'   => $resources->currentPage(),
            'last_page'      => $resources->lastPage(),
            'per_page'       => $resources->perPage(),
            'total'          => $resources->total(),
            'next_page_url'  => $resources->nextPageUrl(),
            'prev_page_url'  => $resources->previousPageUrl(),
            'path'           => $resources->path(),                 // أساس بناء الروابط
            'query'          => request()->except('page'),          // فلاتر الحالية
            'links'          => $links,                             // لو تحب تبني من labels الجاهزة
        ];

        return response()->json([
            'grid'       => $gridHtml,
            'pagination' => $paginationHtml, // للاستخدام السريع بالاستبدال المباشر
            'pmeta'      => $pmeta,          // لإعادة بناء الترقيم برمجياً
            'noResults'  => $resources->isEmpty(),
        ]);
    } catch (\Throwable $e) {
        \Log::error('Library AJAX render failed', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        $msg = config('app.debug') ? ('Render failed: ' . $e->getMessage()) : 'Render failed';
        return response()->json(['error' => $msg], 500);
    }
}

    /**
     * Fallback: توليد HTML للبطاقات مع عرض التاريخ فقط + أيقونة نوع الملف بجانب العنوان.
     */
protected function renderResourcesCardsHtml($resources): string
{
    $html = '';

    // إعلان AdSense كأول عنصر في الشبكة
    $html .= '<article class="card resource-card ad-card" style="display:flex; align-items:center; justify-content:center; padding: 1rem; min-height: 250px;">';
    $html .= '  <!-- Library Feed Ad -->';
    $html .= '  <ins class="adsbygoogle"';
    $html .= '       style="display:block; width:100%; height:100%;"';
    $html .= '       data-ad-client="ca-pub-3572625518315562"';
    $html .= '       data-ad-slot="5596897178"';
    $html .= '       data-ad-format="auto"';
    $html .= '       data-full-width-responsive="true"></ins>';
    $html .= '</article>';

    foreach ($resources as $r) {
        $fileId       = (int) ($r->file_id ?? 0);
        $downloadUrl  = route('library.download', $fileId);

        $subjectName  = e($r->subject_name ?? '');
        $title        = e($r->title ?? '');
        $categoryName = e($r->category_name ?? '');
        $sizeMb       = e($r->size_mb ?? '');
        $semesterName = e($r->semester_name ?? '');

        // التاريخ (تاريخ فقط + datetime للـ time)
        $dateText     = '';
        $datetimeAttr = '';
        if (!empty($r->updated_on)) {
            $dateText = e($r->updated_on); // Y-m-d
            try {
                $datetimeAttr = e(\Illuminate\Support\Carbon::parse($r->updated_on)->toAtomString());
            } catch (\Throwable $e) {
                $datetimeAttr = e($r->updated_on);
            }
        } elseif (!empty($r->updated_at)) {
            try {
                $c = $r->updated_at instanceof \DateTimeInterface
                    ? \Illuminate\Support\Carbon::instance($r->updated_at)
                    : \Illuminate\Support\Carbon::parse($r->updated_at);
                $dateText     = e($c->toDateString());
                $datetimeAttr = e($c->toAtomString());
            } catch (\Throwable $e) {
                $raw          = (string) $r->updated_at;
                $dateText     = e(preg_replace('/\s.*/', '', $raw) ?: $raw);
                $datetimeAttr = e($raw);
            }
        }

        // أيقونة النوع + امتداد
        $nameOrPath = (string) ($r->original_name ?? $r->storage_path ?? '');
        $mime       = (string) ($r->mime_type ?? '');
        $iconClass  = $this->fileIconClass($mime, $nameOrPath);
        $extUpper   = e(strtoupper(pathinfo($nameOrPath, PATHINFO_EXTENSION) ?: (explode('/', $mime)[1] ?? 'FILE')));

        // ✅ رابط المعاينة:
        // نُفضّل الراوت (preview) لو هناك preview_storage_path،
        // وإلا إن وُجد preview_public_url نستخدمه.
        $previewUrl = null;
        if (!empty($r->preview_storage_path)) {
            $previewUrl = route('library.preview', $fileId);
        } elseif (!empty($r->preview_public_url)) {
            $previewUrl = $r->preview_public_url;
        }

        // ====== بناء البطاقة بالتخطيط الجديد ======
        $isImage = $iconClass === 'fa-file-image';
        
        $html .= '<article class="clean-card" data-file-id="' . $fileId . '">';
        
        if (!empty($previewUrl)) {
            $html .= '<div class="clean-card-thumb">';
            $html .=   '<img src="' . e($previewUrl) . '" alt="' . $title . '" loading="lazy" decoding="async">';
            $html .=   '<div class="thumb-overlay"><i class="fa-solid fa-eye"></i> معاينة</div>';
            $html .= '</div>';
        }

        $html .= '<div class="clean-card-body">';
        
        $html .=   '<div class="clean-card-header">';
        $html .=     '<span class="clean-tag subject-tag">' . $subjectName . '</span>';
        $html .=     '<div class="clean-file-icon ' . $extUpper . '">';
        $html .=       '<i class="fa-solid ' . $iconClass . '" aria-hidden="true" title="' . $extUpper . '"></i>';
        $html .=     '</div>';
        $html .=   '</div>';

        $html .=   '<h3 class="clean-card-title">';
        
        $linkClass = 'stretched-link';
        if (!empty($previewUrl)) {
            $linkClass .= ' lightbox-trigger';
        }
        $target = (!empty($previewUrl)) ? ' data-preview="true"' : '';
        
        $html .=     '<a href="' . ($previewUrl ?? $downloadUrl) . '" class="' . $linkClass . '"' . $target . '>';
        $html .=       $title;
        $html .=     '</a>';
        $html .=   '</h3>';

        $html .=   '<div class="clean-card-meta">';
        $html .=     '<div class="meta-item"><i class="fa-regular fa-folder"></i> ' . $categoryName . '</div>';
        if (!empty($semesterName)) {
            $html .= '<div class="meta-item"><i class="fa-regular fa-calendar"></i> ' . $semesterName . '</div>';
        }
        $html .=     '<div class="meta-item"><i class="fa-solid fa-weight-scale"></i> ' . $sizeMb . ' MB</div>';
        $html .=   '</div>';
        
        $html .= '</div>';

        $html .= '<div class="clean-card-footer">';
        if ($dateText !== '') {
            $html .= '<time class="clean-time" datetime="' . $datetimeAttr . '">' . $dateText . '</time>';
        }
        $html .=   '<div class="download-action">';
        $html .=     '<a class="clean-download-btn" href="' . $downloadUrl . '" title="تحميل الملف" aria-label="تحميل">';
        $html .=       '<span>تحميل</span> <i class="fa-solid fa-download" aria-hidden="true"></i>';
        $html .=     '</a>';
        $html .=   '</div>';
        $html .= '</div>';

        $html .= '</article>';
    }

    return $html;
}

    
/**
 * توليد رابط عام للقرص/المسار مع Fallback عند عدم دعم disk->url()
 */
protected function publicUrl(?string $disk, ?string $path): ?string
{
    if (!$disk || !$path) return null;
    try {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $storageDisk */
        $storageDisk = Storage::disk($disk);
        return $storageDisk->url($path);
    } catch (\Throwable $e) {
        $prefix = rtrim((string) env('FILES_PUBLIC_PREFIX', '/storage'), '/');
        return $prefix.'/'.ltrim($path, '/');
    }
}

/**
 * إرفاق file_public_url و preview_public_url لكل عنصر داخل المجمّع
 */
protected function attachPublicUrls($paginatorOrCollection): void
{
    // يدعم LengthAwarePaginator أو Collection عادية
    if (is_object($paginatorOrCollection) && method_exists($paginatorOrCollection, 'getCollection')) {
        $collection = $paginatorOrCollection->getCollection();
    } else {
        $collection = $paginatorOrCollection;
    }

    if (is_object($collection) && method_exists($collection, 'transform')) {
        $collection->transform(function ($r) {
            $r->file_public_url = $this->publicUrl($r->storage_disk ?? null, $r->storage_path ?? null);
            $r->preview_public_url = $this->publicUrl($r->preview_storage_disk ?? null, $r->preview_storage_path ?? null);
            return $r;
        });
    }
}

    /**
     * حدد كلاس Font Awesome المناسب حسب MIME/الامتداد.
     */
    protected function fileIconClass(?string $mime, string $nameOrPath): string
    {
        $ext = strtolower(pathinfo($nameOrPath ?? '', PATHINFO_EXTENSION));

        // تخمين بالإمتداد أولاً
        if (in_array($ext, ['pdf']))                          return 'fa-file-pdf';
        if (in_array($ext, ['doc', 'docx']))                  return 'fa-file-word';
        if (in_array($ext, ['xls', 'xlsx']))                  return 'fa-file-excel';
        if (in_array($ext, ['ppt', 'pptx']))                  return 'fa-file-powerpoint';
        if (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz']))return 'fa-file-zipper';
        if (in_array($ext, ['mp3', 'wav', 'm4a', 'ogg', 'flac'])) return 'fa-file-audio';
        if (in_array($ext, ['mp4', 'mkv', 'webm', 'mov', 'avi']))  return 'fa-file-video';
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'])) return 'fa-file-image';
        if (in_array($ext, ['csv']))                          return 'fa-file-csv';
        if (in_array($ext, ['txt', 'md', 'rtf']))             return 'fa-file-lines';
        if (in_array($ext, ['json', 'xml', 'html', 'htm', 'css', 'js', 'php'])) return 'fa-file-code';

        // ثمّ نحاول بالـ MIME إن وُجد
        if ($mime) {
            if (strpos($mime, 'image/') === 0) return 'fa-file-image';
            if (strpos($mime, 'audio/') === 0) return 'fa-file-audio';
            if (strpos($mime, 'video/') === 0) return 'fa-file-video';
            if ($mime === 'application/pdf')    return 'fa-file-pdf';
            if (strpos($mime, 'word') !== false
                || strpos($mime, 'msword') !== false
                || strpos($mime, 'officedocument.wordprocessingml') !== false) return 'fa-file-word';
            if (strpos($mime, 'excel') !== false
                || strpos($mime, 'spreadsheetml') !== false) return 'fa-file-excel';
            if (strpos($mime, 'powerpoint') !== false
                || strpos($mime, 'presentationml') !== false) return 'fa-file-powerpoint';
            if (strpos($mime, 'zip') !== false
                || strpos($mime, 'rar') !== false
                || strpos($mime, '7z') !== false
                || strpos($mime, 'compressed') !== false) return 'fa-file-zipper';
            if (strpos($mime, 'csv') !== false)  return 'fa-file-csv';
            if (strpos($mime, 'text') !== false) return 'fa-file-lines';
        }

        return 'fa-file'; // افتراضي
    }
}

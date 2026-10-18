<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\SubscriptionService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UserClassController extends Controller
{
    /** الحد الأقصى لعدد طلاب الصف الواحد */
    private const MAX_STUDENTS_PER_CLASS = 50;

    /* ===========================
     * Helpers (Auth & Constants)
     * =========================== */

    private function currentUserId(): ?int
    {
        $auth = session('auth_user');
        if (is_array($auth) && isset($auth['id'])) return (int) $auth['id'];
        if (is_object($auth) && isset($auth->id))   return (int) $auth->id;
        return null;
    }

    /** سنوات دراسية ثابتة لتوحيد النسق */
    private function academicYears(): array
    {
        return ['2026/2027','2025/2026'];
    }
    private function classAllowsMajor(?string $gradeLevel): bool
    {
        $gradeLevel = trim((string)$gradeLevel);
        return !in_array($gradeLevel, ['أساسي','ثانوي'], true);
    }
    public function studentsDestroyBulk(Request $request, int $id)
{
    $uid = $this->currentUserId();
    

    $this->ensureOwnedClass($uid, $id);

    $ids = $request->input('ids', []);
    if (!is_array($ids) || empty($ids)) {
        return back()->withErrors(['bulk' => 'لم يتم اختيار أي طلاب للحذف.']);
    }

    // نظّف المعرفات وأزل التكرارات
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (empty($ids)) {
        return back()->withErrors(['bulk' => 'قائمة المعرفات غير صالحة.']);
    }

    // احذف فقط السجلات التابعة لهذا الصف
    $deleted = DB::table('user_class_students')
        ->where('class_id', $id)
        ->whereIn('id', $ids)
        ->delete();

    return back()->with('ok', "تم حذف {$deleted} طالب/طلاب.");
}

    /* ===========================
     * خطة المستخدم وحدود الصفوف
     * =========================== */

    /**
     * استخراج عدد الصفوف المسموح من خصائص الخطة (features).
     * يبحث عن مفتاح Number_of_classes في أي عمق داخل limits_json.
     */
    private function extractIntLimitFromFeatures(array $features, string $wantedKey = 'Number_of_classes'): ?int
    {
        $normalize = function ($k) {
            $k = mb_strtolower((string)$k);
            $k = str_replace([' ', '-', 'ـ', '–', '—'], '_', $k);
            $k = preg_replace('/_+/', '_', $k);
            return trim($k, '_');
        };

        $needle = $normalize($wantedKey);

        $dig = function ($val) use (&$dig, $normalize, $needle) {
            if (is_numeric($val)) return (int)$val;
            if (!is_array($val)) return null;

            foreach ($val as $k => $v) {
                $nk = $normalize($k);
                if ($nk === $needle && (is_numeric($v) || (is_string($v) && is_numeric($v)))) {
                    return (int)$v;
                }
                if (is_array($v)) {
                    $deep = $dig($v);
                    if (is_int($deep)) return $deep;
                }
            }
            return null;
        };

        foreach ($features as $featKey => $limits) {
            $val = $dig($limits);
            if (is_int($val)) return $val;
        }

        // Fallback على مسميات شائعة
        $aliases = [
            'عدد_الصفوف_المسموح',
            'عدد_الصفوف',
            'classes_limit',
            'max_classes',
            'total_classes',
        ];

        foreach ($features as $featKey => $limits) {
            $nk = $normalize($featKey);
            if (in_array($nk, $aliases, true)) {
                if (is_numeric($limits)) return (int)$limits;
                if (is_array($limits)) {
                    foreach ($limits as $v) {
                        if (is_numeric($v)) return (int)$v;
                    }
                }
            }
        }

        return null;
    }

    /**
     * بدون خطة: (3 إجمالي / 1 فعّال)
     * مع خطة: Number_of_classes = إجمالي، ولا نقيّد الفعال.
     */
    private function computeClassCaps(?array $activePlan): array
    {
        $noPlanCaps = [
            'max_total'  => 3,
            'max_active' => 1,
            'source'     => 'no-plan',
        ];

        if (!$activePlan) return $noPlanCaps;

        $features = $activePlan['features'] ?? [];
        $num = $this->extractIntLimitFromFeatures($features, 'Number_of_classes');

        if (is_int($num) && $num > 0) {
            return [
                'max_total'  => $num,
                'max_active' => PHP_INT_MAX,
                'source'     => 'plan',
            ];
        }

        return $noPlanCaps;
    }

    /* ===========================
     * CRUD الصفوف
     * =========================== */

    public function index(Request $request, SubscriptionService $subs)
    {
        $uid = $this->currentUserId();
        

        $classes = DB::table('user_classes as c')
            ->leftJoin('user_class_students as s', 's.class_id', '=', 'c.id')
            ->select(
                'c.id','c.user_id','c.name','c.notes','c.grade_level','c.year',
                'c.is_active','c.created_at','c.updated_at',
                DB::raw('COUNT(s.id) as students_count')
            )
            ->where('c.user_id', $uid)
            ->groupBy(
                'c.id','c.user_id','c.name','c.notes','c.grade_level','c.year',
                'c.is_active','c.created_at','c.updated_at'
            )
            ->orderBy('c.created_at', 'desc')
            ->get();

        $academicYears = $this->academicYears();

        $activePlan = $subs->getActivePlanForUser($uid); // من قاعدة الدبلوم
        $caps       = $this->computeClassCaps($activePlan);

        return view('classes.index', [
            'classes'       => $classes,
            'academicYears' => $academicYears,
            'activePlan'    => $activePlan,
            'caps'          => $caps,
        ]);
    }

    public function store(Request $request, SubscriptionService $subs)
    {
        $uid = $this->currentUserId();
        

        $data = $request->validate([
            'name'        => ['required','string','max:255'],
            'grade_level' => ['nullable','string','max:50'],
            'year'        => ['nullable','string','max:20'],
            'notes'       => ['nullable','string'],
            'is_active'   => ['nullable'],
        ]);

        $activePlan  = $subs->getActivePlanForUser($uid);
        $caps        = $this->computeClassCaps($activePlan);
        $totalCount  = (int) DB::table('user_classes')->where('user_id', $uid)->count();
        $activeCount = (int) DB::table('user_classes')->where('user_id', $uid)->where('is_active', 1)->count();

        if ($totalCount >= $caps['max_total']) {
            return back()
                ->withErrors(['plan' => 'لقد وصلت إلى الحد الأقصى للصفوف المسموح بها. فضلاً قم بالترقية إلى خطة أعلى أو احذف صفوفًا قديمة.'])
                ->withInput();
        }

        $willBeActive = $request->boolean('is_active');

        if ($caps['source'] === 'no-plan' && $willBeActive && $activeCount >= $caps['max_active']) {
            return back()
                ->withErrors(['plan' => 'يمكن تفعيل صف واحد فقط بدون خطة. فضلاً قم بإلغاء تفعيل صف آخر أو الترقية إلى خطة أعلى.'])
                ->withInput();
        }

        DB::table('user_classes')->insert([
            'user_id'     => $uid,
            'name'        => $data['name'],
            'grade_level' => $data['grade_level'] ?? null,
            'year'        => $data['year'] ?? null,
            'notes'       => $data['notes'] ?? null,
            'is_active'   => $willBeActive ? 1 : 0,
        ]);

        return redirect()->route('user.classes.index')->with('ok','تمت إضافة الصف بنجاح.');
    }

    public function update(Request $request, int $id, SubscriptionService $subs)
    {
        $uid = $this->currentUserId();
        

        $data = $request->validate([
            'name'        => ['required','string','max:255'],
            'grade_level' => ['nullable','string','max:50'],
            'year'        => ['nullable','string','max:20'],
            'notes'       => ['nullable','string'],
            'is_active'   => ['nullable'],
        ]);

        $current = DB::table('user_classes')
            ->where('id', $id)
            ->where('user_id', $uid)
            ->first();

        if (!$current) {
            return redirect()->route('user.classes.index')
                ->withErrors(['plan' => 'الصف المطلوب غير موجود.']);
        }

        $activePlan = $subs->getActivePlanForUser($uid);
        $caps       = $this->computeClassCaps($activePlan);

        $willBeActive = $request->boolean('is_active');
        $wasActive    = (int)($current->is_active ?? 0) === 1;

        if ($caps['source'] === 'no-plan' && $willBeActive && !$wasActive) {
            $activeCount = (int) DB::table('user_classes')
                ->where('user_id', $uid)
                ->where('is_active', 1)
                ->count();

            if ($activeCount >= $caps['max_active']) {
                return back()
                    ->withErrors(['plan' => 'يمكن تفعيل صف واحد فقط بدون خطة. فضلاً قم بإلغاء تفعيل صف آخر أو الترقية.'])
                    ->withInput();
            }
        }

        $affected = DB::table('user_classes')
            ->where('id', $id)
            ->where('user_id', $uid)
            ->update([
                'name'        => $data['name'],
                'grade_level' => $data['grade_level'] ?? null,
                'year'        => $data['year'] ?? null,
                'notes'       => $data['notes'] ?? null,
                'is_active'   => $willBeActive ? 1 : 0,
            ]);

        return redirect()->route('user.classes.index')
            ->with('ok', $affected ? 'تم حفظ التعديلات.' : 'لم يتم تعديل أي بيانات.');
    }

    public function destroy(Request $request, int $id)
    {
        $uid = $this->currentUserId();
        

        DB::table('user_classes')
            ->where('id', $id)
            ->where('user_id', $uid)
            ->delete();

        return redirect()->route('user.classes.index')->with('ok', 'تم حذف الصف.');
    }

    /* ===========================
     * Helpers (Classes/Students)
     * =========================== */

    private function ensureOwnedClass(int $uid, int $classId)
    {
        $class = DB::table('user_classes')->where('id', $classId)->where('user_id', $uid)->first();
        abort_if(!$class, 404);
        return $class;
    }

    private function getStudents(int $classId)
    {
        return DB::table('user_class_students')
            ->where('class_id', $classId)
            ->orderBy('id')
            ->get();
    }

    /** عدد/حدود طلاب الصف */
    private function classStudentCounts(int $classId): array
    {
        $total = (int) DB::table('user_class_students')->where('class_id', $classId)->count();
        return [
            'total' => $total,
            'max'   => self::MAX_STUDENTS_PER_CLASS,
            'left'  => max(0, self::MAX_STUDENTS_PER_CLASS - $total),
        ];
    }

    /* ===========================
     * ملفات الاستيراد المؤقتة
     * =========================== */

    /** الامتدادات المسموح بها لاستيراد الطلاب */
    private const IMPORT_ALLOWED_EXT = ['csv', 'txt', 'xlsx', 'xls'];

    /** الحد الأقصى لحجم ملف الاستيراد (5 ميجابايت) */
    private const IMPORT_MAX_BYTES = 5_242_880;

    /** مجلد الملفات المؤقتة داخل قرص local (storage/app/private) */
    private const IMPORT_TMP_DIR = 'tmp/class-imports';

    /**
     * يخزّن ملفًا مرفوعًا في موقع مؤقت آمن ويُعيد توكِنًا (الاسم المخزّن) يحمل الامتداد.
     * يتحقق من الامتداد والحجم، وينظّف الملفات القديمة (أقدم من يوم).
     */
    private function storeTempFile(\Illuminate\Http\UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        abort_unless(
            in_array($ext, self::IMPORT_ALLOWED_EXT, true),
            422,
            'صيغة الملف غير مدعومة. المسموح: CSV أو TXT أو XLSX أو XLS.'
        );
        abort_if($file->getSize() > self::IMPORT_MAX_BYTES, 422, 'حجم الملف كبير جدًا (الحد الأقصى 5 ميجابايت).');

        $this->pruneTempFiles();

        $token = Str::random(40) . '.' . $ext;
        $file->storeAs(self::IMPORT_TMP_DIR, $token, 'local');

        return $token;
    }

    /**
     * يحوّل توكِن ملف مؤقت إلى [المسار الكامل، الامتداد].
     * يرفض أي توكِن لا يطابق النمط الآمن (يمنع path traversal).
     * يُعيد [null, null] إن لم يوجد الملف أو كان التوكِن غير صالح.
     */
    private function readTempFile(?string $token): array
    {
        $token = (string) $token;
        if (!preg_match('/^[A-Za-z0-9]{10,64}\.(csv|txt|xlsx|xls)$/i', $token)) {
            return [null, null];
        }

        $rel  = self::IMPORT_TMP_DIR . '/' . $token;
        $disk = Storage::disk('local');
        if (!$disk->exists($rel)) {
            return [null, null];
        }

        return [$disk->path($rel), strtolower(pathinfo($token, PATHINFO_EXTENSION))];
    }

    /** يحذف ملفات الاستيراد المؤقتة الأقدم من 24 ساعة لمنع تراكمها. */
    private function pruneTempFiles(): void
    {
        try {
            $disk    = Storage::disk('local');
            $cutoff  = now()->subDay()->timestamp;
            foreach ($disk->files(self::IMPORT_TMP_DIR) as $path) {
                if ($disk->lastModified($path) < $cutoff) {
                    $disk->delete($path);
                }
            }
        } catch (\Throwable $e) {
            // التنظيف اختياري — لا تُسقط الطلب إن فشل
        }
    }

    /* ===========================
     * إدارة طلاب الصف
     * =========================== */

public function studentsPage(int $id)
{
    $uid = $this->currentUserId();
    

    $class    = $this->ensureOwnedClass($uid, $id);
    $students = $this->getStudents($id);
    $counts   = $this->classStudentCounts($id);

    return view('classes.students', [
        'class'        => $class,
        'students'     => $students,
        'counts'       => $counts,
        'preview'      => [],
        'dropped'      => [],
        'fileToken'    => null,
        'headings'     => [],
        'pickedCol'    => null,
        'pickedMajor'  => null,
        'allowsMajor'  => $this->classAllowsMajor($class->grade_level), 
    ]);
}


    /** معاينة الأسماء قبل الحفظ (ملف على مرحلتين أو نص) */
public function studentsPreview(Request $request, int $id)
{
    $uid = $this->currentUserId();
    

    $class        = $this->ensureOwnedClass($uid, $id);
    $students     = $this->getStudents($id);
    $counts       = $this->classStudentCounts($id);
    $allowsMajor  = $this->classAllowsMajor($class->grade_level);

    // 1) رفع ملف جديد → خزّنه مؤقتًا واعرض اختيار الأعمدة
    if ($request->hasFile('names_file')) {
        $token = $this->storeTempFile($request->file('names_file'));
        [$full, $ext] = $this->readTempFile($token);
        $rows  = app(\App\Services\StudentImportService::class)->readFileRows($full, $ext);
        $heads = app(\App\Services\StudentImportService::class)->headingsFromFirstRow($rows);

        return view('classes.students', [
            'class'        => $class,
            'students'     => $students,
            'counts'       => $counts,
            'preview'      => [],
            'dropped'      => [],
            'fileToken'    => $token,
            'headings'     => $heads,
            'pickedCol'    => null,
            'pickedMajor'  => null,
            'allowsMajor'  => $allowsMajor,
        ]);
    }

    // 2) اختيار الأعمدة من الملف المؤقت
    if ($request->filled('file_token') && $request->filled('name_col')) {
        [$full, $ext] = $this->readTempFile($request->input('file_token'));
        if ($full) {
            $rows      = app(\App\Services\StudentImportService::class)->readFileRows($full, $ext);
            $nameCol   = (int) $request->input('name_col');
            $majorCol  = $allowsMajor && $request->filled('major_col') ? (int)$request->input('major_col') : null;

            $pairs = app(\App\Services\StudentImportService::class)->extractColumnsAsStudentPairs($rows, $nameCol, $majorCol);

            $left    = $counts['left'];
            $preview = array_slice($pairs, 0, $left);
            $dropped = array_slice($pairs, $left);

            return view('classes.students', [
                'class'        => $class,
                'students'     => $students,
                'counts'       => $counts,
                'preview'      => $preview,   
                'dropped'      => $dropped,
                'fileToken'    => null,
                'headings'     => [],
                'pickedCol'    => $nameCol,
                'pickedMajor'  => $majorCol,
                'allowsMajor'  => $allowsMajor,
            ]);
        }
    }

    // 3) من نص: إما عمودين متوازيين (names_text + majors_text) أو سطر بفاصل
    $pairs = $allowsMajor
        ? app(\App\Services\StudentImportService::class)->parseTextareaPairs($request->input('names_text'), $request->input('majors_text'))
        : array_map(fn($n)=>['name'=>$n,'major'=>null], app(\App\Services\StudentImportService::class)->parseTextareaPairs($request->input('names_text')));

    $left    = $counts['left'];
    $preview = array_slice($pairs, 0, $left);
    $dropped = array_slice($pairs, $left);

    return view('classes.students', [
        'class'        => $class,
        'students'     => $students,
        'counts'       => $counts,
        'preview'      => $preview,
        'dropped'      => $dropped,
        'fileToken'    => null,
        'headings'     => [],
        'pickedCol'    => null,
        'pickedMajor'  => null,
        'allowsMajor'  => $allowsMajor,
    ]);
}


    /** حفظ الأسماء المعروضة */
public function studentsStore(Request $request, int $id)
{
    $uid = $this->currentUserId();
    

    $class = $this->ensureOwnedClass($uid, $id);

    // من المعاينة: names[] و majors[] (majors اختيارية)
    $names  = array_values(array_map('trim', (array)$request->input('names', [])));
    $majors = array_values((array)$request->input('majors', []));

    if (empty($names)) {
        return back()->withErrors(['names' => 'لم يتم تحديد أي أسماء لإضافتها.'])->withInput();
    }

    // حد الصف
    $counts = $this->classStudentCounts($id);
    if (count($names) > $counts['left']) {
        return back()->withErrors(['names' => 'تجاوزت الحد الأقصى المسموح به في هذا الصف ('.self::MAX_STUDENTS_PER_CLASS.' طالب).'])->withInput();
    }

    // استبدال كامل؟
    if ($request->boolean('replace_all')) {
        DB::table('user_class_students')->where('class_id', $id)->delete();
    }

    $now = now();
    $rows = [];
    foreach ($names as $i => $nm) {
        $m = isset($majors[$i]) ? trim((string)$majors[$i]) : '';
        $rows[] = [
            'class_id'     => $id,
            'student_name' => $nm,
            'major'        => ($m === '' ? null : $m),
            'student_code' => null,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];
    }

    if (!empty($rows)) {
        DB::table('user_class_students')->insert($rows);
    }

    return redirect()->route('user.classes.students', $id)->with('ok', 'تم حفظ الطلاب.');
}

public function studentUpdate(Request $request, int $id, int $studentId)
{
    $uid = $this->currentUserId();
    

    $class = $this->ensureOwnedClass($uid, $id);

    $data = $request->validate([
        'student_name' => ['required','string','max:255'],
        'major'        => ['nullable','string','max:255'],
    ]);

    DB::table('user_class_students')
        ->where('id', $studentId)
        ->where('class_id', $id)
        ->update([
            'student_name' => $data['student_name'],
            'major'        => (isset($data['major']) && trim((string)$data['major']) !== '') ? trim((string)$data['major']) : null,
            'updated_at'   => now()
        ]);

    return back()->with('ok','تم تحديث بيانات الطالب.');
}


    /** حذف طالب */
    public function studentDestroy(Request $request, int $id, int $studentId)
    {
        $uid = $this->currentUserId();
        

        $this->ensureOwnedClass($uid, $id);

        DB::table('user_class_students')
            ->where('id', $studentId)
            ->where('class_id', $id)
            ->delete();

        return back()->with('ok','تم حذف الطالب.');
    }
    // يحوّل نصين متوازيين (اختياريين) إلى أزواج [ ['name'=>..,'major'=>..], ... ]

}

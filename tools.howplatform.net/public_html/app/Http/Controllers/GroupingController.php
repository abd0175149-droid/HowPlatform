<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use setasign\Fpdi\Tcpdf\Fpdi;
use TCPDF_FONTS;
use Illuminate\Support\Str;

class GroupingController extends Controller
{
    // ================= Helpers =================
    private function currentUserId(): ?int
    {
        $auth = session('auth_user');
        if (is_array($auth) && isset($auth['id'])) return (int) $auth['id'];
        if (is_object($auth) && isset($auth->id))   return (int) $auth->id;
        return null;
    }

    private function ensureOwnedSession(int $uid, int $sessionId)
    {
        $row = DB::table('grouping_sessions as gs')
            ->join('user_classes as c', 'c.id', '=', 'gs.class_id')
            ->where('gs.id', $sessionId)
            ->where('gs.user_id', $uid)
            ->select('gs.*','c.name as class_name','c.grade_level')
            ->first();
        abort_if(!$row, 404);
        return $row;
    }

    private function classStudents(int $classId)
    {
        return DB::table('user_class_students')
            ->where('class_id', $classId)
            ->select('id','student_name','major')
            ->orderBy('id')
            ->get();
    }

    private function classAllowsMajor(?string $gradeLevel): bool
    {
        $gradeLevel = trim((string)$gradeLevel);
        return !in_array($gradeLevel, ['أساسي','ثانوي'], true);
    }
    
    private function availableNameSets(): array
    {
        // مفاتيح واضحة + قوائم كبيرة لتغطي أعداد مجموعات كبيرة
        return [
            'AR_NUMERIC' => array_map(fn($i)=>"المجموعة {$i}", range(1, 300)),
    
            'A_Z' => (function(){
                $arr = [];
                $letters = range('A','Z');
                for ($i=0; $i<300; $i++) {
                    $arr[] = $letters[$i % 26] . ($i>=26 ? floor($i/26)+1 : '');
                }
                return $arr;
            })(),
    
            'AL_ASHARA_AL_MUBASHAREEN' => [
                'أبو بكر','عمر','عثمان','علي','طلحة','الزبير','عبد الرحمن بن عوف','سعد بن أبي وقاص','سعيد بن زيد','أبو عبيدة'
                // يمكنك إضافة امتداد كبير هنا لو أحببت (أسماء صحابة أكثر) لتغطية عشرات المجموعات
            ],
    
            'SAHABA_PIONEERS' => [
                'المهاجرون','الأنصار','البدريون','الحديبيون','أهل الصفة','القراء','الفقهاء','الرواة','الفرسان','الكتاب','الحفاظ','القضاة'
                // أضف المزيد لزيادة التغطية
            ],
    
            'PLANETS' => [
                'عطارد','الزهرة','الأرض','المريخ','المشتري','زحل','أورانوس','نبتون','سيريس','بلوتو','هاوميا','ميكماكي','إيريس'
                // موسع ليغطي >10
            ],
    
            'COLORS' => [
                'أزرق','أحمر','أخضر','أصفر','برتقالي','بنفسجي','وردي','سماوي','فيروزي','تركواز','نيلي','عنّابي','زيتي','ذهبي','فضي','رمادي','أسود','أبيض'
            ],
    
            'ARAB_COUNTRIES' => [
                'الأردن','فلسطين','مصر','السعودية','الإمارات','الكويت','قطر','البحرين','عُمان','اليمن','العراق','سوريا','لبنان','ليبيا','تونس','الجزائر','المغرب','موريتانيا','السودان','جيبوتي','الصومال','جزر القمر'
            ],
    
            'FLOWERS' => [
                'ورد','ياسمين','نرجس','زنبق','أقحوان','أوركيد','تيوليب','لافندر','كاميليا','قرنفل','دوار الشمس','نسرين','ريحان','غاردينيا','فل'
            ],
    
            'CARTOON_CHARACTERS' => [
                'ميكي','ميني','بطوط','بطوطة','غوفي','توم','جيري','سكوبي','سبونج','باتريك','بوباي','أوليف','سالي','غرغور','بينغو','سنوبي','ماريو','لويجي','يوغي'
            ],
        ];
    }
    /**
 * مَرْكَز تجميعي لقوائم الأسماء: يدمج القوائم المضمّنة مع القوائم القادمة من config('groupings.name_sets')
 * لو وُجد تعارض في المفتاح؛ تَغْلِب إعدادات config.
 */
private function getNameSets(): array
{
    // 1) القوائم المضمّنة الحالية (الدالة المتوفرة أصلًا availableNameSets())
    $builtin = $this->availableNameSets();

    // 2) القوائم من الإعدادات (config/groupings.php)
    $cfg = config('groupings.name_sets', []);
    if (!is_array($cfg)) $cfg = [];

    // Normalize: [{id,label,names:[...]}] -> [id => names]
    $fromConfig = [];
    foreach ($cfg as $item) {
        if (!is_array($item)) continue;
        $id    = $item['id']    ?? null;
        $names = $item['names'] ?? null;
        if (!$id) continue;

        // دعم توليد تلقائي بسيط عند غياب names
        if ($id === 'AR_NUMERIC' && (empty($names) || !is_array($names))) {
            $names = array_map(fn($i)=>"المجموعة {$i}", range(1, 300));
        }
        if ($id === 'A_Z' && (empty($names) || !is_array($names))) {
            $arr = [];
            $letters = range('A','Z');
            for ($i=0; $i<300; $i++) {
                $arr[] = $letters[$i % 26] . ($i>=26 ? floor($i/26)+1 : '');
            }
            $names = $arr;
        }

        if (is_array($names)) {
            $fromConfig[$id] = array_values(array_map(fn($v)=> (string)$v, $names));
        }
    }

    // 3) الدمج: إعدادات config تُغلِب المضمّنة
    return array_replace($builtin, $fromConfig);
}

/**
 * يعيد القوائم التي تُغطي عدد مجموعات ≥ $min
 */
private function filterNameSetsByMin(int $min): array
{
    $sets = $this->getNameSets();
    $out  = [];
    foreach ($sets as $key => $names) {
        if (is_array($names) && count($names) >= $min) {
            $out[$key] = $names;
        }
    }
    return $out;
}
/**
 * يحسب عدد المجموعات المعرفة لهذه الجلسة.
 */
private function groupsCountForSession(int $sessionId): int
{
    return (int) DB::table('grouping_results')->where('session_id', $sessionId)->count();
}

    private function pickGroupNames(int $count, string $mode = 'default', ?string $setKey = null, ?string $customCsv = null): array
    {
        $sets = $this->getNameSets();

        // default: "المجموعة 1..n"
        if ($mode === 'default') {
            return array_slice($sets['AR_NUMERIC'], 0, max(0,$count));
        }
    
        // set: اختر قائمة جاهزة
        if ($mode === 'set') {
            $key = $setKey ?: 'A_Z';
            $src = $sets[$key] ?? $sets['A_Z'];
            // لو القائمة أقصر من العدد؛ نكمّل ترقيم
            if (count($src) < $count) {
                $remain = $count - count($src);
                $src = array_merge($src, array_slice($sets['AR_NUMERIC'], 0, $remain));
            }
            return array_slice($src, 0, $count);
        }
    
        // custom: CSV
        if ($mode === 'custom') {
            $names = array_values(array_filter(array_map('trim', explode(',', (string)$customCsv))));
            if (count($names) !== $count) {
                // في حال عدم التطابق، نرجع الترقيم الافتراضي (لا نكسر التدفق)
                return array_slice($sets['AR_NUMERIC'], 0, max(0,$count));
            }
            return $names;
        }
    
        // Fallback
        return array_slice($sets['AR_NUMERIC'], 0, max(0,$count));
    }
    
    private function generateGroupColors(int $count): array
    {
        // لوحة ألوان آمنة ومتباينة؛ تتكرر عند الحاجة
        $palette = [
            '#219ebc','#fd9e02','#8ecae6','#ffb703','#2a9d8f','#e76f51',
            '#6a4c93','#ff6b6b','#4cc9f0','#90be6d','#f94144','#577590',
            '#f3722c','#43aa8b','#f8961e','#277da1','#b5179e','#00b4d8',
            '#adb5bd','#8338ec','#3a86ff','#06d6a0','#ef476f','#ffd166'
        ];
        $out = [];
        for ($i=0; $i<$count; $i++) {
            $out[] = $palette[$i % count($palette)];
        }
        return $out;
    }
    
public function applyNames(Request $request, int $id)
{
    $uid = $this->currentUserId();
    
    $session = $this->ensureOwnedSession($uid, $id);

    $data = $request->validate([
        'naming_mode'  => ['required','in:default,set,custom'],
        'name_set_key' => ['nullable','string','max:100'],
        'custom_names' => ['nullable','string','max:4000'],
    ]);

    // مجموعات الجلسة
    $groups = DB::table('grouping_results')
        ->where('session_id', $id)
        ->orderBy('group_index')
        ->get();

    if ($groups->isEmpty()) {
        return back()->withErrors(['names' => 'لا توجد مجموعات بعد. أنشئ التوزيع أولًا.']);
    }

    $count = $groups->count();

    // ✅ فحص إضافي: عند اختيار قائمة جاهزة يجب أن تحتوي على أسماء ≥ عدد المجموعات
    if (($data['naming_mode'] ?? 'default') === 'set') {
        $sets = $this->filterNameSetsByMin($count);   // يعتمد على config/groupings.php + المضمّن
        $chosenKey = (string)($data['name_set_key'] ?? '');
        if (!isset($sets[$chosenKey])) {
            return back()
                ->withErrors(['name_set_key' => "القائمة المختارة لا تحتوي على أسماء كافية لعدد مجموعاتك ({$count})."])
                ->withInput();
        }
    }

    // الاختيار الفعلي للأسماء (سيعتمد داخليًا على getNameSets() الموحّدة)
    $names  = $this->pickGroupNames(
        $count,
        $data['naming_mode'] ?? 'default',
        $data['name_set_key'] ?? null,
        $data['custom_names'] ?? null
    );

    $colors = $this->generateGroupColors($count);

    DB::beginTransaction();
    try {
        foreach ($groups as $i => $g) {
            $meta = json_decode($g->meta_json ?? '{}', true);
            if (!is_array($meta)) $meta = [];

            // نُبقي أي قيم موجودة ونُحدّث المطلوب فقط
            $meta['color']       = $colors[$i] ?? ($meta['color'] ?? null);
            $meta['naming_mode'] = $data['naming_mode'];
            $meta['name_set']    = $data['name_set_key'] ?? null;

            DB::table('grouping_results')->where('id', $g->id)->update([
                'group_label' => $names[$i] ?? $g->group_label,
                'meta_json'   => json_encode($meta, JSON_UNESCAPED_UNICODE),
                'updated_at'  => now(),
            ]);
        }
        DB::commit();
    } catch (\Throwable $e) {
        DB::rollBack();
        return back()->withErrors(['names' => 'تعذّر تحديث الأسماء.'])->withInput();
    }

    return redirect()->route('user.groupings.show', $id)
        ->with('ok', 'تم تحديث أسماء وألوان المجموعات.');
}

public function pdf(\Illuminate\Http\Request $request, int $id)
{
    $uid = $this->currentUserId();
    
    $session = $this->ensureOwnedSession($uid, $id);

    // 1) جلب المجموعات بنفس ترتيب العرض
    $groups = \DB::table('grouping_results')
        ->where('session_id', $id)
        ->orderByRaw('CAST(group_index AS SIGNED) ASC')
        ->get();

    $members = \DB::table('grouping_result_members')
        ->whereIn('result_id', $groups->pluck('id')->all() ?: [0])
        ->orderBy('student_name_snapshot')
        ->get()
        ->groupBy('result_id');

    if ($groups->isEmpty()) {
        return back()->with('error','لا يوجد توزيع لطباعته.');
    }

    // 2) تجاهل أي تسمية مخصّصة — نستخدم ترقيم افتراضي متسلسل (1..N)
    // سنستخدم عدّاد الحلقة فقط لضمان البدء من 1 دائمًا.
    $groups = $groups->map(function($g) use ($members) {
        $g->members = $members->get($g->id) ?? collect();
        return $g;
    });

    // 3) FPDI/TCPDF — تحميل القالب (لاندسكيب)
    $cfg = config('groupings_templates');
    $pdfPath = $cfg['pdf_path'] ?? null;
    if (!$pdfPath || !is_file($pdfPath)) {
        $cands = $cfg['candidates'] ?? [];
        $try = [
            $pdfPath,
            storage_path($cands['storage'] ?? ''),
            base_path($cands['base'] ?? ''),
            public_path($cands['public'] ?? ''),
        ];
        $pdfPath = null;
        foreach ($try as $p) { if ($p && is_file($p)) { $pdfPath = $p; break; } }
    }
    if (!$pdfPath) abort(404, 'ملف قالب المجموعات غير موجود.');
    $pageNo = (int)($cfg['page'] ?? 1);

    $pdf = new Fpdi('L', 'mm');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->setRTL(true);

    // خطوط عربية
    $fontRegular = 'aealarabiya';
    $fontBold    = 'aealarabiya';
    $regPath = storage_path('app/fonts/Almarai-Regular.ttf');
    $boldPath= storage_path('app/fonts/Almarai-Bold.ttf');
    if (is_file($regPath) && is_file($boldPath)) {
        $fontRegular = TCPDF_FONTS::addTTFfont($regPath, 'TrueTypeUnicode', '', 96);
        $fontBold    = TCPDF_FONTS::addTTFfont($boldPath, 'TrueTypeUnicode', '', 96);
    }

    $pageCount = $pdf->setSourceFile($pdfPath);
    if ($pageNo < 1 || $pageNo > $pageCount) abort(404, 'رقم صفحة غير صالح للقالب.');
    $tplIdx = $pdf->importPage($pageNo);
    $size   = $pdf->getTemplateSize($tplIdx);

    $pdf->AddPage($size['orientation'] ?? 'L', [$size['width'], $size['height']]);
    $pdf->useTemplate($tplIdx, 0, 0, $size['width'], $size['height'], true);

    // 4) الإعدادات (هوامش/أحجام/أعمدة)
    $W = (float)$size['width'];
    $H = (float)$size['height'];

    $L   = (float)($cfg['layout']['margin_left']   ?? 14);
    $R   = (float)($cfg['layout']['margin_right']  ?? 14);
    $T   = (float)($cfg['layout']['margin_top']    ?? 45);
    $B   = (float)($cfg['layout']['margin_bottom'] ?? 14);
    $cols= max(1, (int)($cfg['layout']['columns']  ?? 2));
    $gap = (float)($cfg['layout']['gap'] ?? 7);

    $padX = (float)($cfg['layout']['card_pad_x'] ?? 4);
    $padY = (float)($cfg['layout']['card_pad_y'] ?? 3);
    $titleGap = (float)($cfg['layout']['title_gap'] ?? 2);

    $titleSz  = (float)($cfg['layout']['title_size']   ?? 15);
    $memberSz = (float)($cfg['layout']['member_size']  ?? 12);
    $lineH    = (float)($cfg['layout']['line_h']       ?? 6.2);
    $bullet   = (string)($cfg['layout']['bullet']      ?? '• ');

    $UW = $W - $L - $R;
    $UH = $H - $T - $B;
    $cellW = ($UW - ($cols - 1) * $gap) / $cols;

    // اتجاه الأعمدة حسب الإعداد
    $rtlCols = (bool)($cfg['layout']['rtl_columns'] ?? false);
    $colX = [];
    for ($c=0; $c<$cols; $c++) {
        $colX[$c] = $rtlCols
            ? $W - $R - $cellW - $c * ($cellW + $gap) // من اليمين
            : $L + $c * ($cellW + $gap);              // من اليسار
    }
    $colY = array_fill(0, $cols, $T);

    // دوال مساعدة
    $getStringHeight = function(Fpdi $pdf, float $w, string $text, float $size, $font) {
        $pdf->SetFont($font, '', $size);
        return (float) $pdf->getStringHeight($w, $text, false, true, 0);
    };
    $toLines = function($coll) use ($bullet) {
        return collect($coll)->map(function($m){
            if (is_object($m)) return $m->student_name_snapshot ?? $m->name ?? '';
            if (is_array($m))  return $m['student_name_snapshot'] ?? $m['name'] ?? '';
            return (string)$m;
        })->filter()->values()->map(function($nm) use ($bullet) {
            $nm = trim($nm);
            return $nm === '' ? null : ($bullet.$nm);
        })->filter()->values()->all();
    };

    // 5) رسم البطاقات بلا إطار، وارتفاع ديناميكي، وترقيم افتراضي متسلسل
    $seq = 0;
    foreach ($groups as $g) {
        $seq++; // 1..N
        $lines = $toLines($g->members);
        $count = count($lines);
        $titlePrinted = 'مجموعة ' . $seq . ' (' . $count . ')';

        // قياس ارتفاع العنوان بدقة
        $titleH = $getStringHeight($pdf, $cellW - 2*$padX, $titlePrinted, $titleSz, $fontBold);
        $neededH = $padY + $titleH + $titleGap + ($count * $lineH) + $padY;

        // اختر العمود الأقصر
        $col = 0;
        for ($c=1; $c<$cols; $c++) if ($colY[$c] < $colY[$col]) $col = $c;

        // افتح صفحة جديدة إذا ما في مساحة
        if (($colY[$col] + $neededH) > ($T + $UH)) {
            $pdf->AddPage($size['orientation'] ?? 'L', [$size['width'], $size['height']]);
            $pdf->useTemplate($tplIdx, 0, 0, $size['width'], $size['height'], true);
            $colY = array_fill(0, $cols, $T);
        }

        $x = $colX[$col];
        $y = $colY[$col];
        $w = $cellW;

        // عنوان
        $pdf->SetFont($fontBold, '', $titleSz);
        $pdf->SetXY($x + $padX, $y + $padY);
        $pdf->MultiCell($w - 2*$padX, 0, $titlePrinted, 0, 'R', false, 1, '', '', true, 0, false, true, 0, 'T', true);
        $afterTitleY = (float)$pdf->GetY();

        // أعضاء
        $py = $afterTitleY + $titleGap;
        if ($count > 0) {
            $pdf->SetFont($fontRegular, '', $memberSz);
            foreach ($lines as $ln) {
                $pdf->SetXY($x + $padX, $py);
                $pdf->MultiCell($w - 2*$padX, $lineH, $ln, 0, 'R', false, 1, '', '', true, 0, false, false, $lineH, 'M', true);
                $py += $lineH;
            }
        }

        // تحديث ارتفاع العمود (بدون إطار)
        $actualH = max($neededH, ($py + $padY) - $y);
        $colY[$col] = $y + $actualH + $gap;
    }

    $file = 'groups-'.($session->id).'-'.now()->format('Ymd-His').'.pdf';
    return response($pdf->Output($file, 'S'), 200, [
        'Content-Type'        => 'application/pdf',
        'Content-Disposition' => "attachment; filename=\"$file\"",
        'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma'              => 'no-cache',
    ]);
}

    // ================= Pages =================
    public function index(Request $request)
    {
        $uid = $this->currentUserId();
        

        $sessions = DB::table('grouping_sessions as gs')
            ->join('user_classes as c', 'c.id', '=', 'gs.class_id')
            ->leftJoin('grouping_results as gr', 'gr.session_id', '=', 'gs.id')
            ->select(
                'gs.id','gs.title','gs.mode','gs.group_size','gs.group_count','gs.seed',
                'gs.status','gs.created_at','gs.class_id','c.name as class_name','gs.major_mode',
                DB::raw('COUNT(gr.id) as groups_count')
            )
            ->where('gs.user_id', $uid)
            ->groupBy('gs.id','gs.title','gs.mode','gs.group_size','gs.group_count','gs.seed',
                      'gs.status','gs.created_at','gs.class_id','c.name','gs.major_mode')
            ->orderBy('gs.created_at','desc')
            ->get();

        return view('groupings.index', compact('sessions'));
    }

    public function create(Request $request)
    {
        $uid = $this->currentUserId();
        

        $classes = DB::table('user_classes as c')
            ->leftJoin('user_class_students as s', 's.class_id', '=', 'c.id')
            ->select('c.id','c.name','c.grade_level', DB::raw('COUNT(s.id) as students_count'))
            ->where('c.user_id', $uid)
            ->groupBy('c.id','c.name','c.grade_level')
            ->orderBy('c.created_at','desc')
            ->get();
        return view('groupings.create', compact('classes'));
    }

    public function store(Request $request)
    {
        $uid = $this->currentUserId();
        

        $data = $request->validate([
            'class_id'       => ['required','integer','min:1'],
            'title'          => ['nullable','string','max:190'],
            'mode'           => ['required','in:by_size,by_count'],
            'group_size'     => ['nullable','integer','min:2','max:50'],
            'group_count'    => ['nullable','integer','min:2','max:50'],
            'major_mode'     => ['required','in:none,per_major,bundle_majors,mixed'],
            'major_selected' => ['nullable','array'],
            'major_selected.*' => ['string','max:255'],
            'keep_seed'      => ['nullable','in:1'],
        ], [
            'class_id.required'    => 'اختر الصف.',
            'mode.required'        => 'اختر طريقة التوزيع.',
            'major_mode.required'  => 'اختر وضع التوزيع حسب التخصص أو اتركه افتراضيًا.',
            'major_mode.in'        => 'قيمة وضع التخصص غير صحيحة.',
            'group_size.integer'   => 'حجم المجموعة يجب أن يكون رقمًا.',
            'group_count.integer'  => 'عدد المجموعات يجب أن يكون رقمًا.',
        ]);


        $owned = DB::table('user_classes')->where('id', $data['class_id'])->where('user_id', $uid)->first();
        if (!$owned) return back()->withErrors(['class_id'=>'هذا الصف غير موجود أو لا تملكه.'])->withInput();

        if ($data['mode']==='by_size' && empty($data['group_size'])) {
            return back()->withErrors(['group_size'=>'أدخل حجم المجموعة عند اختيار "حسب الحجم".'])->withInput();
        }
        if ($data['mode']==='by_count' && empty($data['group_count'])) {
            return back()->withErrors(['group_count'=>'أدخل عدد المجموعات عند اختيار "حسب العدد".'])->withInput();
        }

        // تحقق إتاحة التخصص + وجود تخصصات فعلاً
        $allowMajor = $this->classAllowsMajor($owned->grade_level ?? null);
        $majCount = DB::table('user_class_students')->where('class_id', $data['class_id'])
                    ->whereNotNull('major')->where('major','<>','')->distinct()->count('major');

        if ((!$allowMajor || $majCount === 0) && $data['major_mode'] !== 'none' && $data['major_mode'] !== 'mixed') {
            return back()->withErrors(['major_mode'=>'هذا الصف لا يحتوي تخصصات أو لا يسمح بها.'])->withInput();
        }

        $majorSelectedJson = null;
        if ($data['major_mode'] === 'bundle_majors') {
            $ms = array_values(array_unique(array_filter(array_map('trim', $data['major_selected'] ?? []))));
            if (count($ms) < 2) {
                return back()->withErrors(['major_selected'=>'اختر تخصصين فأكثر للتجميع.'])->withInput();
            }
            $majorSelectedJson = json_encode($ms, JSON_UNESCAPED_UNICODE);
        }

        $seed = !empty($data['keep_seed']) ? ('seed-'.(string)round(microtime(true)*1000)) : null;

        DB::table('grouping_sessions')->insert([
            'user_id'             => $uid,
            'class_id'            => (int)$data['class_id'],
            'title'               => $data['title'] ?? null,
            'mode'                => $data['mode'],
            'group_size'          => $data['mode']==='by_size'  ? (int)$data['group_size']  : null,
            'group_count'         => $data['mode']==='by_count' ? (int)$data['group_count'] : null,
            'seed'                => $seed,
            'major_mode'          => $data['major_mode'],
            'major_selected_json' => $majorSelectedJson,
            'status'              => 'draft',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        return redirect()->route('user.groupings.index')->with('ok','تم إنشاء جلسة توزيع (مسودة).');
    }

    public function quickStore(Request $request)
    {
        $uid = $this->currentUserId();
        

        // نفس التحقق مثل store()
        $data = $request->validate([
            'class_id'       => ['required','integer','min:1'],
            'title'          => ['nullable','string','max:190'],
            'mode'           => ['required','in:by_size,by_count'],
            'group_size'     => ['nullable','integer','min:2','max:50'],
            'group_count'    => ['nullable','integer','min:2','max:50'],
            'major_mode'     => ['required','in:none,per_major,bundle_majors,mixed'],
            'major_selected' => ['nullable','array'],
            'major_selected.*' => ['string','max:255'],
            'keep_seed'      => ['nullable','in:1'],
        ], [
            'class_id.required'    => 'اختر الصف.',
            'mode.required'        => 'اختر طريقة التوزيع.',
            'major_mode.required'  => 'اختر وضع التوزيع حسب التخصص أو اتركه افتراضيًا.',
            'major_mode.in'        => 'قيمة وضع التخصص غير صحيحة.',
            'group_size.integer'   => 'حجم المجموعة يجب أن يكون رقمًا.',
            'group_count.integer'  => 'عدد المجموعات يجب أن يكون رقمًا.',
        ]);

        $owned = DB::table('user_classes')->where('id', $data['class_id'])->where('user_id', $uid)->first();
        if (!$owned) return back()->withErrors(['class_id'=>'هذا الصف غير موجود أو لا تملكه.'])->withInput();

        if ($data['mode']==='by_size' && empty($data['group_size'])) {
            return back()->withErrors(['group_size'=>'أدخل حجم المجموعة عند اختيار "حسب الحجم".'])->withInput();
        }
        if ($data['mode']==='by_count' && empty($data['group_count'])) {
            return back()->withErrors(['group_count'=>'أدخل عدد المجموعات عند اختيار "حسب العدد".'])->withInput();
        }

        // شرط التخصص + وجود تخصصات فعلًا
        $allowMajor = $this->classAllowsMajor($owned->grade_level ?? null);
        $majCount = DB::table('user_class_students')->where('class_id', $data['class_id'])
                    ->whereNotNull('major')->where('major','<>','')->distinct()->count('major');

        if ((!$allowMajor || $majCount === 0) && $data['major_mode'] !== 'none' && $data['major_mode'] !== 'mixed') {
            return back()->withErrors(['major_mode'=>'هذا الصف لا يحتوي تخصصات أو لا يسمح بها.'])->withInput();
        }

        $majorSelectedJson = null;
        if ($data['major_mode'] === 'bundle_majors') {
            $ms = array_values(array_unique(array_filter(array_map('trim', $data['major_selected'] ?? []))));
            if (count($ms) < 2) {
                return back()->withErrors(['major_selected'=>'اختر تخصصين فأكثر للتجميع.'])->withInput();
            }
            $majorSelectedJson = json_encode($ms, JSON_UNESCAPED_UNICODE);
        }

        $seed = !empty($data['keep_seed']) ? ('seed-'.(string)round(microtime(true)*1000)) : ('seed-'.(string)round(microtime(true)*1000));

        // أنشئ جلسة فورًا
        $sessionId = DB::table('grouping_sessions')->insertGetId([
            'user_id'             => $uid,
            'class_id'            => (int)$data['class_id'],
            'title'               => $data['title'] ?? null,
            'mode'                => $data['mode'],
            'group_size'          => $data['mode']==='by_size'  ? (int)$data['group_size']  : null,
            'group_count'         => $data['mode']==='by_count' ? (int)$data['group_count'] : null,
            'seed'                => $seed,
            'major_mode'          => $data['major_mode'],
            'major_selected_json' => $majorSelectedJson,
            'status'              => 'draft',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        // وزّع مباشرة بدون قيود (لا قيود لأن الجلسة جديدة)
        $session = DB::table('grouping_sessions as gs')
            ->join('user_classes as c', 'c.id', '=', 'gs.class_id')
            ->where('gs.id', $sessionId)->select('gs.*','c.grade_level')->first();

        [$ok, $preview, $err] = $this->buildPreview($session, $seed);
        if (!$ok) {
            return redirect()->route('user.groupings.create')->withErrors(['generate'=>$err ?: 'تعذر التوزيع الأولي.'])->withInput();
        }

        DB::transaction(function() use ($sessionId, $preview, $seed) {
            foreach ($preview['groups'] as $grp) {
                $resId = DB::table('grouping_results')->insertGetId([
                    'session_id'  => $sessionId,
                    'group_index' => $grp['index'],
                    'group_label' => $grp['label'],
                    'meta_json'   => json_encode(['seed'=>$preview['seed']], JSON_UNESCAPED_UNICODE),
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
                if (!empty($grp['members'])) {
                    $rows = [];
                    foreach ($grp['members'] as $m) {
                        $rows[] = [
                            'result_id'             => $resId,
                            'student_id'            => $m['id'],
                            'student_name_snapshot' => $m['name'],
                        ];
                    }
                    DB::table('grouping_result_members')->insert($rows);
                }
            }
            DB::table('grouping_sessions')->where('id',$sessionId)->update([
                'status'    => 'final',
                'seed'      => $seed,
                'updated_at'=> now(),
            ]);
        });

        return redirect()->route('user.groupings.show', $sessionId)->with('ok','تم الإنشاء والتوزيع مباشرة.');
    }

    public function constraints(Request $request, int $id)
    {
        $uid     = $this->currentUserId();
        
        $session = $this->ensureOwnedSession($uid, $id);
        $students= $this->classStudents($session->class_id);

        $constraints = DB::table('grouping_constraints as gc')
            ->where('gc.session_id', $id)
            ->orderBy('gc.id','desc')->get();

        $members = DB::table('grouping_constraint_members as m')
            ->join('user_class_students as ucs', 'ucs.id', '=', 'm.student_id')
            ->whereIn('m.constraint_id', $constraints->pluck('id')->all() ?: [0])
            ->select('m.constraint_id','m.student_id','ucs.student_name')
            ->get()
            ->groupBy('constraint_id');

        return view('groupings.constraints', [
            'session'     => $session,
            'students'    => $students,
            'constraints' => $constraints,
            'members'     => $members,
            'preview'     => null,
            'previewSeed' => null,
            'errorMsg'    => null,
        ]);
    }

    public function constraintsStore(Request $request, int $id)
    {
        $uid = $this->currentUserId();
        
        $session = $this->ensureOwnedSession($uid, $id);

        $action = $request->input('action');

        if ($action === 'add_constraint') {
            $base = $request->validate([
                'type'       => ['required','in:MUST_TOGETHER,MUST_APART'],
                'note'       => ['nullable','string','max:190'],
                'student_a'  => ['nullable','integer','min:1'],
                'student_b'  => ['nullable','integer','min:1'],
                'members'    => ['nullable','array'],
                'members.*'  => ['integer','min:1'],
            ]);

            $members = [];
            if (!empty($base['members'])) {
                $members = array_values(array_unique(array_map('intval', $base['members'])));
            } else {
                $a = (int)($base['student_a'] ?? 0);
                $b = (int)($base['student_b'] ?? 0);
                if ($a > 0) $members[] = $a;
                if ($b > 0 && $b !== $a) $members[] = $b;
            }

            if (count($members) < 2) {
                return back()->withErrors(['members'=>'اختر طالبين على الأقل (ثنائيًا أو متعددًا).'])->withInput();
            }

            $cnt = DB::table('user_class_students')
                ->where('class_id', $session->class_id)
                ->whereIn('id', $members)
                ->count();
            if ($cnt !== count($members)) {
                return back()->withErrors(['members'=>'بعض الأعضاء لا ينتمون إلى هذا الصف.'])->withInput();
            }

            $cid = DB::table('grouping_constraints')->insertGetId([
                'session_id' => $id,
                'type'       => $base['type'],
                'note'       => $base['note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $rows = [];
            foreach ($members as $sid) $rows[] = ['constraint_id'=>$cid, 'student_id'=>(int)$sid];
            DB::table('grouping_constraint_members')->insert($rows);

            return back()->with('ok','تمت إضافة القيد.');
        }

        if ($action === 'remove_member') {
            $data = $request->validate([
                'constraint_id' => ['required','integer','min:1'],
                'student_id'    => ['required','integer','min:1'],
            ]);
            $c = DB::table('grouping_constraints')->where('id',$data['constraint_id'])->where('session_id',$id)->first();
            if ($c) {
                DB::table('grouping_constraint_members')
                    ->where('constraint_id',$c->id)
                    ->where('student_id',(int)$data['student_id'])
                    ->delete();

                $left = DB::table('grouping_constraint_members')->where('constraint_id',$c->id)->count();
                if ($left < 2) DB::table('grouping_constraints')->where('id',$c->id)->delete();
            }
            return back()->with('ok','تم تعديل القيد.');
        }

        if ($action === 'delete_constraint') {
            $data = $request->validate(['constraint_id'=>['required','integer','min:1']]);
            DB::table('grouping_constraints')->where('id', $data['constraint_id'])->where('session_id',$id)->delete();
            return back()->with('ok','تم حذف القيد.');
        }

        return back()->withErrors(['action'=>'طلب غير معروف.']);
    }

    public function generate(Request $request, int $id)
    {
        $uid     = $this->currentUserId();
        
        $session = $this->ensureOwnedSession($uid, $id);
        $students= $this->classStudents($session->class_id);

        $keep = (bool)$request->boolean('keep_seed');
        $forceNew = (bool)$request->boolean('reshuffle');

        if ($forceNew) {
            $effectiveSeed = 'seed-'.(string)round(microtime(true)*1000);
        } else if ($keep && !empty($session->seed)) {
            $effectiveSeed = $session->seed;
        } else {
            $effectiveSeed = 'seed-'.(string)round(microtime(true)*1000);
        }

        [$ok, $preview, $err] = $this->buildPreview($session, $effectiveSeed);
        if (!$ok) {
            $constraints = DB::table('grouping_constraints as gc')->where('gc.session_id', $id)->orderBy('gc.id','desc')->get();
            $members = DB::table('grouping_constraint_members as m')
                ->join('user_class_students as ucs', 'ucs.id', '=', 'm.student_id')
                ->whereIn('m.constraint_id', $constraints->pluck('id')->all() ?: [0])
                ->select('m.constraint_id','m.student_id','ucs.student_name')
                ->get()->groupBy('constraint_id');

            return view('groupings.constraints', [
                'session'     => $session,
                'students'    => $students,
                'constraints' => $constraints,
                'members'     => $members,
                'preview'     => null,
                'previewSeed' => $effectiveSeed,
                'errorMsg'    => $err ?: 'تعذر التوليد.',
            ]);
        }

        $constraints = DB::table('grouping_constraints as gc')->where('gc.session_id', $id)->orderBy('gc.id','desc')->get();
        $members = DB::table('grouping_constraint_members as m')
            ->join('user_class_students as ucs', 'ucs.id', '=', 'm.student_id')
            ->whereIn('m.constraint_id', $constraints->pluck('id')->all() ?: [0])
            ->select('m.constraint_id','m.student_id','ucs.student_name')
            ->get()->groupBy('constraint_id');

        return view('groupings.constraints', [
            'session'     => $session,
            'students'    => $students,
            'constraints' => $constraints,
            'members'     => $members,
            'preview'     => $preview,
            'previewSeed' => $effectiveSeed,
            'errorMsg'    => null,
        ]);
    }

    public function finalize(Request $request, int $id)
    {
        $uid = $this->currentUserId();
        
    
        $session = $this->ensureOwnedSession($uid, $id);
    
        // خيارات التوليد/البذرة (كما هو)
        $data = $request->validate([
            'keep_seed'       => ['nullable','in:1'],
            'replace_results' => ['nullable','in:1'],
            'reshuffle'       => ['nullable','in:1'],
    
            // --- جديد: مدخلات التسمية (اختيارية تمامًا) ---
            'naming_mode'     => ['nullable','in:default,set,custom'],
            'name_set_key'    => ['nullable','string','max:100'],
            'custom_names'    => ['nullable','string','max:4000'],
        ]);
    
        $keep     = !empty($data['keep_seed']);
        $forceNew = !empty($data['reshuffle']);
    
        if ($forceNew) {
            $effectiveSeed = 'seed-'.(string)round(microtime(true)*1000);
        } else if ($keep && !empty($session->seed)) {
            $effectiveSeed = $session->seed;
        } else {
            $effectiveSeed = 'seed-'.(string)round(microtime(true)*1000);
        }
    
        // بناء المعاينة
        [$ok, $preview, $err] = $this->buildPreview($session, $effectiveSeed);
        if (!$ok) {
            return back()
                ->withErrors(['generate' => $err ?: 'تعذر الحفظ النهائي.'])
                ->withInput();
        }
    
        // --- جديد: تجهيز أسماء وألوان المجموعات قبل الحفظ ---
        $groups = $preview['groups'] ?? [];
        $groupCount = is_array($groups) ? count($groups) : 0;
    
        // اقرأ مدخلات التسمية (كلها اختيارية، مع افتراض "افتراضي" إن لم تُرسَل)
        $namingMode = $data['naming_mode'] ?? 'default';   // default|set|custom
        $nameSetKey = $data['name_set_key'] ?? null;       // مثل PLANETS
        $customCsv  = $data['custom_names'] ?? null;       // "اسم1,اسم2,..."
    
        // احصل على مصفوفة أسماء بحجم عدد المجموعات
        $names  = $this->pickGroupNames($groupCount, $namingMode, $nameSetKey, $customCsv);
        // ألوان مميّزة لكل مجموعة
        $colors = $this->generateGroupColors($groupCount);
    
        // طبّق الاسم/اللون على مصفوفة المعاينة (لا يغيّر منطق التوزيع)
        for ($i = 0; $i < $groupCount; $i++) {
            if (isset($groups[$i])) {
                $groups[$i]['label'] = $names[$i] ?? ($groups[$i]['label'] ?? ('المجموعة '.($i+1)));
                $groups[$i]['color'] = $colors[$i] ?? null;
            }
        }
        $preview['groups'] = $groups;
    
        // الحفظ النهائي في الجداول
        DB::transaction(function() use ($id, $preview, $effectiveSeed, $namingMode, $nameSetKey) {
    
            // لو اخترت replace_results مستقبلاً بمعنى مختلف، يمكن استخدامه هنا.
            // حالياً منطقك يحذف دائمًا نتائج الجلسة الحالية قبل الإدخال — أبقيته كما هو.
            DB::table('grouping_results')->where('session_id', $id)->delete();
    
            foreach ($preview['groups'] as $grp) {
                $meta = [
                    'seed'        => $preview['seed'] ?? $effectiveSeed,
                    'color'       => $grp['color'] ?? null,
                    'naming_mode' => $namingMode,
                    'name_set'    => $nameSetKey,
                ];
    
                $resId = DB::table('grouping_results')->insertGetId([
                    'session_id'  => $id,
                    'group_index' => $grp['index'],
                    'group_label' => $grp['label'], // ← صار الاسم النهائي
                    'meta_json'   => json_encode($meta, JSON_UNESCAPED_UNICODE),
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
    
                if (!empty($grp['members'])) {
                    $rows = [];
                    foreach ($grp['members'] as $m) {
                        $rows[] = [
                            'result_id'             => $resId,
                            'student_id'            => $m['id'],
                            'student_name_snapshot' => $m['name'],
                        ];
                    }
                    if (!empty($rows)) {
                        DB::table('grouping_result_members')->insert($rows);
                    }
                }
            }
    
            DB::table('grouping_sessions')->where('id',$id)->update([
                'status'     => 'final',
                'seed'       => $effectiveSeed,
                'updated_at' => now(),
            ]);
        });
    
        return redirect()
            ->route('user.groupings.show', $id)
            ->with('ok','تم حفظ التوزيع نهائيًا.');
    }

    public function reshuffle(Request $request, int $id)
    {
        $uid     = $this->currentUserId();
        
        $session = $this->ensureOwnedSession($uid, $id);

        $request->merge(['reshuffle' => '1', 'keep_seed' => $request->input('keep_seed','1')]);
        return $this->finalize($request, $id);
    }
/*
public function show(Request $request, int $id)
{
    $uid = $this->currentUserId();
    

    // التأكد من ملكية الجلسة
    $session = $this->ensureOwnedSession($uid, $id);

    // جلب المجموعات مرتبة
    $groups = DB::table('grouping_results')
        ->where('session_id', $id)
        ->orderBy('group_index')
        ->get();

    // جلب الأعضاء وإسنادهم لكل نتيجة مجموعة
    $members = DB::table('grouping_result_members')
        ->whereIn('result_id', $groups->pluck('id')->all() ?: [0])
        ->orderBy('student_name_snapshot')
        ->get()
        ->groupBy('result_id');

    // فك meta_json وتمرير معلومات إضافية جاهزة للعرض
    $groups = $groups->map(function($g) use ($members) {
        $meta = json_decode($g->meta_json ?? '{}', true);
        if (!is_array($meta)) $meta = [];

        // تم حقن color/naming_mode/name_set عند finalize/applyNames
        $g->color        = $meta['color']       ?? null;
        $g->naming_mode  = $meta['naming_mode'] ?? null;   // default | set | custom
        $g->name_set     = $meta['name_set']    ?? null;   // مثل PLANETS, COLORS...
        $g->seed         = $meta['seed']        ?? null;

        // أعضاء هذه المجموعة
        $g->members = $members->get($g->id) ?? collect();

        return $g;
    });

    // عدد المجموعات في الجلسة
    $minGroups = (int) DB::table('grouping_results')->where('session_id', $id)->count();

    // قوائم الأسماء المُفلترة بحسب العدد (تقرأ من config + المضمّن)
    $nameSets = $this->filterNameSetsByMin($minGroups);

    return view('groupings.show', [
        'session'   => $session,
        'groups'    => $groups,
        'members'   => $members,   // إن كنت تحتاجه بشكل منفصل في أماكن أخرى
        'nameSets'  => $nameSets,  // تُستخدم في واجهة التسمية (قائمة جاهزة)
        'minGroups' => $minGroups, // للرسائل/التحقق على الواجهة
    ]);
}
*/

public function show(Request $request, int $id)
{
    $uid = $this->currentUserId();
    

    // التأكد من ملكية الجلسة
    $session = $this->ensureOwnedSession($uid, $id);

    // جلب المجموعات مرتبة
    $groups = DB::table('grouping_results')
        ->where('session_id', $id)
        ->orderBy('group_index')
        ->get();

    // جلب الأعضاء وإسنادهم لكل نتيجة مجموعة
    $members = DB::table('grouping_result_members')
        ->whereIn('result_id', $groups->pluck('id')->all() ?: [0])
        ->orderBy('student_name_snapshot')
        ->get()
        ->groupBy('result_id');

    // فك meta_json وتمرير معلومات إضافية جاهزة للعرض
    $groups = $groups->map(function($g) use ($members) {
        $meta = json_decode($g->meta_json ?? '{}', true);
        if (!is_array($meta)) $meta = [];

        // تم حقن color/naming_mode/name_set عند finalize/applyNames
        $g->color        = $meta['color']       ?? null;
        $g->naming_mode  = $meta['naming_mode'] ?? null;   // default | set | custom
        $g->name_set     = $meta['name_set']    ?? null;   // مثل PLANETS, COLORS...
        $g->seed         = $meta['seed']        ?? null;

        // أعضاء هذه المجموعة
        $g->members = $members->get($g->id) ?? collect();

        return $g;
    });

    // عدد المجموعات في الجلسة
    $minGroups = (int) DB::table('grouping_results')->where('session_id', $id)->count();

    // قوائم الأسماء المُفلترة بحسب العدد (مبنية 100% من config + المضمّن عبر getNameSets())
    $nameSets = $this->filterNameSetsByMin($minGroups); // [key => [names...]]

    // استخراج اللِّيبلز مباشرة من ملف config فقط (بدون أي خرائط يدوية)
    $nameSetLabels = [];
    foreach ((array) config('groupings.name_sets', []) as $item) {
        if (!is_array($item)) continue;
        $confId = $item['id'] ?? null;
        if ($confId) {
            $nameSetLabels[$confId] = (string) ($item['label'] ?? $confId);
        }
    }

    return view('groupings.show', [
        'session'       => $session,
        'groups'        => $groups,
        'members'       => $members,      // إن كنت تحتاجه بشكل منفصل في أماكن أخرى
        'nameSets'      => $nameSets,     // تُستخدم في واجهة التسمية (قوائم جاهزة)
        'nameSetLabels' => $nameSetLabels,// لعرض العناوين من config مباشرة
        'minGroups'     => $minGroups,    // للرسائل/التحقق على الواجهة
    ]);
}


    public function destroyPost(Request $request, int $id)
    {
        return $this->destroy($request, $id);
    }
    
    public function destroy(Request $request, int $id)
    {
        $uid = $this->currentUserId();
        
        $this->ensureOwnedSession($uid, $id);
    
        DB::table('grouping_sessions')->where('id',$id)->delete();
        return redirect()->route('user.groupings.index')->with('ok','تم حذف الجلسة وكل بياناتها.');
    }
    // ================= API =================
    public function classMajors(Request $request, int $classId)
    {
        $uid = $this->currentUserId();
        if (!$uid) return response()->json(['ok'=>false,'error'=>'unauthorized'], 401);

        $cls = DB::table('user_classes')->where('id',$classId)->where('user_id',$uid)->first();
        if (!$cls) return response()->json(['ok'=>false,'error'=>'notfound'], 404);

        $allow = $this->classAllowsMajor($cls->grade_level ?? null);
        if (!$allow) return response()->json(['ok'=>true,'allow'=>false,'majors'=>[]]);

        $majors = DB::table('user_class_students')
            ->where('class_id', $classId)
            ->whereNotNull('major')->where('major','<>','')
            ->select('major')->distinct()->orderBy('major')->pluck('major')->all();

        return response()->json(['ok'=>true,'allow'=>true,'majors'=>$majors]);
    }

    private function buildPreview(object $session, string $seed): array
    {
        $students = DB::table('user_class_students')
            ->where('class_id', $session->class_id)
            ->select('id','student_name','major')
            ->orderBy('id')
            ->get();

        $N = $students->count();
        if ($N < 2) return [false, [], 'عدد الطلاب غير كافٍ للتوزيع.'];

        // 2) القيود
        $constraints = DB::table('grouping_constraints')->where('session_id', $session->id)->get();
        $cMembers    = DB::table('grouping_constraint_members')->whereIn('constraint_id', $constraints->pluck('id')->all() ?: [0])->get()->groupBy('constraint_id');

        // 3) Union-Find لمَعًا دائمًا
        $parent = [];
        foreach ($students as $s) $parent[$s->id] = $s->id;
        $find = function($x) use (&$parent, &$find) {
            if ($parent[$x] !== $x) $parent[$x] = $find($parent[$x]);
            return $parent[$x];
        };
        $union = function($a,$b) use (&$parent, $find) {
            $ra = $find($a); $rb = $find($b);
            if ($ra !== $rb) $parent[$rb] = $ra;
        };

        foreach ($constraints as $c) {
            if ($c->type !== 'MUST_TOGETHER') continue;
            $m = ($cMembers[$c->id] ?? collect())->pluck('student_id')->all();
            if (count($m) >= 2) {
                $base = $m[0];
                for ($i=1; $i<count($m); $i++) $union($base, $m[$i]);
            }
        }

        $blocks = [];
        foreach ($students as $s) {
            $r = $find($s->id);
            if (!isset($blocks[$r])) $blocks[$r] = ['ids'=>[], 'names'=>[], 'majors'=>[], 'size'=>0];
            $blocks[$r]['ids'][]    = $s->id;
            $blocks[$r]['names'][]  = $s->student_name;
            $blocks[$r]['majors'][] = (string)($s->major ?? '');
            $blocks[$r]['size']++;
        }

        // تحقق MUST_APART
        $apartSets = [];
        foreach ($constraints as $c) {
            if ($c->type !== 'MUST_APART') continue;
            $m = ($cMembers[$c->id] ?? collect())->pluck('student_id')->all();
            $seenRoots = [];
            foreach ($m as $sid) {
                $root = $find($sid);
                if (isset($seenRoots[$root])) {
                    return [false, [], 'قيد "لا يلتقون" يحتوي طلابًا مدموجين في كتلة واحدة.'];
                }
                $seenRoots[$root] = true;
            }
            $apartSets[$c->id] = array_keys($seenRoots);
        }

        // 4) عدد المجموعات / الحجم
        if ($session->mode === 'by_size') {
            $groupSize  = max(2, (int)$session->group_size);
            $groupCount = (int)ceil($N / $groupSize);
        } else {
            $groupCount = max(2, (int)$session->group_count);
            $groupSize  = (int)ceil($N / $groupCount);
        }
        if ($groupCount < 2) return [false, [], 'عدد المجموعات غير صالح.'];

        foreach ($apartSets as $cid => $roots) if (count($roots) > $groupCount) return [false, [], 'قيد "لا يلتقون" يحوي أعضاء أكثر من عدد المجموعات.'];

        if ($session->mode === 'by_size') {
            $maxBlock = 0; foreach ($blocks as $b) $maxBlock = max($maxBlock, $b['size']);
            if ($maxBlock > $groupSize) return [false, [], 'أكبر كتلة "معًا دائمًا" تتجاوز حجم المجموعة.'];
        }

        // 5) ترتيب حتمي بالـ seed
        $blockList = [];
        foreach ($blocks as $root => $b) {
            $key = sprintf('%s|%d', $seed, $root);
            $hash = crc32($key);
            $blockList[] = ['root'=>$root, 'size'=>$b['size'], 'hash'=>$hash, 'majors'=>$b['majors']];
        }
        usort($blockList, function($a,$b){
            if ($a['size'] === $b['size']) return $a['hash'] <=> $b['hash'];
            return ($a['size'] > $b['size']) ? -1 : 1;
        });

        // 6) المجموعات
        $groups = [];
        $letters = range('A','Z');
        for ($i=0; $i<$groupCount; $i++) {
            $groups[$i] = [
                'index'   => $i+1,
                'label' => (string)($i + 1),
                'members' => [],
                'size'    => 0,
                'apart_flags' => [],
                // تتبّع بسيط لغلبة التخصصات داخل المجموعة
                'major_hist' => [],
            ];
        }

        $blockApartMap = [];
        foreach ($apartSets as $cid => $roots) foreach ($roots as $r) { $blockApartMap[$r][$cid] = true; }

        $groupHash = [];
        for ($i=0; $i<$groupCount; $i++) $groupHash[$i] = crc32($seed.'|G|'.$i);

        // ======= تفضيل التخصص مع احترام العدد/الحجم =======
        $majorMode = $session->major_mode ?? 'none';
        $allowMajor = $this->classAllowsMajor($session->grade_level ?? null);
        if (!$allowMajor) $majorMode = 'none';

        $selectedMajors = [];
        if ($majorMode === 'bundle_majors' && !empty($session->major_selected_json)) {
            $tmp = json_decode($session->major_selected_json, true);
            if (is_array($tmp)) $selectedMajors = array_values(array_unique(array_filter(array_map('strval', $tmp))));
        }

        // ماب: تخصّص -> مجموعة مفضلة (ثابت عبر seed)
        $preferredGroupForMajor = function(string $major) use ($seed, $groupCount) {
            $h = crc32($seed.'|MJ|'.$major);
            return (int)($h % $groupCount);
        };

        foreach ($blockList as $blk) {
            $r    = $blk['root'];
            $b    = $blocks[$r];
            $size = $b['size'];

            // التخصص الغالب في الكتلة
            $majArr = array_filter(array_map('trim', $blk['majors']));
            $dom = count($majArr) ? array_count_values($majArr) : [];
            arsort($dom);
            $majorLabel = count($dom) ? (string)array_key_first($dom) : '';

            // الترتيب الأساسي: أصغر مجموعة فالأصغر
            $order = [];
            for ($g=0; $g<$groupCount; $g++) {
                $order[] = ['g'=>$g, 'key'=>sprintf('%08d-%u', $groups[$g]['size'], $groupHash[$g])];
            }
            usort($order, fn($x,$y)=> strcmp($x['key'],$y['key']));

            // لو per_major: جرّب أولاً المجموعة المفضلة لهذا التخصص
            $candidateIndices = array_column($order, 'g');
            if ($majorMode === 'per_major' && $majorLabel !== '') {
                $pref = $preferredGroupForMajor($majorLabel);
                // قدّم المجموعة المفضلة إلى مقدمة القائمة
                $candidateIndices = array_values(array_unique(array_merge([$pref], $candidateIndices)));
            }

            // لو bundle_majors: إن كانت هذه الكتلة ضمن التخصصات المختارة، فضّل المجموعة 0
            if ($majorMode === 'bundle_majors' && $majorLabel !== '' && in_array($majorLabel, $selectedMajors, true)) {
                $candidateIndices = array_values(array_unique(array_merge([0], $candidateIndices)));
            }

            $placed = false;
            foreach ($candidateIndices as $gi) {
                if ($session->mode === 'by_size' && ($groups[$gi]['size'] + $size) > (int)$session->group_size) continue;

                // MUST_APART
                $ok = true;
                foreach (($blockApartMap[$r] ?? []) as $cid => $_true) {
                    if (!empty($groups[$gi]['apart_flags'][$cid])) { $ok = false; break; }
                }
                if (!$ok) continue;

                // ضع الكتلة
                foreach ($b['ids'] as $k => $sid) {
                    $groups[$gi]['members'][] = ['id'=>$sid, 'name'=>$b['names'][$k]];
                }
                $groups[$gi]['size'] += $size;
                foreach (($blockApartMap[$r] ?? []) as $cid => $_true) $groups[$gi]['apart_flags'][$cid] = true;

                // حدث إحصاء التخصص للمجموعة (تأثير ناعم لاحقًا)
                if ($majorLabel !== '') {
                    if (!isset($groups[$gi]['major_hist'][$majorLabel])) $groups[$gi]['major_hist'][$majorLabel] = 0;
                    $groups[$gi]['major_hist'][$majorLabel] += $size;
                }

                $placed = true; break;
            }

            if (!$placed) return [false, [], 'تعذر استيفاء القيود/السعة مع إعدادات التوزيع.'];
        }

        foreach ($groups as &$g) {
            unset($g['apart_flags'], $g['major_hist']);
        }

        return [true, ['seed'=>$seed, 'groups'=>array_values($groups)], null];
    }
}

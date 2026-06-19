<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cookie;
use PhpOffice\PhpSpreadsheet\IOFactory;
use setasign\Fpdi\Tcpdf\Fpdi;
use TCPDF_FONTS;
use App\Models\Tool;
use App\Models\ToolStat;

class ScheduleToolController extends Controller
{
    /** slug موحّد للأداة */
    private string $toolSlug = 'schedule-generator';

    /** tool_id مخزّن بعد أول استعلام */
    private ?int $toolId = null;

    /** قراءة tool_id من الـ slug مع كاش داخلي */
    private function getToolId(): ?int
    {
        if ($this->toolId !== null) return $this->toolId;
        $tool = Tool::where('slug', $this->toolSlug)->first();
        $this->toolId = $tool?->id;
        return $this->toolId;
    }

    /** تهيئة صف tool_stats عند فتح الصفحة لأول مرة */
    private function ensureStatsRow(): void
    {
        if (!$id = $this->getToolId()) return;
        ToolStat::firstOrCreate(['tool_id' => $id]);
    }

    /** زيادة عدّاد الاستخدام */
    private function bumpToolUsage(): void
    {
        if (!$id = $this->getToolId()) return;

        try {
            DB::transaction(function () use ($id) {
                $row = DB::table('tool_stats')->where('tool_id', $id)->lockForUpdate()->first();

                if ($row) {
                    DB::table('tool_stats')->where('tool_id', $id)->update([
                        'uses_count'   => (int)$row->uses_count + 1,
                        'last_used_at' => now(),
                        'updated_at'   => now(),
                    ]);
                } else {
                    DB::table('tool_stats')->insert([
                        'tool_id'      => $id,
                        'uses_count'   => 1,
                        'unique_users_count' => 0,
                        'rating_avg'   => 0,
                        'rating_count' => 0,
                        'last_used_at' => now(),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            // لا تعطل تنزيل الـ PDF بسبب إحصاءات
        }
    }

    /** احتساب مستخدم فريد (كوكي 30 يوم) */
    private function bumpUniqueVisitor(Request $request): void
    {
        if (!$id = $this->getToolId()) return;

        $cookieKey = 'tool_' . $id . '_seen';
        if ($request->cookies->has($cookieKey)) return;

        try {
            DB::table('tool_stats')->where('tool_id', $id)->update([
                'unique_users_count' => DB::raw('unique_users_count + 1'),
                'updated_at'         => now(),
            ]);
            cookie()->queue(cookie()->make($cookieKey, '1', 60*24*30, null, null, false, false, false, 'Lax'));
        } catch (\Throwable $e) {
            // تجاهل خطأ الإحصاءات
        }
    }

    // =========================== التقييم ===========================

    public function rate(Request $request)
    {
        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
        ]);

        $tool = Tool::where('slug', $this->toolSlug)->first();
        if (!$tool) {
            return response()->json(['ok' => false, 'msg' => 'الأداة غير موجودة'], 404);
        }

        // منع تكرار التقييم على نفس الجهاز
        $cookieName = 'rated_tool_' . str_replace(['-','.'], '_', $this->toolSlug);
        if ($request->cookies->has($cookieName)) {
            $row = DB::table('tool_stats')->where('tool_id', $tool->id)->first();
            return response()->json([
                'ok'           => true,
                'rating_avg'   => $row ? round((float)$row->rating_avg, 2) : 0,
                'rating_count' => $row ? (int)$row->rating_count : 0,
                'msg'          => 'تم تسجيل تقييمك سابقاً على هذا الجهاز.',
            ]);
        }

        ToolStat::firstOrCreate(['tool_id' => $tool->id]);

        DB::table('tool_stats')->where('tool_id', $tool->id)->update([
            'rating_avg'   => DB::raw('(rating_avg * rating_count + '.(int)$data['rating'].') / (rating_count + 1)'),
            'rating_count' => DB::raw('rating_count + 1'),
            'updated_at'   => now(),
        ]);

        $row    = DB::table('tool_stats')->where('tool_id', $tool->id)->first();
        $cookie = Cookie::make($cookieName, '1', 60*24*365, null, null, false, false, false, 'Lax');

        return response()->json([
            'ok'           => true,
            'rating_avg'   => round((float)$row->rating_avg, 2),
            'rating_count' => (int)$row->rating_count,
        ])->cookie($cookie);
    }

    // =========================== الإعدادات القديمة لديك.. ===========================
    private array $COURSES = [
        'بيداغوجيا التعلم والتعليم',
        'أساليب تدريس المبحث',
        'الخبرة المدرسية',
    ];

    private array $COURSE_FILES = [
        'بيداغوجيا التعلم والتعليم' => 'app/tools/schedule/pedagogy.xlsx',
        'أساليب تدريس المبحث'        => 'app/tools/schedule/methods.xlsx',
        'الخبرة المدرسية'            => 'app/tools/schedule/practicum.xlsx',
    ];

    private string $CACHE_KEY = 'tools.schedule.excel_memory_v1';
    private int    $CACHE_TTL_SECONDS = 300;

    // =========================== صفحات / واجهات ============================

    public function index()
    {
        // تهيئة tool_stats مبكراً
        $this->ensureStatsRow();

        $this->ensureLoaded();
        return view('tools.schedule.index');
    }

    public function courses() { $this->ensureLoaded(); return response()->json($this->COURSES); }

    public function sections(Request $request)
    {
        $this->ensureLoaded();
        $course = trim((string) $request->query('course', ''));
        abort_if($course === '' || !in_array($course, $this->COURSES, true), 422, 'course غير صحيح');

        $mem = Cache::get($this->CACHE_KEY, []);
        $sections = collect($mem['by_course'][$course] ?? [])
            ->pluck('section')->filter()->unique()->sort()->values()->all();
        return response()->json($sections);
    }

    public function sectionDetails(Request $request)
    {
        $this->ensureLoaded();
        $course  = trim((string) $request->query('course', ''));
        $section = trim((string) $request->query('section', ''));
        abort_if($course === '' || $section === '', 422, 'course و section مطلوبان');

        $mem = Cache::get($this->CACHE_KEY, []);
        $rows = $mem['by_course'][$course] ?? [];
        $row  = collect($rows)->first(fn($r) => (string)($r['section'] ?? '') === $section);

        return response()->json($row ?: []);
    }

    // =========================== التحميل/الكاش =============================

    private function ensureLoaded(): void
    {
        $paths = [];
        $latestMtime = 0;
        foreach ($this->COURSE_FILES as $course => $rel) {
            $abs = storage_path($rel);
            $paths[$course] = $abs;
            if (is_file($abs)) $latestMtime = max($latestMtime, (int) @filemtime($abs));
        }

        $mem = Cache::get($this->CACHE_KEY);
        $haveCache = is_array($mem) && isset($mem['loaded_at'], $mem['latest_mtime']);

        if ($latestMtime === 0) {
            if (!$haveCache) {
                Cache::put($this->CACHE_KEY, [
                    'loaded_at'    => time(),
                    'latest_mtime' => 0,
                    'by_course'    => [],
                ], $this->CACHE_TTL_SECONDS);
            }
            return;
        }

        $needReload = !$haveCache
                   || ($latestMtime > (int) $mem['latest_mtime'])
                   || ($mem['loaded_at'] + $this->CACHE_TTL_SECONDS < time());

        if (!$needReload) return;

        $lock = Cache::lock('tools.schedule.excel_lock', 15);
        if (!$lock->get()) return;

        try {
            $byCourse = [];
            foreach ($paths as $course => $absPath) {
                if (!is_file($absPath)) { $byCourse[$course] = []; continue; }
                $rows = $this->loadSpreadsheet($absPath);
                $mapped = [];
                foreach ($rows as $r) {
                    $mapped[] = [
                        'course'  => $course,
                        'section' => trim((string)($r['رقم الشعبة'] ?? '')),
                        'teacher' => trim((string)($r['اسم المدرس'] ?? '')),
                        'days'    => $this->normalizeDays((string)($r['أيام الدراسة'] ?? '')),
                        'room'    => $this->normalizeRoom((string)($r['رقم القاعة'] ?? '')),
                        'time'    => $this->normalizeTime((string)($r['وقت الجلسة'] ?? '')),
                    ];
                }
                $byCourse[$course] = $mapped;
            }

            Cache::put($this->CACHE_KEY, [
                'loaded_at'    => time(),
                'latest_mtime' => $latestMtime,
                'by_course'    => $byCourse,
            ], $this->CACHE_TTL_SECONDS);

        } finally {
            optional($lock)->release();
        }
    }

    private function loadSpreadsheet(string $absPath): array
    {
        try {
            $reader = IOFactory::createReaderForFile($absPath);
            $reader->setReadDataOnly(true);
            $ss = $reader->load($absPath);
            $ws = $ss->getSheet(0);

            $rows = $ws->toArray(null, true, true, true);
            if (!$rows || count($rows) < 2) return [];

            $firstKey = array_key_first($rows);
            $headers  = array_map('trim', array_values($rows[$firstKey]));
            unset($rows[$firstKey]);

            $out = [];
            foreach ($rows as $r) {
                $vals = array_values($r);
                $row  = [];
                foreach ($headers as $i => $h) {
                    if ($h === '' || $h === null) continue;
                    $row[$h] = $vals[$i] ?? null;
                }
                if (array_filter($row, fn($v) => $v !== null && $v !== '')) $out[] = $row;
            }
            return $out;

        } catch (\Throwable $e) {
            return [];
        }
    }

    // ============================== تنسيقات ================================

    private function normalizeDays(string $s): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        $s = str_replace(['–','—'], '-', $s);
        $pairs = [
            ['الاثنين','اثنين'],
            ['الأحد','أحد'], ['الاحد','أحد'],
            ['الثلاثاء','ثلاثاء'],
            ['الأربعاء','أربعاء'], ['الاربعاء','أربعاء'],
        ];
        foreach ($pairs as [$src,$dst]) { $s = str_replace($src,$dst,$s); }
        return $s;
    }

    private function normalizeRoom(string $s): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        $s = str_replace(['–','—'], '-', $s);
        return preg_replace('/\s*-\s*/', '-', $s);
    }

    private function normalizeTime(string $s): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        return str_replace(['–','—'], '-', $s);
    }

    // ============================== PDF ================================

    public function pdf(Request $request)
    {
        // احتساب المستخدم الفريد قبل الإخراج
        $this->bumpUniqueVisitor($request);

        $this->ensureLoaded();
        $mem = Cache::get($this->CACHE_KEY, []);
        $byCourse = $mem['by_course'] ?? [];

        $map = [
            'secA' => 'بيداغوجيا التعلم والتعليم',
            'secB' => 'أساليب تدريس المبحث',
            'secC' => 'الخبرة المدرسية',
        ];
        $picks = [];

        if ($request->has('selections')) {
            foreach ((array) $request->input('selections', []) as $sel) {
                $course  = trim((string)($sel['course']  ?? ''));
                $section = trim((string)($sel['section'] ?? ''));
                if ($course !== '' && $section !== '') {
                    $row = collect($byCourse[$course] ?? [])->first(fn($r)=> (string)($r['section'] ?? '') === $section);
                    if ($row) $picks[] = $row;
                }
            }
        } else {
            foreach ($map as $param => $course) {
                $section = trim((string)$request->query($param, ''));
                if ($section === '') continue;
                $row = collect($byCourse[$course] ?? [])->first(fn($r)=> (string)($r['section'] ?? '') === $section);
                if ($row) $picks[] = $row;
            }
        }

        if (empty($picks)) {
            return back()->with('error','لم يتم اختيار أي شعبة لتوليد الملف.');
        }

        $cfg = config('schedule_pdf', []);
        $pdfPath = $cfg['pdf_path'] ?? null;
        if (!$pdfPath || !is_file($pdfPath)) {
            $cands = $cfg['candidates'] ?? [];
            $try = array_filter([
                $pdfPath,
                storage_path($cands['storage'] ?? ''),
                base_path($cands['base'] ?? ''),
                public_path($cands['public'] ?? ''),
            ]);
            $pdfPath = null;
            foreach ($try as $p) { if ($p && is_file($p)) { $pdfPath = $p; break; } }
        }
        $pageNo = (int)($cfg['page'] ?? 1);

        $pdf = new Fpdi('L', 'mm');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->setRTL(true);

        $fontRegular = 'aealarabiya';
        $fontBold    = 'aealarabiya';
        $regPath = storage_path('app/fonts/Almarai-Regular.ttf');
        $boldPath= storage_path('app/fonts/Almarai-Bold.ttf');
        if (is_file($regPath) && is_file($boldPath)) {
            $fontRegular = TCPDF_FONTS::addTTFfont($regPath, 'TrueTypeUnicode', '', 96);
            $fontBold    = TCPDF_FONTS::addTTFfont($boldPath, 'TrueTypeUnicode', '', 96);
        }

        $W = 297; $H = 210;
        $tplIdx = null; $size = ['width'=>$W,'height'=>$H,'orientation'=>'L'];
        if ($pdfPath && is_file($pdfPath)) {
            $pageCount = $pdf->setSourceFile($pdfPath);
            if ($pageNo < 1 || $pageNo > $pageCount) abort(404, 'رقم صفحة غير صالح للقالب.');
            $tplIdx = $pdf->importPage($pageNo);
            $size   = $pdf->getTemplateSize($tplIdx);
            $W = (float)$size['width']; $H = (float)$size['height'];
        }
        $pdf->AddPage($size['orientation'] ?? 'L', [$W, $H]);
        if ($tplIdx) $pdf->useTemplate($tplIdx, 0, 0, $W, $H, true);

        $L = (float)($cfg['layout']['margin_left']   ?? 14);
        $R = (float)($cfg['layout']['margin_right']  ?? 14);
        $T = (float)($cfg['layout']['margin_top']    ?? 28);
        $B = (float)($cfg['layout']['margin_bottom'] ?? 14);

        $title    = (string)($cfg['title'] ?? 'جدول الطالب');
        $titleSz  = (float)($cfg['title_size'] ?? 16);
        $headSz   = (float)($cfg['head_size']  ?? 12.5);
        $cellSz   = (float)($cfg['cell_size']  ?? 12);
        $rowH     = (float)($cfg['row_h']      ?? 9);
        $gapTitle = (float)($cfg['title_gap']  ?? 6);

        $headers = ['المادة','الشعبة','الموعد','المدرّس','القاعة','أيّام الدوام'];
        $UW = $W - $L - $R;
        $weights = [0.18, 0.12, 0.18, 0.25, 0.12, 0.15];
        $colW = array_map(fn($w)=> $UW*$w, $weights);

        $pdf->SetFont($fontBold,'',$titleSz);
        $pdf->SetTextColor(30,30,30);
        $pdf->SetXY($L, $T);
        $pdf->Cell($UW, 0, $title, 0, 1, 'C', false, '', 0, false, 'T', 'M');

        $y = $T + $gapTitle + 3;
        $pdf->SetFont($fontBold,'',$headSz);
        $pdf->SetFillColor(235,238,243);
        $pdf->SetTextColor(20,20,20);
        $pdf->SetXY($L, $y);
        foreach ($headers as $i=>$h) $pdf->Cell($colW[$i], $rowH, $h, 1, 0, 'C', true);
        $y += $rowH;

        $pdf->SetFont($fontRegular,'',$cellSz);
        $pdf->SetTextColor(0,0,0);
        $pdf->SetFillColor(250,250,250);

        foreach ($picks as $r) {
            $cells = [
                (string)($r['course'] ?? ''),
                (string)($r['section'] ?? ''),
                (string)($r['time'] ?? ''),
                (string)($r['teacher'] ?? ''),
                (string)($r['room'] ?? ''),
                (string)($r['days'] ?? ''),
            ];
            $pdf->SetXY($L, $y);
            foreach ($cells as $i=>$val) {
                $align = $i===3 ? 'R' : 'C';
                $pdf->Cell($colW[$i], $rowH, $val, 1, 0, $align, false);
            }
            $y += $rowH;
        }

        $file = 'student-schedule-'.now()->format('Ymd-His').'.pdf';

        // تسجيل الاستخدام مرة واحدة في هذا الموضع
        $this->bumpToolUsage();

        return response($pdf->Output($file, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"$file\"",
            'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'              => 'no-cache',
        ]);
    }
}

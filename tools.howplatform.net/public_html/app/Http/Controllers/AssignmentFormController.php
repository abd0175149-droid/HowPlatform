<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cookie;
use setasign\Fpdi\Tcpdf\Fpdi;

class AssignmentFormController extends Controller
{
    /* ===================== Helpers ===================== */
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

    /* ===================== واجهة الأداة ===================== */
    public function show(Request $request)
    {
        // شرط الدخول بدون ميدل وير
        $uid = $this->currentUserId();
        if (!$uid) {
            $redirect = url()->current();
            return redirect()->route('login', ['redirect' => $redirect])
                ->with('status', 'الرجاء تسجيل الدخول أولاً.');
        }

        $years = $this->academicYears();

        $universities = [
            'jordan'    => ['name'=>'الجامعة الأردنية',   'logo'=>asset('assets/images/universities/jordan.png')],
           /* 'yarmouk'   => ['name'=>'جامعة اليرموك',      'logo'=>asset('assets/images/universities/yarmouk.png')],
            'mutah'     => ['name'=>'جامعة مؤتة',         'logo'=>asset('assets/images/universities/mutah.png')],
            'hashemite' => ['name'=>'الجامعة الهاشمية',   'logo'=>asset('assets/images/universities/hashemite.png')],*/
            
        ];

        $taskTypes = ['مهمة قبلية','مهمة بعدية'];
        $courseNames = [
            'بيداغوجيا التعلم والتعليم - 1',
            'بيداغوجيا التعلم والتعليم - 2',
            'أساليب تدريس المبحث - 1',
            'أساليب تدريس المبحث - 2',
            'الخبرة المدرسية - 1',
            'الخبرة المدرسية - 2',
        ];

        // التقييم الافتراضي 5 نجوم (تلتقطه الواجهة لاختيار النجوم مباشرة)
        $defaultRating = 5;

        // إحصائيات العرض (اختياري)
        try {
            if (class_exists(\App\Models\Tool::class) && class_exists(\App\Models\ToolStat::class)) {
                $tool = \App\Models\Tool::where('slug', 'assignment-form-generator')->first();
                if ($tool) {
                    \App\Models\ToolStat::firstOrCreate(['tool_id'=>$tool->id]);
                }
            }
        } catch (\Throwable $e) {}

        return view('tools.assignment-form', compact('years','universities','taskTypes','courseNames','defaultRating'));
    }

    /* ===================== تقييم الأداة ===================== */
    public function rate(Request $request)
    {
        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
        ]);

        $tool = \App\Models\Tool::where('slug', 'assignment-form-generator')->first();
        if (!$tool) {
            return response()->json(['ok' => false, 'msg' => 'الأداة غير موجودة'], 404);
        }

        \App\Models\ToolStat::firstOrCreate(['tool_id' => $tool->id]);

        DB::table('tool_stats')->where('tool_id', $tool->id)->update([
            'rating_avg'   => DB::raw('(rating_avg * rating_count + '.(int)$data['rating'].') / (rating_count + 1)'),
            'rating_count' => DB::raw('rating_count + 1'),
            'updated_at'   => now(),
        ]);

        $row = DB::table('tool_stats')->where('tool_id', $tool->id)->first();

        // كوكي لمنع تكرار التقييم
        $cookie = Cookie::make('rated_tool_assignform', '1', 60*24*365);

        return response()->json([
            'ok'           => true,
            'rating_avg'   => round((float)$row->rating_avg, 2),
            'rating_count' => (int)$row->rating_count,
        ])->cookie($cookie);
    }

    /* ===================== فتح القالب داخل iframe ===================== */
    public function template(Request $request)
    {
        $path = config('assignment_templates.pdf_path');

        if (!$path || !is_file($path)) {
            $publicPath = public_path('templates/assignment/all-universities.pdf');
            if (is_file($publicPath)) {
                $path = $publicPath;
            } else {
                abort(404, 'ملف القالب غير موجود: ' . ($path ?: '(config فارغ)') . ' | ' . $publicPath);
            }
        }

        return response()->file($path, [
            'Content-Type'    => 'application/pdf',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    /* ===================== معاينة صفحة جامعة محددة ===================== */
    public function preview(Request $request)
    {
        $uni = $request->query('university'); // jordan|yarmouk|mutah|hashemite

        $cfg = config('assignment_templates');
        $map = $cfg['universities'][$uni] ?? null;

        if (!$map) {
            abort(404, 'جامعة غير معروفة للمعاينة.');
        }

        $candidates = [
            $cfg['pdf_path'] ?? null,
            storage_path('app/templates/assignment/all-universities.pdf'),
            base_path('app/templates/assignment/all-universities.pdf'),
            public_path('templates/assignment/all-universities.pdf'),
        ];

        $src = null;
        foreach ($candidates as $p) {
            if ($p && is_file($p)) { $src = $p; break; }
        }
        if (!$src) {
            abort(404, 'ملف القالب غير موجود للمعاينة.');
        }

        $pageNo = (int)($map['page'] ?? 1);

        $pdf = new Fpdi('P','mm');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->setRTL(true);

        $pageCount = $pdf->setSourceFile($src);
        if ($pageNo < 1 || $pageNo > $pageCount) {
            abort(404, 'رقم صفحة غير صالح.');
        }

        $tplIdx = $pdf->importPage($pageNo);
        $size   = $pdf->getTemplateSize($tplIdx);

        $pdf->AddPage($size['orientation'] ?? 'P', [$size['width'], $size['height']]);
        $pdf->useTemplate($tplIdx, 0, 0, $size['width'], $size['height'], true);

        $binary = $pdf->Output('preview.pdf', 'S');

        return response($binary, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview.pdf"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'              => 'no-cache',
        ]);
    }

    /* ===================== الختم والتنزيل ===================== */
public function stamp(Request $request)
{
    $request->validate([
        'university'       => 'required|string|in:jordan,yarmouk,mutah,hashemite',
        'student_name'     => 'required|string|max:190',
        'student_id'       => 'required|string|max:50',
        'specialty'        => 'required|string|max:190',
        'course_teacher'   => 'required|string|max:190',
        'academic_advisor' => 'required|string|max:190',
        'course_name'      => 'required|string|max:190', // DDL
        'task_type'        => 'required|string|in:مهمة قبلية,مهمة بعدية',
        'date'             => 'required|string|max:50',
        'answer_html'      => 'nullable|string',
    ], [], [
        'university'       => 'الجامعة',
        'student_name'     => 'اسم الطالب/المعلم',
        'student_id'       => 'الرقم الجامعي',
        'specialty'        => 'التخصص',
        'course_teacher'   => 'اسم مدرس المادة',
        'academic_advisor' => 'اسم المرشد الأكاديمي',
        'course_name'      => 'اسم المادة',
        'task_type'        => 'نوع المهمة',
        'date'             => 'اليوم والتاريخ',
        'answer_html'      => 'نص الإجابة',
    ]);

    $uniKey = $request->string('university')->toString();

    // ===== 1) تنقية + تطبيع HTML TinyMCE إلى صيغة آمنة ومدعومة من TCPDF =====
    $rawHtml = (string) $request->input('answer_html', '');

    $allowedTags =
          '<b><strong><i><em><u>'
        . '<ul><ol><li>'
        . '<p><br><hr><blockquote>'
        . '<h1><h2><h3><h4><h5><h6>'
        . '<table><thead><tbody><tfoot><tr><td><th><colgroup><col>'
        . '<img><a><figure><figcaption>'
        . '<div><span><sup><sub>';

    $html = strip_tags($rawHtml, $allowedTags);

    // تطبيع السمات/العناصر بأسلوب حذر
    $answerHtml = $this->normalizeHtmlForTcpdf($html);

    $data = $request->only([
        'student_name','student_id','specialty','course_teacher',
        'academic_advisor','course_name','task_type','date'
    ]);
    $data['answer_html'] = $answerHtml;

    $cfg = config('assignment_templates');
    $map = $cfg['universities'][$uniKey] ?? null;

    if (!$map) {
        \Log::error('[AssignmentStamp] Unknown university key', ['uni'=>$uniKey]);
        abort(400, 'جامعة غير معروفة.');
    }

    // ===== 2) العثور على القالب (نفس منطق الباجات/المعاينة) =====
    $candidates = [
        $cfg['pdf_path'] ?? null,
        storage_path('app/templates/assignment/all-universities.pdf'),
        base_path('app/templates/assignment/all-universities.pdf'),
        public_path('templates/assignment/all-universities.pdf'),
    ];
    $pdfPath = null;
    foreach ($candidates as $p) {
        if ($p && is_file($p)) { $pdfPath = $p; break; }
    }
    if (!$pdfPath) {
        \Log::error('[AssignmentStamp] Template not found in candidates', ['candidates' => $candidates]);
        abort(400, 'تعذّر فتح القالب: لم يتم العثور على ملف PDF في أي من المسارات المتوقعة.');
    }

    $pageNo = (int) ($map['page'] ?? 1);
    $slots  = $map['slots'] ?? [];

    // ===== 3) الخطوط =====
    $fontsDir    = storage_path('app/tcpdf-fonts');
    $vendorFonts = base_path('vendor/tecnickcom/tcpdf/fonts');
    if (!is_dir($fontsDir)) @mkdir($fontsDir, 0775, true);
    if (!defined('K_PATH_FONTS')) define('K_PATH_FONTS', rtrim($fontsDir,'/\\').DIRECTORY_SEPARATOR);

    foreach (['dejavusans','dejavusansb','dejavusansi','dejavusansbi'] as $fam) {
        foreach (['.php','.z'] as $ext) {
            $src = $vendorFonts.DIRECTORY_SEPARATOR.$fam.$ext;
            $dst = $fontsDir.DIRECTORY_SEPARATOR.$fam.$ext;
            if (!is_file($dst) && is_file($src)) @copy($src, $dst);
        }
    }

    $searchDirs = [base_path('app/fonts'), storage_path('app/fonts'), public_path('app/fonts')];
    $pickFile = function (array $dirs, array $names) {
        foreach ($dirs as $d) {
            if (!is_dir($d)) continue;
            foreach ($names as $n) {
                $p = rtrim($d, '/\\') . DIRECTORY_SEPARATOR . $n;
                if (is_file($p)) return $p;
            }
        }
        return null;
    };

    $regPath  = $pickFile($searchDirs, [$cfg['font']['regular'] ?? 'Amiri-Regular.ttf','Amiri-Regular.ttf','Amiri.ttf','NotoNaskhArabic-Regular.ttf','Almarai-Regular.ttf']);
    $boldPath = $pickFile($searchDirs, [$cfg['font']['bold']    ?? 'Amiri-Bold.ttf','Amiri-Bold.ttf','NotoNaskhArabic-Bold.ttf','Almarai-Bold.ttf']);

    $addFont = function (?string $path) { if (!$path || !is_file($path)) return null; try { return \TCPDF_FONTS::addTTFfont($path,'TrueTypeUnicode','',0); } catch (\Throwable $e) { return null; } };
    $fontRegular = $addFont($regPath) ?: (is_file(K_PATH_FONTS.'dejavusans.php') ? 'dejavusans' : 'aealarabiya');
    $fontBold    = $addFont($boldPath) ?: $fontRegular;

    // ===== 4) إعداد TCPDF/FPDI =====
    $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->setRTL(true);
    $pdf->setLanguageArray(['a_meta_charset'=>'UTF-8','a_meta_dir'=>'rtl','a_meta_language'=>'ar','w_page'=>'صفحة']);
    $pdf->setFontSubsetting(false);

    // هوامش ومقياس صور ونسبة ارتفاع أسطر لتقليل التراكب
    $pdf->SetMargins(15, 18, 15);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->setCellPaddings(1.2, 1.2, 1.2, 1.2);
    $pdf->setImageScale(1.25);
    $pdf->setCellHeightRatio(1.35);

    try {
        $pageCount = $pdf->setSourceFile($pdfPath);
    } catch (\Throwable $e) {
        \Log::error('[AssignmentStamp] setSourceFile failed', ['path'=>$pdfPath,'message'=>$e->getMessage()]);
        abort(400, 'تعذّر فتح القالب.');
    }
    if ($pageNo < 1 || $pageNo > $pageCount) {
        abort(400, 'رقم الصفحة غير صالح.');
    }

    $tplIdx = $pdf->importPage($pageNo);
    $size   = $pdf->getTemplateSize($tplIdx);
    $pdf->AddPage($size['orientation'] ?? 'P', [$size['width'], $size['height']]);
    $pdf->useTemplate($tplIdx, 0, 0, $size['width'], $size['height'], true);

    $M = $pdf->getMargins();
    $innerW = $pdf->getPageWidth() - $M['left'] - $M['right'];

    $defaultSize  = (float)($cfg['font']['size']  ?? 12.0);
    $defaultColor = (string)($cfg['font']['color'] ?? '#111111');
    $toRGB = function ($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) $hex = "{$hex[0]}{$hex[0]}{$hex[1]}{$hex[1]}{$hex[2]}{$hex[2]}";
        $int = hexdec($hex);
        return [($int >> 16) & 255, ($int >> 8) & 255, $int & 255];
    };

    // ===== 5) الكتابة =====
    foreach (($slots ?? []) as $slot) {
        foreach ($slot as $f) {
            $key = $f['key'] ?? null;
            if (!$key || !array_key_exists($key, $data)) continue;

            $x     = (float)($f['x'] ?? 0);
            $y     = (float)($f['y'] ?? 0);
            $w     = (float)($f['w'] ?? 0);
            $align = strtoupper($f['align'] ?? 'R');
            $sizeF = (float)($f['size']  ?? $defaultSize);
            $color = $f['color'] ?? $defaultColor;
            $bold  = !empty($f['bold']);

            [$r,$g,$b] = $toRGB($color);
            $pdf->SetTextColor($r,$g,$b);

            if ($key === 'answer_html') {
                // اكتب ضمن الهوامش وبمحاذاة مناسبة
                $startY = max($y, $M['top']);
                $pdf->SetFont($fontRegular, '', $sizeF, '', false);
                $pdf->SetXY($M['left'], $startY);

                // غلاف بسيط يفرض RTL ومحاذاة يمين وهوامش فقرة داخلية
                $htmlBlock =
                    '<div dir="rtl" style="text-align:right; line-height:1.8;">'
                  . $data['answer_html']
                  . '</div>';

                $pdf->writeHTMLCell(
                    $innerW, 0, $M['left'], $pdf->GetY(),
                    $htmlBlock, 0, 1, false, true, 'J', true
                );
            } else {
                $pdf->SetFont($bold ? $fontBold : $fontRegular, '', $sizeF, '', false);
                // نستخدم عرض آمن إن لم يُحدَّد
                $wSafe = $w ?: $innerW;
                $pdf->SetXY($x, $y);
                $pdf->MultiCell(
                    $wSafe, 0, $data[$key],
                    0, $align, false, 1, '', '', true, 0, false, true, 0, 'T', true
                );
            }
        }
    }

    // ===== 6) إحصائيات الاستخدام =====
    try {
        if (class_exists(\App\Models\Tool::class) && class_exists(\App\Models\ToolStat::class)) {
            $tool = \App\Models\Tool::where('slug', 'assignment-form-generator')->first();
            if ($tool) {
                \App\Models\ToolStat::firstOrCreate(['tool_id'=>$tool->id]);
                \DB::table('tool_stats')->where('tool_id',$tool->id)->update([
                    'uses_count'=>\DB::raw('uses_count + 1'),
                    'last_used_at'=>now(),
                ]);

                $cookieKey = 'tool_'.$tool->id.'_seen';
                if (!request()->cookies->has($cookieKey)) {
                    \DB::table('tool_stats')->where('tool_id',$tool->id)->update([
                        'unique_users_count'=>\DB::raw('unique_users_count + 1'),
                    ]);
                    cookie()->queue(cookie()->make($cookieKey, '1', 60*24*30, null, null, false, false, false, 'Lax'));
                }
            }
        }
    } catch (\Throwable $e) {
        \Log::error('[AssignmentStamp] Tool stats failed', ['message'=>$e->getMessage()]);
    }

    $file = 'Task-Form-'.\Illuminate\Support\Str::slug($data['student_name']).'-'.$uniKey.'.pdf';

    return response($pdf->Output($file, 'S'), 200, [
        'Content-Type'        => 'application/pdf',
        'Content-Disposition' => "attachment; filename=\"$file\"",
    ]);
}

private function normalizeHtmlForTcpdf(string $html): string
{
    if (trim($html) === '') return '';

    $dom = new \DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new \DOMXPath($dom);

    // 1) استبدال figure/figcaption بعناصر مدعومة
    foreach ($xpath->query('//figure') as $fig) {
        $wrapper = $dom->createElement('div');
        while ($fig->firstChild) $wrapper->appendChild($fig->firstChild);
        $fig->parentNode->replaceChild($wrapper, $fig);
    }
    foreach ($xpath->query('//figcaption') as $cap) {
        $p = $dom->createElement('p');
        $strong = $dom->createElement('strong');
        $strong->appendChild($cap->firstChild ? $cap->firstChild : $dom->createTextNode(''));
        $p->appendChild($strong);
        $cap->parentNode->replaceChild($p, $cap);
    }

    // 2) تحويل العناوين إلى فقرات بخط عريض
    foreach (['h1','h2','h3','h4','h5','h6'] as $h) {
        foreach ($xpath->query("//{$h}") as $el) {
            $p = $dom->createElement('p');
            $strong = $dom->createElement('strong');
            while ($el->firstChild) $strong->appendChild($el->firstChild);
            $p->appendChild($strong);
            $el->parentNode->replaceChild($p, $el);
        }
    }

    // 3) مصفاة style
    $safeStyle = function(string $style): string {
        $out = [];
        foreach (explode(';',$style) as $decl) {
            if (!$decl) continue;
            [$k,$v] = array_map('trim', array_pad(explode(':',$decl,2),2,''));
            $k = strtolower($k);
            if ($k === 'text-align' && in_array($v, ['right','left','center','justify'], true)) $out[]="$k:$v";
            if ($k === 'direction'  && in_array($v, ['rtl','ltr'], true))                           $out[]="$k:$v";
            if ($k === 'line-height' && preg_match('/^\d+(\.\d+)?(px|em|rem|%)?$/',$v))             $out[]="$k:$v";
            if ($k === 'width'       && preg_match('/^\d+(\.\d+)?(px|%)$/',$v))                     $out[]="$k:$v";
            if ($k === 'height'      && preg_match('/^\d+(\.\d+)?(px|%)$/',$v))                     $out[]="$k:$v";
            if ($k === 'border'      && preg_match('/^\d+px( solid)?( #[0-9a-fA-F]{3,6})?$/',$v))   $out[]="$k:$v";
            if ($k === 'border-collapse' && in_array(strtolower($v),['collapse','separate'],true))  $out[]="$k:$v";
            // نتجاهل padding/margin لتقليل التمدد
        }
        return implode(';', $out);
    };

    // 4) إعادة بناء السمات بطريقة "التقاط ثم تصفية"
    foreach ($xpath->query('//*') as $el) {
        $tag = strtolower($el->nodeName);

        // التقط السمات أولاً
        $orig = [];
        if ($el->hasAttributes()) {
            foreach (iterator_to_array($el->attributes) as $attr) {
                $orig[strtolower($attr->nodeName)] = $attr->nodeValue;
            }
        }

        // أزلها كلها
        while ($el->attributes->length) {
            $el->removeAttribute($el->attributes->item(0)->nodeName);
        }

        // أعد فقط المسموح بها لكل نوع عنصر بالاعتماد على $orig
        if ($tag === 'a') {
            $href = $orig['href'] ?? '';
            if ($href && preg_match('#^(https?:|mailto:)#i', $href)) $el->setAttribute('href', $href);
        } elseif ($tag === 'img') {
            $src = $orig['src'] ?? '';
            // نسمح بصور data: أو http(s)
            if ($src && preg_match('#^(data:image/[^;]+;base64,|https?://)#i', $src)) {
                $el->setAttribute('src', $src);
            }
            // أعد الأبعاد الرقمية فقط
            foreach (['width','height'] as $dim) {
                $val = $orig[$dim] ?? '';
                if (preg_match('/^\d+$/', $val)) $el->setAttribute($dim, $val);
            }
            // (اختياري) alt لو موجود
            if (!empty($orig['alt'])) $el->setAttribute('alt', $orig['alt']);
        } elseif (in_array($tag, ['table','thead','tbody','tfoot','tr','td','th','colgroup','col'], true)) {
            foreach (['rowspan','colspan'] as $att) {
                $n = isset($orig[$att]) ? (int)$orig[$att] : 0;
                if ($n > 0 && $n < 100) $el->setAttribute($att, (string)$n);
            }
            if ($tag === 'table') {
                $el->setAttribute('border', '1');
                $el->setAttribute('cellpadding', '4');
                $el->setAttribute('cellspacing', '0');
            }
        }

        // style (منظَّف)
        $style = $orig['style'] ?? '';
        if ($style) {
            $styleClean = $safeStyle($style);
            if ($styleClean !== '') $el->setAttribute('style', $styleClean);
        }
    }

    // 5) تفريغ span غير المفيد
    foreach ($xpath->query('//span') as $sp) {
        if (!$sp->hasAttributes()) {
            $frag = $dom->createDocumentFragment();
            while ($sp->firstChild) $frag->appendChild($sp->firstChild);
            $sp->parentNode->replaceChild($frag, $sp);
        }
    }

    // 6) إزالة الفقرات الفارغة
    foreach ($xpath->query('//p') as $p) {
        $txt = trim(preg_replace('/\x{00A0}/u', ' ', $p->textContent ?? ''));
        if ($txt === '') {
            $br = $dom->createElement('br');
            $p->parentNode->replaceChild($br, $p);
        }
    }

    $out = $dom->saveHTML();
    $out = preg_replace('/(?:<br\s*\/?>\s*){3,}/i', '<br><br>', $out);
    $out = preg_replace('/\x{00A0}/u', ' ', $out);

    return $out;
}

}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use setasign\Fpdi\Tcpdf\Fpdi;

class AssignmentStampController extends Controller
{
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

    // 1) استبدال figure/figcaption
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

    // 2) تحويل العناوين الكبيرة إلى فقرات بولد (TCPDF أحيانًا يبالغ في المسافات)
    foreach (['h1','h2','h3','h4','h5','h6'] as $h) {
        foreach ($xpath->query("//{$h}") as $el) {
            $p = $dom->createElement('p');
            $strong = $dom->createElement('strong');
            while ($el->firstChild) $strong->appendChild($el->firstChild);
            $p->appendChild($strong);
            $el->parentNode->replaceChild($p, $el);
        }
    }

    // 3) إزالة السمات غير الآمنة + تصفية style
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
            if ($k === 'padding' || $k==='margin') { /* تجاهل لتفادي تمدد زائد */ }
        }
        return implode(';', $out);
    };

    foreach ($xpath->query('//*') as $el) {
        $tag = strtolower($el->nodeName);

        // احذف كل السمات أولاً
        while ($el->attributes->length) {
            $el->removeAttribute($el->attributes->item(0)->nodeName);
        }

        // أعد فقط السمات المسموحة لكل نوع
        if ($tag === 'a') {
            // href http/https/mailto فقط
            $href = $el->getAttribute('href');
            if ($href && preg_match('#^(https?:|mailto:)#i', $href)) $el->setAttribute('href',$href);
        } elseif ($tag === 'img') {
            // data:image أو http(s)
            $src = $el->getAttribute('src');
            if ($src && preg_match('#^(data:image/[^;]+;base64,|https?://)#i', $src)) $el->setAttribute('src',$src);

            foreach (['width','height'] as $dim) {
                $val = $el->getAttribute($dim);
                if (preg_match('/^\d+$/', $val)) $el->setAttribute($dim, $val);
            }
        } elseif (in_array($tag, ['table','thead','tbody','tfoot','tr','td','th','colgroup','col'], true)) {
            foreach (['rowspan','colspan'] as $att) {
                $n = (int) $el->getAttribute($att);
                if ($n > 0 && $n < 100) $el->setAttribute($att, (string)$n);
            }
            // حدود الجداول الافتراضية
            if ($tag === 'table') {
                $el->setAttribute('border', '1');
                $el->setAttribute('cellpadding', '4');
                $el->setAttribute('cellspacing', '0');
            }
        }

        // style (مصفى)
        $style = $el->getAttribute('style');
        if ($style) {
            $styleClean = $safeStyle($style);
            if ($styleClean !== '') $el->setAttribute('style', $styleClean);
        }
    }

    // 4) تفريغ span لا يحتوي تنسيق مفيد
    foreach ($xpath->query('//span') as $sp) {
        if (!$sp->hasAttributes()) {
            $frag = $dom->createDocumentFragment();
            while ($sp->firstChild) $frag->appendChild($sp->firstChild);
            $sp->parentNode->replaceChild($frag, $sp);
        }
    }

    // 5) إزالة الفقرات الفارغة/المسافات الزائدة
    foreach ($xpath->query('//p') as $p) {
        $txt = trim(preg_replace('/\x{00A0}/u', ' ', $p->textContent ?? ''));
        if ($txt === '') {
            // اسمح بحد أقصى <br> واحد بدل فقرات فارغة متراكمة
            $br = $dom->createElement('br');
            $p->parentNode->replaceChild($br, $p);
        }
    }

    $out = $dom->saveHTML();

    // تنظيف نهائي بسيط
    $out = preg_replace('/(?:<br\s*\/?>\s*){3,}/i', '<br><br>', $out); // لا أكثر من سطرين فارغين
    $out = preg_replace('/\x{00A0}/u', ' ', $out); // nbsp -> space

    return $out;
}


}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use setasign\Fpdi\Tcpdf\Fpdi;

class PdfStampController extends Controller
{
public function preview(Request $request)
{
    $uni = $request->query('university'); // jordan|yarmouk|mutah|hashemite

    $cfg  = config('idcard_templates');
    $map  = $cfg['universities'][$uni] ?? null;

    if (!$map) {
        abort(404, 'جامعة غير معروفة للمعاينة.');
    }

    // جرّب أكثر من مسار كما فعلنا سابقاً
    $candidates = [
        $cfg['pdf_path'] ?? null,
        storage_path('app/templates/idcard/all-universities.pdf'),
        base_path('app/templates/idcard/all-universities.pdf'),
        public_path('templates/idcard/all-universities.pdf'),
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

    // معاينة القالب داخل iframe (نفس الملف، والـ #page=N يعالجه المتصفح)
   public function template(Request $request)
{
    // 1) جرّب من config (Storage)
    $path = config('idcard_templates.pdf_path');

    // 2) لو مش موجود، جرّب نسخة Public
    if (!$path || !is_file($path)) {
        $publicPath = public_path('templates/idcard/all-universities.pdf');
        if (is_file($publicPath)) {
            $path = $publicPath;
        } else {
            // للتشخيص: بيّن المسارين اللذَين تم فحصهما
            abort(404, 'ملف القالب غير موجود: '
                . ($path ?: '(config فارغ)') . ' | '
                . $publicPath);
        }
    }

    return response()->file($path, [
        'Content-Type'    => 'application/pdf',
        'X-Frame-Options' => 'SAMEORIGIN',
    ]);
}
public function stamp(\Illuminate\Http\Request $request)
{
    $request->validate([
        'university' => 'required|string|in:jordan,yarmouk,mutah,hashemite',
        'name'       => 'required|string|max:190',
        'major'      => 'nullable|string|max:190',
        'year'       => 'required|string|max:20',
    ], [], [
        'university' => 'الجامعة',
        'name'       => 'الاسم',
        'major'      => 'التخصص',
        'year'       => 'السنة الأكاديمية',
    ]);

    $uniKey = $request->string('university')->toString();
    $name   = trim($request->input('name'));
    $major  = trim($request->input('major', '—'));
    $year   = trim($request->input('year', '2025/2026'));

    \Log::info('[PdfStamp] Start stamp()', ['uni'=>$uniKey,'name'=>$name,'year'=>$year]);

    $cfg     = config('idcard_templates');
    $pdfPath = $cfg['pdf_path'] ?? null;
    $map     = $cfg['universities'][$uniKey] ?? null;

    if (!$pdfPath || !is_file($pdfPath) || !$map) {
        \Log::error('[PdfStamp] Invalid template config', [
            'pdfPath'=>$pdfPath, 'exists'=>$pdfPath ? is_file($pdfPath) : null, 'mapSet'=>(bool)$map,
        ]);
        abort(400, 'إعداد القوالب غير صحيح.');
    }

    $pageNo = (int) ($map['page'] ?? 1);
    $slots  = $map['slots'] ?? [];

    // 1) ثبت مجلد تعريفات الخطوط وازرع خطوط TCPDF الأساسية (مرة واحدة)
    $fontsDir    = storage_path('app/tcpdf-fonts');
    $vendorFonts = base_path('vendor/tecnickcom/tcpdf/fonts');
    if (!is_dir($fontsDir)) @mkdir($fontsDir, 0775, true);

    $coreSet = [
        'helvetica','helveticab','helveticai','helveticabi',
        'courier','courierb','courieri','courierbi',
        'times','timesb','timesi','timesbi',
        'dejavusans','dejavusansb','dejavusansi','dejavusansbi', // مفيد عربيًا أيضًا
        'symbol','zapfdingbats'
    ];
    foreach ($coreSet as $fam) {
        foreach (['.php', '.z'] as $ext) {
            $src = rtrim($vendorFonts,'/\\').DIRECTORY_SEPARATOR.$fam.$ext;
            $dst = rtrim($fontsDir,'/\\').DIRECTORY_SEPARATOR.$fam.$ext;
            if (!is_file($dst) && is_file($src)) @copy($src, $dst);
        }
    }

    if (!defined('K_PATH_FONTS')) {
        define('K_PATH_FONTS', rtrim($fontsDir, '/\\') . DIRECTORY_SEPARATOR);
    }
    \Log::info('[PdfStamp] K_PATH_FONTS', ['dir'=>K_PATH_FONTS,'writable'=>is_writable(K_PATH_FONTS)]);

    // 2) حضّر TCPDF/FPDI
    $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->setRTL(true);
    $pdf->setLanguageArray(['a_meta_charset'=>'UTF-8','a_meta_dir'=>'rtl','a_meta_language'=>'ar','w_page'=>'صفحة']);

    // أوقف التقطيع مؤقتًا (هذا المفتاح غالبًا يوقف ظاهرة "خ خ خ")
    $pdf->setFontSubsetting(false);

    // 3) العثور على ملفات TTF
    $searchDirs = [
        base_path('app/fonts'),
        storage_path('app/fonts'),
        public_path('app/fonts'),
    ];
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

    // أعطِ أولوية لـ ScheherazadeNew
    $candidatesRegular = [
        'Amiri-Regular.ttf',
        'Amiri-Regular.ttf','Amiri.ttf',
        'NotoNaskhArabic-Regular.ttf','NotoKufiArabic-Regular.ttf',
        'Almarai-Regular.ttf',
        'louts_shamy_Regular.ttf','louts-shamy-regular.ttf',
    ];
    $candidatesBold = [
        'Amiri-Bold.ttf',
        'Amiri-Bold.ttf',
        'NotoNaskhArabic-Bold.ttf','NotoKufiArabic-Bold.ttf',
        'Almarai-Bold.ttf',
        'louts_shamy_Bold.ttf','louts-shamy-bold.ttf',
    ];

    $regPath  = $pickFile($searchDirs, $candidatesRegular);
    $boldPath = $pickFile($searchDirs, $candidatesBold);

    // 4) إعدادات خط افتراضي من config (اختياري)
    $defaultSize  = 13.0;
    $defaultColor = '#111111';
    if (!empty($map['font']) && is_array($map['font'])) {
        $f = $map['font'];
        if (!empty($f['regular'])) { $fp = $pickFile($searchDirs, [$f['regular']]); if ($fp) $regPath = $fp; }
        if (!empty($f['bold']))    { $fp = $pickFile($searchDirs, [$f['bold']]);   if ($fp) $boldPath = $fp; }
        if (!empty($f['size']))    { $defaultSize  = (float)$f['size']; }
        if (!empty($f['color']))   { $defaultColor = $f['color']; }
    }

    \Log::info('[PdfStamp] Font files chosen', ['regular_path'=>$regPath,'bold_path'=>$boldPath]);

    // 5) دوال التسجيل (ابتلاع التحذيرات) + تجارب متعددة
    $silentAdd = function (?string $path, int $flags, int $plat = 3, int $enc = 1) {
        if (!$path || !is_file($path)) return null;
        $prevReporting = error_reporting();
        $prevHandler   = set_error_handler(function () { return true; });
        error_reporting($prevReporting & ~E_WARNING & ~E_NOTICE);
        try {
            $res = \TCPDF_FONTS::addTTFfont($path, 'TrueTypeUnicode', '', $flags, K_PATH_FONTS, $plat, $enc);
        } catch (\Throwable $e) {
            \Log::warning('[PdfStamp] addTTFfont exception (silenced)', [
                'src'=>$path,'flags'=>$flags,'plat'=>$plat,'enc'=>$enc,'msg'=>$e->getMessage()
            ]);
            $res = null;
        } finally {
            error_reporting($prevReporting);
            restore_error_handler();
        }
        return !empty($res) ? $res : null;
    };
    $tryRegister = function (?string $path) use ($silentAdd) {
        if (!$path || !is_file($path)) return null;
        foreach ([[0,3,1],[32,3,1],[0,3,10],[32,3,10]] as [$flags,$plat,$enc]) {
            if ($res = $silentAdd($path, $flags, $plat, $enc)) return $res;
        }
        return null;
    };

    // 6) سجّل الخطوط
    $fontRegular = $tryRegister($regPath);
    $fontBold    = $tryRegister($boldPath);

    // بدائل موثوقة
    if (!$fontRegular) $fontRegular = $tryRegister($pickFile($searchDirs, ['Amiri-Regular.ttf','Amiri.ttf','NotoNaskhArabic-Regular.ttf'])) ?: null;
    if (!$fontBold)    $fontBold    = $tryRegister($pickFile($searchDirs, ['Amiri-Bold.ttf','NotoNaskhArabic-Bold.ttf'])) ?: null;

    // تحقق أن ملفات التعريف اتولدت فعلاً؛ وإلا استخدم dejavu/aealarabiya
    $assertFont = function (string $family): bool {
        $php = K_PATH_FONTS . $family . '.php';
        return is_file($php);
    };

    if (!$fontRegular || !$assertFont($fontRegular)) {
        \Log::warning('[PdfStamp] Regular font missing/invalid, falling back.');
        $fontRegular = is_file(K_PATH_FONTS.'dejavusans.php') ? 'dejavusans' : 'aealarabiya';
    }
    if (!$fontBold || !$assertFont($fontBold)) {
        \Log::warning('[PdfStamp] Bold font missing/invalid, falling back to regular.');
        $fontBold = $fontRegular;
    }

    \Log::info('[PdfStamp] Final chosen fonts', ['regular'=>$fontRegular,'bold'=>$fontBold]);

    // 7) افتح القالب
    try {
        $pageCount = $pdf->setSourceFile($pdfPath);
        \Log::info('[PdfStamp] Template pages', ['count'=>$pageCount, 'pageNo'=>$pageNo]);
    } catch (\Throwable $e) {
        \Log::error('[PdfStamp] setSourceFile failed', ['path'=>$pdfPath,'message'=>$e->getMessage()]);
        abort(400, 'تعذّر فتح القالب.');
    }
    if ($pageNo < 1 || $pageNo > $pageCount) {
        \Log::error('[PdfStamp] Invalid page number', ['pageNo'=>$pageNo, 'count'=>$pageCount]);
        abort(400, 'رقم الصفحة غير صالح.');
    }

    $tplIdx = $pdf->importPage($pageNo);
    $size   = $pdf->getTemplateSize($tplIdx);
    $pdf->AddPage($size['orientation'] ?? 'P', [$size['width'], $size['height']]);
    $pdf->useTemplate($tplIdx, 0, 0, $size['width'], $size['height'], true);

    // HEX→RGB
    $toRGB = function ($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) $hex = "{$hex[0]}{$hex[0]}{$hex[1]}{$hex[1]}{$hex[2]}{$hex[2]}";
        $int = hexdec($hex);
        return [($int >> 16) & 255, ($int >> 8) & 255, $int & 255];
    };

    $data = ['name'=>$name, 'major'=>$major, 'year'=>$year];

    // 8) الكتابة على السلوْتس — انتبه: SetFont آخر بارامتر subset=false
    $written = 0;
    foreach ($slots as $slotIndex => $slot) {
        foreach ($slot as $fIndex => $f) {
            $key = $f['key'] ?? null;
            if (!$key || !array_key_exists($key, $data)) continue;

            $x     = (float)($f['x'] ?? 0);
            $y     = (float)($f['y'] ?? 0);
            $w     = (float)($f['w'] ?? 0);
            $align = strtoupper($f['align'] ?? 'R'); // R|C|L
            $sizeF = (float)($f['size']  ?? $defaultSize);
            $color = $f['color'] ?? $defaultColor;
            $bold  = !empty($f['bold']);

            [$r, $g, $b] = $toRGB($color);
            $pdf->SetTextColor($r, $g, $b);

            // subset=false يجنّبنا خرائط خاطئة مع بعض الخطوط
            $pdf->SetFont($bold ? $fontBold : $fontRegular, '', $sizeF, '', false);

            $pdf->SetXY($x, $y);
            $pdf->MultiCell(
                $w, 0, $data[$key],
                0, $align, false, 1, '', '', true, 0, false, true, 0, 'T', true
            );

            $written++;
            if ($written <= 3) {
                \Log::debug('[PdfStamp] Write sample', [
                    'slot'=>$slotIndex.':'.$fIndex, 'key'=>$key,
                    'value'=>mb_substr($data[$key], 0, 50),
                    'x'=>$x,'y'=>$y,'w'=>$w,'size'=>$sizeF,'bold'=>$bold,'align'=>$align
                ]);
            }
        }
    }
    \Log::info('[PdfStamp] Finished writing slots', ['count'=>$written]);

// ===== إحصائيات الاستخدام =====
try {
    if (class_exists(\App\Models\Tool::class) && class_exists(\App\Models\ToolStat::class)) {
        $tool = \App\Models\Tool::where('slug', 'id-card-generator')->first();
        if ($tool) {
            \App\Models\ToolStat::firstOrCreate(['tool_id' => $tool->id]);

            \DB::table('tool_stats')->where('tool_id', $tool->id)->update([
                'uses_count'   => \DB::raw('uses_count + 1'),
                'last_used_at' => now(),
            ]);

            // اسم كوكي موحّد للمستخدم الفريد حسب tool_id
            $cookieKey = 'tool_'.$tool->id.'_seen';
            if (!request()->cookies->has($cookieKey)) {
                \DB::table('tool_stats')->where('tool_id', $tool->id)->update([
                    'unique_users_count' => \DB::raw('unique_users_count + 1'),
                ]);
                cookie()->queue(cookie()->make($cookieKey, '1', 60*24*30, null, null, false, false, false, 'Lax'));
            }
        }
    }
} catch (\Throwable $e) {
    \Log::error('[PdfStamp] Tool stats failed', ['message'=>$e->getMessage()]);
}


    $file = 'ID-' . \Illuminate\Support\Str::slug($name ?: 'student')
          . '-' . \Illuminate\Support\Str::slug($year)
          . '-' . $uniKey . '.pdf';

    \Log::info('[PdfStamp] Output PDF', ['filename'=>$file]);

    return response($pdf->Output($file, 'S'), 200, [
        'Content-Type'        => 'application/pdf',
        'Content-Disposition' => "attachment; filename=\"$file\"",
    ]);
}

}

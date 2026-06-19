<?php
namespace App\Services;
use App\Models\FormTemplate;
use App\Models\TemplatePdfBinding;
use App\Models\AssignmentPdfSlot;
use App\Support\TcpdfHtmlNormalizer;
use setasign\Fpdi\Tcpdf\Fpdi;
use TCPDF_FONTS;

class PdfStampService {
  use TcpdfHtmlNormalizer;

  public function render(FormTemplate $tpl, array $payload): string {
    $ctx = $payload['ctx'] ?? [];
    $values = $payload['values'] ?? [];

    $binding = TemplatePdfBinding::bestFor($tpl->id, $ctx);
    abort_unless($binding, 400, 'لا يوجد ربط PDF للقالب.');

    $pdfTpl = $binding->pdfTemplate;
    $pageNo = $binding->page ?: $pdfTpl->default_page;

    $pdfPathCandidates = [
      base_path($pdfTpl->file_path),
      storage_path(str_starts_with($pdfTpl->file_path,'app/') ? $pdfTpl->file_path : 'app/'.$pdfTpl->file_path),
      public_path($pdfTpl->file_path),
      $pdfTpl->file_path,
    ];
    $src=null; foreach ($pdfPathCandidates as $p){ if($p && is_file($p)){ $src=$p; break; } }
    abort_unless($src, 400, 'تعذّر فتح ملف القالب PDF.');

    // إعداد الخطوط الأساسية
    $fontsDir = storage_path('app/tcpdf-fonts');
    if (!is_dir($fontsDir)) @mkdir($fontsDir, 0775, true);
    if (!defined('K_PATH_FONTS')) define('K_PATH_FONTS', rtrim($fontsDir,'/\\').DIRECTORY_SEPARATOR);
    $vendorFonts = base_path('vendor/tecnickcom/tcpdf/fonts');
    foreach (['dejavusans','dejavusansb','dejavusansi','dejavusansbi'] as $fam){
      foreach (['.php','.z'] as $ext){
        $srcf=$vendorFonts.DIRECTORY_SEPARATOR.$fam.$ext; $dstf=$fontsDir.DIRECTORY_SEPARATOR.$fam.$ext;
        if (!is_file($dstf) && is_file($srcf)) @copy($srcf,$dstf);
      }
    }
    $fontRegular='dejavusans'; $fontBold='dejavusansb';

    // FPDI
    $pdf = new Fpdi('P','mm');
    $pdf->setPrintHeader(false); $pdf->setPrintFooter(false); $pdf->setRTL(true);
    $pdf->setLanguageArray(['a_meta_charset'=>'UTF-8','a_meta_dir'=>'rtl','a_meta_language'=>'ar','w_page'=>'صفحة']);
    $pdf->setFontSubsetting(false);
    $pdf->SetMargins(15,18,15); $pdf->SetAutoPageBreak(true,15); $pdf->setCellPaddings(1.2,1.2,1.2,1.2); $pdf->setImageScale(1.25); $pdf->setCellHeightRatio(1.35);

    $pageCount = $pdf->setSourceFile($src);
    abort_if($pageNo<1 || $pageNo>$pageCount, 400, 'رقم صفحة غير صالح.');
    $tplIdx = $pdf->importPage($pageNo);
    $size   = $pdf->getTemplateSize($tplIdx);

    $pdf->AddPage($size['orientation'] ?? 'P', [$size['width'], $size['height']]);
    $pdf->useTemplate($tplIdx, 0, 0, $size['width'], $size['height'], true);

    $M = $pdf->getMargins();
    $innerW = $pdf->getPageWidth() - $M['left'] - $M['right'];

    $slots = AssignmentPdfSlot::where('assignment_pdf_template_id', $pdfTpl->id)->where('page',$pageNo)->get();
    $toRGB = function($hex){ $hex=ltrim($hex,'#'); if(strlen($hex)===3)$hex="{$hex[0]}{$hex[0]}{$hex[1]}{$hex[1]}{$hex[2]}{$hex[2]}"; $int=hexdec($hex); return [($int>>16)&255, ($int>>8)&255, $int&255]; };

    foreach ($slots as $s){
      $key=$s->key; if(!array_key_exists($key,$values)) continue;
      $val = $values[$key];

      [$r,$g,$b]=$toRGB($s->color ?? '#111111');
      $pdf->SetTextColor($r,$g,$b);

      if ($key==='answer_html'){
        $startY=max($s->y,$M['top']); $pdf->SetFont($fontRegular,'',$s->size,'',false); $pdf->SetXY($M['left'],$startY);
        $htmlBlock = '<div dir="rtl" style="text-align:right; line-height:1.8;">'.$this->normalizeHtmlForTcpdf((string)$val).'</div>';
        $pdf->writeHTMLCell($innerW, 0, $M['left'], $pdf->GetY(), $htmlBlock, 0, 1, false, true, 'J', true);
      } else {
        $pdf->SetFont($s->bold ? $fontBold : $fontRegular, '', $s->size, '', false);
        $wSafe = $s->w ?: $innerW; $pdf->SetXY($s->x, $s->y);
        $pdf->MultiCell($wSafe, 0, (string)$val, 0, strtoupper($s->align ?: 'R'), false, 1, '', '', true, 0, false, true, 0, 'T', true);
      }
    }

    return $pdf->Output('Task-Form.pdf','S');
  }
}

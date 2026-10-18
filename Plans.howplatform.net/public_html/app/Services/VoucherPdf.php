<?php

namespace App\Services;

use App\Models\Card;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * توليد قسائم البطاقات كـ PDF وفق قالب voucher_template (خلفية A4 كاملة)،
 * صفحة لكل بطاقة. يستخدم TCPDF المثبّت (عربي RTL + QR متجهي، بلا مكتبات/GD جديدة).
 *
 * المتغيّرات: sellerName / address / buyerName / phoneNumber / cardType / price / cardDetails / code / qrCode.
 */
class VoucherPdf
{
    private const REDEEM_BASE = 'https://plans.howplatform.net';

    public function __construct(private UserDirectory $dir) {}

    /** يبني PDF يحوي قسيمة لكل بطاقة (Collection|array من Card)؛ يُعيد محتوى الـPDF كنصّ. */
    public function render($cards): string
    {
        $cards = collect($cards);

        // معلومات البائعين دفعة واحدة (نقاط البيع + أسماء auth احتياطًا)
        $sellerIds = $cards->pluck('issued_by')->filter()->map(fn ($x) => (int) $x)->unique()->values()->all();
        $points    = empty($sellerIds) ? collect()
            : DB::table('sales_points')->whereIn('user_id', $sellerIds)->get()->keyBy('user_id');
        $names     = empty($sellerIds) ? [] : $this->dir->names($sellerIds);

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(18, 56, 18);
        $pdf->setCellHeightRatio(1.3);

        $bg = public_path('templates/voucher_bg.png');

        foreach ($cards as $card) {
            $this->page($pdf, $card, $points, $names, $bg);
        }

        if ($cards->isEmpty()) {
            $pdf->AddPage();
            $pdf->SetFont('dejavusans', '', 12);
            $pdf->SetXY(18, 60);
            $pdf->Cell(0, 8, 'لا توجد بطاقات مطابقة.', 0, 1, 'C');
        }

        return $pdf->Output('vouchers.pdf', 'S');
    }

    private function page($pdf, Card $c, $points, array $names, string $bg): void
    {
        $pdf->AddPage();

        // الخلفية (الترويسة/الشعار/الزخارف/التذييل) تملأ A4 كاملة
        if (is_file($bg)) {
            $pdf->Image($bg, 0, 0, 210, 297, 'PNG', '', '', false, 300, '', false, false, 0);
        }

        $pdf->setRTL(true);

        // ===== القيم =====
        $sp = ($c->issued_by && isset($points[$c->issued_by])) ? $points[$c->issued_by] : null;
        $sellerName = $sp->user_name ?? $sp->store_name ?? ($names[$c->issued_by] ?? null)
            ?: ($c->issued_by ? ('بائع #' . $c->issued_by) : 'إدارة المنصّة');
        $address = $sp
            ? trim((string) ($sp->governorate ?? '') . ((($sp->address_details ?? '') !== '') ? ' - ' . $sp->address_details : '')) ?: '—'
            : '—';

        $typeLabel = ['subscription' => 'اشتراك', 'points' => 'نقاط', 'mixed' => 'مختلطة'][$c->type] ?? $c->type;
        $price = ($c->price_amount !== null)
            ? (rtrim(rtrim(number_format((float) $c->price_amount, 2), '0'), '.') . ' ' . ($c->price_currency ?: 'JOD'))
            : '—';
        $details   = $this->cardDetails($c);
        $codeFmt   = implode('-', str_split($c->code, 4));
        $url       = self::REDEEM_BASE . '/redeem?code=' . rawurlencode($c->code);

        // ===== العنوان =====
        $pdf->SetY(56);
        $pdf->SetFont('dejavusans', 'B', 20);
        $pdf->Cell(0, 12, 'قسيمة منصّة كيف', 0, 1, 'C');
        $pdf->Ln(2);

        // ===== الحقول (جدول HTML RTL) =====
        $rows = [
            ['اسم البائع',    $sellerName],
            ['العنوان',       $address],
            ['اسم المشتري',   $c->buyer_name ?: '—'],
            ['رقم الهاتف',    $c->buyer_phone ?: '—'],
            ['نوع البطاقة',   $typeLabel],
            ['السعر',         $price],
            ['تفاصيل البطاقة', $details],
        ];
        $h = '<table cellpadding="6" style="font-size:12px; border-collapse:collapse;">';
        foreach ($rows as [$label, $val]) {
            $h .= '<tr>'
                . '<td width="32%" style="font-weight:bold; color:#013046;">' . $this->e($label) . '</td>'
                . '<td width="68%" style="color:#222;">' . $this->e((string) $val) . '</td>'
                . '</tr>';
        }
        $h .= '</table>';
        $pdf->writeHTML($h, true, false, true, false, '');

        // ===== الكود (بارز) =====
        $pdf->Ln(2);
        $pdf->SetFont('dejavusans', 'B', 22);
        $pdf->Cell(0, 12, $codeFmt, 0, 1, 'C');

        // ===== رمز QR (متجهي، بلا GD) — نُعطّل RTL مؤقتًا لضبط الإحداثيات من اليسار =====
        $pdf->setRTL(false);
        $style = ['border' => false, 'vpadding' => 0, 'hpadding' => 0, 'fgcolor' => [1, 48, 70], 'bgcolor' => false, 'module_width' => 1, 'module_height' => 1];
        $qr = 46;
        $pdf->write2DBarcode($url, 'QRCODE,M', (210 - $qr) / 2, 188, $qr, $qr, $style, 'N');
        $pdf->setRTL(true);

        $pdf->SetFont('dejavusans', '', 10);
        $pdf->SetY(236);
        $pdf->Cell(0, 6, 'امسح الرمز لتفعيل البطاقة', 0, 1, 'C');
    }

    private function cardDetails(Card $c): string
    {
        $plan = $c->plan; // قد يكون null
        if ($c->type === 'points') {
            return number_format((int) $c->points) . ' نقطة';
        }
        $days = (int) ($c->duration_days ?: (optional($plan)->duration_days ?? 0));
        $base = $plan ? $plan->name : 'اشتراك';
        $sub  = $base . ($days > 0 ? ' — ' . $days . ' يوم' : '');
        if ($c->type === 'mixed' && (int) $c->points > 0) {
            $sub .= ' + ' . number_format((int) $c->points) . ' نقطة';
        }
        return $sub;
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

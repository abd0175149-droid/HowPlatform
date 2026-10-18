<?php

namespace App\Services;

use App\Models\Card;
use Illuminate\Support\Facades\DB;

/**
 * يبني بيانات القسيمة (الحقول + رمز QR كـ SVG) لعرضها في قالب HTML بحجم A4.
 * QR عبر TCPDF2DBarcode::getBarcodeSVGcode (متجهي، بلا GD).
 */
class VoucherRenderer
{
    private const REDEEM_BASE = 'https://plans.howplatform.net';

    public function __construct(private UserDirectory $dir) {}

    /** يُعيد مصفوفة قسائم (كل عنصر = حقول بطاقة جاهزة للقالب). */
    public function forCards($cards): array
    {
        $cards = collect($cards);

        $sellerIds = $cards->pluck('issued_by')->filter()->map(fn ($x) => (int) $x)->unique()->values()->all();
        $points    = empty($sellerIds) ? collect() : DB::table('sales_points')->whereIn('user_id', $sellerIds)->get()->keyBy('user_id');
        $names     = empty($sellerIds) ? [] : $this->dir->names($sellerIds);

        return $cards->map(fn ($c) => $this->one($c, $points, $names))->all();
    }

    private function one(Card $c, $points, array $names): array
    {
        $sp = ($c->issued_by && isset($points[$c->issued_by])) ? $points[$c->issued_by] : null;
        $sellerName = $sp->user_name ?? $sp->store_name ?? ($names[$c->issued_by] ?? null)
            ?: ($c->issued_by ? ('بائع #' . $c->issued_by) : 'إدارة المنصّة');
        $address = $sp
            ? (trim((string) ($sp->governorate ?? '') . ((($sp->address_details ?? '') !== '') ? ' - ' . $sp->address_details : '')) ?: '—')
            : '—';

        $url = self::REDEEM_BASE . '/redeem?code=' . rawurlencode($c->code);

        return [
            'sellerName'  => $sellerName,
            'address'     => $address,
            'buyerName'   => $c->buyer_name ?: '—',
            'phoneNumber' => $c->buyer_phone ?: '—',
            'cardType'    => ['subscription' => 'اشتراك', 'points' => 'نقاط', 'mixed' => 'مختلطة'][$c->type] ?? $c->type,
            'price'       => ($c->price_amount !== null)
                ? (rtrim(rtrim(number_format((float) $c->price_amount, 2), '0'), '.') . ' ' . ($c->price_currency ?: 'JOD'))
                : '—',
            'cardDetails' => $this->details($c),
            'code'        => implode('-', str_split($c->code, 4)),
            'datetime'    => optional($c->created_at)->format('Y-m-d H:i') ?: now()->format('Y-m-d H:i'),
            'qr'          => $this->qrDataUri($url),
        ];
    }

    private function details(Card $c): string
    {
        if ($c->type === 'points') {
            return number_format((int) $c->points) . ' نقطة';
        }
        // النصّ يبدأ بالعربية ليبقى اتجاه السطر RTL، واسم الخطة (قد يكون لاتينيًا) معزول اتجاهيًا (FSI…PDI)
        $plan  = $c->plan;
        $name  = $plan ? ("\u{2068}" . $plan->name . "\u{2069}") : 'اشتراك';
        $days  = (int) ($c->duration_days ?: (optional($plan)->duration_days ?? 0));
        $parts = ['خطة ' . $name];
        if ($days > 0) {
            $parts[] = $days . ' يوم';
        }
        if ($c->type === 'mixed' && (int) $c->points > 0) {
            $parts[] = number_format((int) $c->points) . ' نقطة';
        }
        return implode(' · ', $parts);
    }

    /** QR كـ data:image/svg+xml (متجهي، يطبع بوضوح، بلا GD). */
    private function qrDataUri(string $url): string
    {
        $bc  = new \TCPDF2DBarcode($url, 'QRCODE,M');
        $svg = $bc->getBarcodeSVGcode(4, 4, 'black');
        if (! is_string($svg) || $svg === '') {
            return ''; // القالب يُخفي الصورة عند الفراغ
        }
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}

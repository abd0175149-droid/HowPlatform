<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\Card;
use App\Models\Plan;
use App\Models\PointPackage;
use App\Services\CardRedemptionService;
use App\Services\UserDirectory;
use App\Services\VoucherRenderer;

class CardsController extends Controller
{
    /** أبجدية آمنة بلا أحرف ملتبسة (O/0/I/1/L) */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** الدومين العام لوجهة تفعيل البطاقة عبر QR. */
    private const REDEEM_BASE = 'https://plans.howplatform.net';

    public function index(Request $request)
    {
        $cards = $this->filteredQuery($request)->paginate(40)->withQueryString();

        // حلّ أسماء من استبدلوا/أصدروا البطاقات (دفعة واحدة عبر auth)
        $ids = $cards->getCollection()
            ->flatMap(fn ($c) => [$c->redeemed_by, $c->issued_by])
            ->filter()->map(fn ($x) => (int) $x)->unique()->values()->all();
        $names = app(UserDirectory::class)->names($ids);

        return view('admin.cards.index', [
            'cards'   => $cards,
            'names'   => $names,
            'stats'   => [
                'total'    => Card::count(),
                'active'   => Card::where('status', 'active')->count(),
                'redeemed' => Card::where('status', 'redeemed')->count(),
                'disabled' => Card::where('status', 'disabled')->count(),
            ],
            'plans'   => Plan::orderBy('name')->get(),
            'batches' => Card::select('batch')->whereNotNull('batch')->distinct()->orderByDesc('batch')->limit(50)->pluck('batch'),
            'f'       => $request->only('status', 'batch', 'plan_id', 'q'),
        ]);
    }

    /** استعلام البطاقات مع كل الفلاتر (status/batch/plan_id + بحث q) — مشترك بين العرض والتصدير. */
    private function filteredQuery(Request $request)
    {
        $q = Card::with('plan')->latest('id');

        if ($s = $request->string('status')->value()) $q->where('status', $s);
        if ($b = $request->string('batch')->value())  $q->where('batch', $b);
        if ($p = (int) $request->input('plan_id'))     $q->where('plan_id', $p);

        $term = trim((string) $request->input('q', ''));
        if ($term !== '') {
            // q يطابق: الكود/المشتري محليًا، أو مستخدمًا (اسم/إيميل/هاتف/معرّف) عبر الدليل
            $userIds = collect(app(UserDirectory::class)->search($term, 50))->pluck('id')->map(fn ($x) => (int) $x)->all();
            $q->where(function ($w) use ($term, $userIds) {
                $w->where('code', 'like', "%{$term}%")
                  ->orWhere('buyer_name', 'like', "%{$term}%")
                  ->orWhere('buyer_email', 'like', "%{$term}%")
                  ->orWhere('buyer_phone', 'like', "%{$term}%");
                if (! empty($userIds)) {
                    $w->orWhereIn('redeemed_by', $userIds)->orWhereIn('issued_by', $userIds);
                }
            });
        }

        return $q;
    }

    public function issue()
    {
        return view('admin.cards.issue', [
            'plans'    => Plan::where('is_active', 1)->orderBy('name')->get(),
            'packages' => PointPackage::where('is_active', 1)->orderBy('sort_order')->orderBy('points')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'mode'        => ['nullable', Rule::in(['batch', 'single'])],
            'type'        => ['required', Rule::in(['subscription', 'points', 'mixed'])],
            'plan_id'     => ['nullable', 'integer', 'exists:plans,id'],
            'package_id'  => ['nullable', 'integer', 'exists:point_packages,id'],
            'points'      => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'count'       => ['nullable', 'integer', 'min:1', 'max:1000'],
            'batch'       => ['nullable', 'string', 'max:60'],
            'expires_at'  => ['nullable', 'date'],
            'buyer_name'  => ['nullable', 'string', 'max:255'],
            'buyer_email' => ['nullable', 'email', 'max:255'],
            'buyer_phone' => ['nullable', 'string', 'max:50'],
        ]);

        $mode = $v['mode'] ?? 'batch';

        if (in_array($v['type'], ['subscription', 'mixed'], true) && empty($v['plan_id'])) {
            return back()->withInput()->with('err', 'بطاقة الاشتراك تتطلب اختيار خطة.');
        }

        // النقاط/السعر للنوعين points/mixed (من حزمة أو يدويًا)
        $points        = (int) ($v['points'] ?? 0);
        $priceAmount   = null;
        $priceCurrency = 'JOD';
        if (in_array($v['type'], ['points', 'mixed'], true)) {
            if (! empty($v['package_id'])) {
                $pkg           = PointPackage::findOrFail((int) $v['package_id']);
                $points        = (int) $pkg->points;
                $priceAmount   = (float) $pkg->price_amount;
                $priceCurrency = $pkg->price_currency;
            }
            if ($points <= 0) {
                return back()->withInput()->with('err', 'بطاقة النقاط تتطلب اختيار حزمة أو عدد نقاط أكبر من صفر.');
            }
        }

        $adminId = (int) (session('auth_user')['id'] ?? 0);

        // ===== إصدار بطاقة واحدة (مع بيانات مشتري اختيارية) =====
        if ($mode === 'single') {
            $code = $this->generateUniqueCodes(1)[0];
            Card::create([
                'code'           => $code,
                'type'           => $v['type'],
                'plan_id'        => $v['plan_id'] ?? null,
                'points'         => $points,
                'price_amount'   => $priceAmount,
                'price_currency' => $priceCurrency,
                'status'         => 'active',
                'batch'          => $v['batch'] ?: ('SINGLE-' . now()->format('ymd')),
                'expires_at'     => $v['expires_at'] ?? null,
                'buyer_name'     => $v['buyer_name'] ?? null,
                'buyer_email'    => $v['buyer_email'] ?? null,
                'buyer_phone'    => $v['buyer_phone'] ?? null,
                'created_by'     => $adminId ?: null,
            ]);

            \App\Models\AdminNotification::notify('card_issued', 'تم إصدار بطاقة واحدة ' . $code, [
                'icon' => 'fa-ticket', 'color' => '#0ea5e9', 'link' => '/admin/cards?q=' . $code,
            ]);

            return redirect()->route('admin.cards.index', ['q' => $code])
                ->with('ok', "تم إصدار البطاقة «{$code}». اطبع قسيمتها من الجدول (أيقونة الطباعة).");
        }

        // ===== إصدار دفعة =====
        $count = (int) ($v['count'] ?? 0);
        if ($count < 1) {
            return back()->withInput()->with('err', 'حدّد عدد البطاقات في وضع الدفعة.');
        }
        $batch = $v['batch'] ?: ('B-' . now()->format('ymd-His'));

        $codes = $this->generateUniqueCodes($count);
        $rows  = [];
        $now   = now();
        foreach ($codes as $code) {
            $rows[] = [
                'code'           => $code,
                'type'           => $v['type'],
                'plan_id'        => $v['plan_id'] ?? null,
                'points'         => $points,
                'price_amount'   => $priceAmount,
                'price_currency' => $priceCurrency,
                'status'         => 'active',
                'batch'          => $batch,
                'expires_at'     => $v['expires_at'] ?? null,
                'created_by'     => $adminId ?: null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        DB::table('cards')->insert($rows);

        \App\Models\AdminNotification::notify('card_issued', 'تم إصدار ' . $count . ' بطاقة (دفعة ' . $batch . ')', [
            'icon' => 'fa-bolt', 'color' => '#0ea5e9', 'link' => '/admin/cards?batch=' . urlencode($batch),
        ]);

        return redirect()->route('admin.cards.index', ['batch' => $batch])
            ->with('ok', "تم إصدار {$count} بطاقة في الدفعة «{$batch}». اطبع قسائمها من زر «طباعة القسائم».");
    }

    public function export(Request $request)
    {
        $q = $this->filteredQuery($request);

        $filename = 'cards-' . ($request->string('batch')->value() ?: 'all') . '-' . now()->format('ymd-His') . '.csv';

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['code', 'type', 'plan', 'points', 'price', 'status', 'batch', 'expires_at', 'redeemed_by', 'redeemed_at']);
            $q->chunk(500, function ($cards) use ($out) {
                foreach ($cards as $c) {
                    fputcsv($out, [
                        $c->code, $c->type, optional($c->plan)->name, $c->points, $c->price_amount, $c->status,
                        $c->batch, optional($c->expires_at)->toDateString(),
                        $c->redeemed_by, optional($c->redeemed_at)->toDateTimeString(),
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** تصدير QR قابل للطباعة (PDF شبكي) للبطاقات المفلترة. */
    public function exportQrPdf(Request $request)
    {
        $cards = $this->filteredQuery($request)->limit(2000)->get();

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetFont('helvetica', '', 9);

        $style = ['border' => false, 'vpadding' => 0, 'hpadding' => 0, 'fgcolor' => [0, 0, 0], 'bgcolor' => false, 'module_width' => 1, 'module_height' => 1];
        $cols = 3; $rows = 5; $perPage = $cols * $rows;
        $marginX = 15; $marginY = 15; $colW = 60; $rowH = 53; $qr = 42;

        $i = 0;
        foreach ($cards as $c) {
            $pos = $i % $perPage;
            if ($pos === 0) $pdf->AddPage();
            $col = $pos % $cols;
            $row = intdiv($pos, $cols);
            $x = $marginX + $col * $colW;
            $y = $marginY + $row * $rowH;

            $url = self::REDEEM_BASE . '/redeem?code=' . rawurlencode($c->code);
            $pdf->write2DBarcode($url, 'QRCODE,M', $x + ($colW - $qr) / 2, $y, $qr, $qr, $style, 'N');

            $pdf->SetXY($x, $y + $qr + 1);
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell($colW, 5, $c->code, 0, 0, 'C');

            $sub = $c->type === 'points'
                ? (number_format((int) $c->points) . ' pts')
                : (optional($c->plan)->slug ?? $c->type);
            $pdf->SetXY($x, $y + $qr + 6);
            $pdf->SetFont('helvetica', '', 7);
            $pdf->Cell($colW, 4, $sub, 0, 0, 'C');
            $pdf->SetFont('helvetica', '', 9);

            $i++;
        }

        if ($i === 0) {
            $pdf->AddPage();
            $pdf->SetXY(15, 15);
            $pdf->Cell(0, 8, 'No cards match the current filter.', 0, 0, 'L');
        }

        $batch    = $request->string('batch')->value() ?: 'all';
        $filename = 'cards-qr-' . $batch . '-' . now()->format('ymd-His') . '.pdf';

        return response($pdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /** قسيمة بطاقة واحدة كصفحة HTML بحجم A4 (تُطبع من المتصفّح). */
    public function voucher(Card $card, VoucherRenderer $r)
    {
        $card->load('plan');
        return view('cards.voucher', [
            'vouchers' => $r->forCards([$card]),
            'bg'       => asset('templates/voucher_card_bg.png'),
        ]);
    }

    /** قسائم جماعية (صفحة A4 لكل بطاقة) للبطاقات المفلترة. */
    public function vouchersBulk(Request $request, VoucherRenderer $r)
    {
        $cards = $this->filteredQuery($request)->limit(500)->get();
        return view('cards.voucher', [
            'vouchers' => $r->forCards($cards),
            'bg'       => asset('templates/voucher_card_bg.png'),
        ]);
    }

    public function toggle(Card $card)
    {
        if ($card->status === 'redeemed') {
            return back()->with('err', 'لا يمكن تعطيل بطاقة مستبدَلة.');
        }
        $card->update(['status' => $card->status === 'active' ? 'disabled' : 'active']);

        return back()->with('ok', 'تم تحديث حالة البطاقة.');
    }

    /** صفحة مستقلة: تفعيل بطاقة لصالح مستخدم (بحث بالاسم/الإيميل/الهاتف). */
    public function redeemForm()
    {
        return view('admin.cards.redeem');
    }

    /** استبدال يدوي من الأدمن لصالح مستخدم */
    public function redeem(Request $request, CardRedemptionService $svc)
    {
        $v = $request->validate([
            'code'    => ['required', 'string', 'max:60'],
            'user_id' => ['required', 'integer', 'min:1'],
        ]);

        $res = $svc->redeem($v['code'], (int) $v['user_id'], 'admin');

        if ($res['ok']) {
            $msg = 'تم الاستبدال للمستخدم ' . $v['user_id'] . '.';
            if (!empty($res['subscription_id'])) $msg .= ' فُعّل اشتراك #' . $res['subscription_id'] . '.';
            if (!empty($res['points_granted']))  $msg .= ' مُنحت ' . $res['points_granted'] . ' نقطة.';
            return back()->with('ok', $msg);
        }

        return back()->with('err', 'تعذّر الاستبدال: ' . $this->errLabel($res['error'] ?? 'unknown'));
    }

    private function errLabel(string $err): string
    {
        return [
            'card_not_found'   => 'الكود غير موجود',
            'already_redeemed' => 'البطاقة مستبدَلة مسبقًا',
            'card_disabled'    => 'البطاقة معطّلة',
            'card_expired'     => 'انتهت صلاحية البطاقة',
            'invalid_input'    => 'مدخلات غير صحيحة',
        ][$err] ?? $err;
    }

    private function generateUniqueCodes(int $n): array
    {
        $set = [];
        while (count($set) < $n) {
            $need = $n - count($set);
            $batch = [];
            for ($i = 0; $i < $need; $i++) {
                $batch[$this->randomCode()] = true;
            }
            $existing = Card::whereIn('code', array_keys($batch))->pluck('code')->all();
            foreach ($existing as $e) unset($batch[$e]);
            $set += $batch;
        }
        return array_slice(array_keys($set), 0, $n);
    }

    private function randomCode(): string
    {
        $a = self::ALPHABET; $len = strlen($a); $out = '';
        for ($i = 0; $i < 12; $i++) {
            $out .= $a[random_int(0, $len - 1)];
        }
        return $out;
    }
}

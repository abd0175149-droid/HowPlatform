<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tool;
use App\Models\ToolStat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cookie;
use PDF;

class IdCardController extends Controller
{

public function rate(Request $request)
{
    $data = $request->validate([
        'rating' => 'required|integer|min:1|max:5',
    ]);

    $tool = \App\Models\Tool::where('slug', 'id-card-generator')->first();
    if (!$tool) {
        return response()->json(['ok' => false, 'msg' => 'الأداة غير موجودة'], 404);
    }

    \App\Models\ToolStat::firstOrCreate(['tool_id' => $tool->id]);

    \DB::table('tool_stats')->where('tool_id', $tool->id)->update([
        'rating_avg'   => \DB::raw('(rating_avg * rating_count + '.(int)$data['rating'].') / (rating_count + 1)'),
        'rating_count' => \DB::raw('rating_count + 1'),
        'updated_at'   => now(),
    ]);

    $row = \DB::table('tool_stats')->where('tool_id', $tool->id)->first();

    // اسم موحّد للكوكي الخاصة بالتقييم
    $cookie = \Cookie::make('rated_tool_idcard', '1', 60*24*365);

    return response()->json([
        'ok'           => true,
        'rating_avg'   => round((float)$row->rating_avg, 2),
        'rating_count' => (int)$row->rating_count,
    ])->cookie($cookie);
}



    public function show(Request $request)
{
    // تأكيد وجود صفّ الإحصاءات للأداة (مرة واحدة)
    if ($tool = \App\Models\Tool::where('slug','id-card-generator')->first()) {
        \App\Models\ToolStat::firstOrCreate(['tool_id' => $tool->id]);
    }

    $years = ['2026/2027','2025/2026'];

    $universities = [
        'jordan'    => ['name' => 'الجامعة الأردنية',  'logo' => asset('assets/images/jordan.png')],
        'yarmouk'   => ['name' => 'جامعة اليرموك',     'logo' => asset('assets/images/yarmouk.png')],
        'mutah'     => ['name' => 'جامعة مؤتة',        'logo' => asset('assets/images/mutah.png')],
        'hashemite' => ['name' => 'الجامعة الهاشمية',  'logo' => asset('assets/images/hashemite.png')],
    ];

    $templates = [
        'classic'  => 'كلاسيكي',
        'minimal'  => 'بسيط',
        'gradient' => 'متدرّج',
        'stripe'   => 'شريط ملون',
        'badge'    => 'شارة مميّزة',
    ];

    return view('tools.id-card', compact('years', 'universities', 'templates'));
}


public function pdf(Request $request)
{
    $data = $request->validate([
        'year'        => 'required|string|max:20',
        'university'  => 'required|string|in:jordan,yarmouk,mutah,hashemite',
        'studentName' => 'required|string|max:120',
        'major'       => 'required|string|max:120',
        'template'    => 'required|string|in:classic,minimal,gradient,stripe,badge',
        'orientation' => 'required|string|in:portrait,landscape',
    ]);

    $uniMap = [
        'jordan'    => ['name' => 'الجامعة الأردنية',  'logo' => asset('assets/images/jordan.png')],
        'yarmouk'   => ['name' => 'جامعة اليرموك',     'logo' => asset('assets/images/yarmouk.png')],
        'mutah'     => ['name' => 'جامعة مؤتة',        'logo' => asset('assets/images/mutah.png')],
        'hashemite' => ['name' => 'الجامعة الهاشمية',  'logo' => asset('assets/images/hashemite.png')],
    ];

    $data['universityName'] = $uniMap[$data['university']]['name'];
    $data['universityLogo'] = $uniMap[$data['university']]['logo'];

    $paper = 'a6';
    $orientation = $data['orientation'] === 'landscape' ? 'landscape' : 'portrait';

    $pdf = \PDF::loadView('tools.pdf.id-card', $data)->setPaper($paper, $orientation);

    // لا تعدّ الاستخدام هنا (تم نقله لـ PdfStampController@stamp)
    return $pdf->download('id-card.pdf');
}



public function markUsed(Request $request)
{
    $tool = Tool::where('slug', 'id-card-generator')->first();
    if (!$tool) return response()->noContent();

    ToolStat::firstOrCreate(['tool_id' => $tool->id]);

    // دائماً +1 استخدام
    ToolStat::where('tool_id', $tool->id)->update([
        'uses_count'   => \DB::raw('uses_count + 1'),
        'last_used_at' => now(),
    ]);

    // مستخدم فريد مرة واحدة بكوكي
    $cookieKey = 'used_tool_idcard';
    $resp = response()->noContent();
    if (!$request->hasCookie($cookieKey)) {
        ToolStat::where('tool_id', $tool->id)->update([
            'unique_users_count' => \DB::raw('unique_users_count + 1'),
        ]);
        $resp->withCookie(Cookie::make($cookieKey, '1', 60*24*365));
    }
    return $resp;
}

}

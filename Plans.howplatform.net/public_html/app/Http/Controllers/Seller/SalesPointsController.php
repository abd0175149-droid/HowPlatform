<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * إدارة نقاط البيع (المندوبون) + عرض السقوف والأرصدة — للمشرفين.
 * مُرحَّل من www POS، مبسّط: إدخال user_id/الاسم يدويًا (بلا اعتماد على AUTH sales-users).
 */
class SalesPointsController extends Controller
{
    private array $governorates = [
        'عمان', 'الزرقاء', 'إربد', 'البلقاء', 'الكرك', 'معان',
        'المفرق', 'مادبا', 'عجلون', 'جرش', 'الطفيلة', 'العقبة',
    ];

    public function index(Request $request)
    {
        $periodKey = now()->format('Y-m');

        $points = DB::table('sales_points')->orderByDesc('id')->paginate(20)->withQueryString();

        // أحدث صفّ لكل user_id (للاسم/الرصيد)
        $latest = DB::table('sales_points')->select('user_id', DB::raw('MAX(id) as max_id'))->groupBy('user_id');

        $limits = DB::table('sales_limits as sl')
            ->leftJoinSub($latest, 'ls', fn ($j) => $j->on('ls.user_id', '=', 'sl.user_id'))
            ->leftJoin('sales_points as sp', fn ($j) => $j->on('sp.id', '=', 'ls.max_id'))
            ->select('sl.id', 'sl.user_id', 'sl.monthly_limit', 'sl.updated_at', DB::raw('COALESCE(sp.user_name, "غير معروف") as user_name'))
            ->orderByDesc('sl.updated_at')->get();

        $stats = DB::table('sales_points as sp')
            ->joinSub($latest, 'ls', fn ($j) => $j->on('sp.id', '=', 'ls.max_id'))
            ->leftJoin('sales_limits as sl', 'sl.user_id', '=', 'sp.user_id')
            ->select('sp.user_id', 'sp.user_name', 'sp.balance', 'sp.balance_month_key', 'sl.monthly_limit')
            ->get();

        return view('seller.manage', [
            'points'       => $points,
            'limits'       => $limits,
            'stats'        => $stats,
            'periodKey'    => $periodKey,
            'governorates' => $this->governorates,
        ]);
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'user_id'          => ['required', 'integer'],
            'user_name'        => ['required', 'string', 'max:255'],
            'store_name'       => ['required', 'string', 'max:255'],
            'phone'            => ['required', 'string', 'max:30'],
            'location_url'     => ['nullable', 'string', 'max:255'],
            'sell_method'      => ['required', Rule::in(['online', 'in_person', 'both'])],
            'image'            => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'governorate'      => ['nullable', 'string', 'max:60'],
            'address_details'  => ['nullable', 'string', 'max:255'],
        ]);

        $discount = max(0, min(100, (float) ($v['discount_percent'] ?? 0)));

        $data = [
            'user_id'          => (int) $v['user_id'],
            'user_name'        => $v['user_name'],
            'store_name'       => $v['store_name'],
            'phone'            => $v['phone'],
            'location_url'     => $v['location_url'] ?? null,
            'sell_method'      => $v['sell_method'],
            'discount_percent' => $discount,
            'governorate'      => $v['governorate'] ?? null,
            'address_details'  => $v['address_details'] ?? null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ];

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('sales_points', 'public');
            $data['image_url'] = Storage::url($path);
        }

        DB::table('sales_points')->insert($data);

        DB::table('sales_limits')->updateOrInsert(
            ['user_id' => (int) $v['user_id']],
            ['monthly_limit' => 200, 'updated_at' => now(), 'created_at' => now()]
        );

        return back()->with('ok', '✅ تمت إضافة نقطة البيع (وحدّ شهري افتراضي 200 JOD).');
    }

    public function update(Request $request, int $id)
    {
        $v = $request->validate([
            'store_name'       => ['required', 'string', 'max:255'],
            'phone'            => ['required', 'string', 'max:30'],
            'location_url'     => ['nullable', 'string', 'max:255'],
            'sell_method'      => ['required', Rule::in(['online', 'in_person', 'both'])],
            'image'            => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image'     => ['nullable', Rule::in(['0', '1'])],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'governorate'      => ['nullable', 'string', 'max:60'],
            'address_details'  => ['nullable', 'string', 'max:255'],
        ]);

        $row = DB::table('sales_points')->where('id', $id)->first();
        if (! $row) {
            return back()->with('err', 'السجل غير موجود.');
        }

        $data = [
            'store_name'      => $v['store_name'],
            'phone'           => $v['phone'],
            'location_url'    => $v['location_url'] ?? null,
            'sell_method'     => $v['sell_method'],
            'governorate'     => $v['governorate'] ?? null,
            'address_details' => $v['address_details'] ?? null,
            'updated_at'      => now(),
        ];

        if ($request->has('discount_percent')) {
            $data['discount_percent'] = max(0, min(100, (float) $request->input('discount_percent', 0)));
        }

        if ($request->input('remove_image') === '1') {
            $this->deleteImageByUrl($row->image_url ?? null);
            $data['image_url'] = null;
        }
        if ($request->hasFile('image')) {
            $this->deleteImageByUrl($row->image_url ?? null);
            $path = $request->file('image')->store('sales_points', 'public');
            $data['image_url'] = Storage::url($path);
        }

        DB::table('sales_points')->where('id', $id)->update($data);

        return back()->with('ok', '✏️ تم تعديل نقطة البيع.');
    }

    public function delete(int $id)
    {
        $row = DB::table('sales_points')->where('id', $id)->first();
        if ($row) {
            $this->deleteImageByUrl($row->image_url ?? null);
            if (! empty($row->user_id)) {
                DB::table('sales_limits')->where('user_id', $row->user_id)->delete();
            }
            DB::table('sales_points')->where('id', $id)->delete();
        }

        return back()->with('ok', '🗑️ تم حذف نقطة البيع وسقفها.');
    }

    private function deleteImageByUrl(?string $imageUrl): void
    {
        if (! $imageUrl) {
            return;
        }
        if (Str::startsWith($imageUrl, '/storage/')) {
            $relative = Str::after($imageUrl, '/storage/');
            if ($relative && Storage::disk('public')->exists($relative)) {
                Storage::disk('public')->delete($relative);
            }
        }
    }
}

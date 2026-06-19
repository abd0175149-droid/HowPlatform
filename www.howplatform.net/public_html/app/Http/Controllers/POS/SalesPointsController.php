<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use App\Http\Controllers\POS\AccountingService;

class SalesPointsController extends Controller
{
    /**
     * صفحة الداشبورد + تبويب نقاط البيع (إحصائيات + باجينيشن)
     */
    public function index(Request $request)
    {
        // ✅ صلاحيات
        $userData = $request->session()->get('auth_user', []);
        if ((int)($userData['is_admin'] ?? 0) !== 1) {
            return redirect('/')->with('error', '🚫 غير مصرح لك بالوصول إلى صفحة المبيعات');
        }

        // تبويب نشط
        $activeTab = $request->query('tab', session('pos_active_tab', 'points'));
        session(['pos_active_tab' => $activeTab]);

        // أحجام صفحات للـ overview (من الاستعلام)
        $perPlatforms = (int) max(5, min(100, (int) $request->query('per_page_platforms', 10)));
        $perSellers   = (int) max(5, min(100, (int) $request->query('per_page_sellers', 10)));

        // ===== إحصائيات عامة (Overview) =====
        $totalVouchers = DB::table('vouchers')->count();
        $totalSales    = (float) DB::table('vouchers')->sum('price_amount');

        // المبيعات حسب المنصة — Paginate + ترقيم HOW في الواجهة
        $salesByPlatform = DB::table('vouchers')
            ->select('platform', DB::raw('COUNT(*) AS count'), DB::raw('SUM(price_amount) AS total'))
            ->groupBy('platform')
            ->orderByDesc('total')
            ->paginate($perPlatforms)
            ->appends($request->query())
            ->appends(['tab' => 'overview', 'per_page_platforms' => $perPlatforms]);

        // أفضل البائعين
        $salesByUser = DB::table('vouchers')
            ->select(
                'issued_by AS user_id',
                'issued_by_name AS name',
                DB::raw('COUNT(*) AS count'),
                DB::raw('SUM(price_amount) AS total')
            )
            ->groupBy('issued_by', 'issued_by_name')
            ->orderByDesc('total')
            ->paginate($perSellers)
            ->appends($request->query())
            ->appends(['tab' => 'overview', 'per_page_sellers' => $perSellers]);

        // ===== مندوبون من AUTH (للـ DDL) =====
        $salesUsers = collect();
        try {
            $url = rtrim((string) config('services.auth.base_url'), '/') . '/sales-users';
            $res = Http::withHeaders([
                'Authorization' => 'Bearer ' . (string) config('services.auth.secret_key'),
                'Accept'        => 'application/json',
            ])->get($url);
            if ($res->ok()) $salesUsers = collect($res->json());
        } catch (\Throwable $e) {
            \Log::error('AUTH sales-users exception: ' . $e->getMessage());
        }

        // ===== نقاط البيع (باجينيشن) =====
        $perPage = (int) max(5, min(100, (int) $request->query('per_page', 10)));
        $salesPoints = DB::table('sales_points')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query())
            ->appends(['tab' => $activeTab])
            ->appends(['per_page' => $perPage]);

        $points = $salesPoints; // alias للتوافق

        // ===== أحدث سجل لكل user_id من sales_points (للاسم/الرصيد) =====
        $latestSpSub = DB::table('sales_points')
            ->select('user_id', DB::raw('MAX(id) as max_id'))
            ->groupBy('user_id');

        // ===== سقوف المبيعات مع الاسم =====
        $salesLimits = DB::table('sales_limits as sl')
            ->leftJoinSub($latestSpSub, 'ls', fn($j) => $j->on('ls.user_id', '=', 'sl.user_id'))
            ->leftJoin('sales_points as sp', fn($j) => $j->on('sp.id', '=', 'ls.max_id'))
            ->select(
                'sl.id','sl.user_id','sl.monthly_limit','sl.created_at','sl.updated_at',
                DB::raw('COALESCE(sp.user_name, "غير معروف") as user_name')
            )
            ->orderByDesc('sl.updated_at')
            ->get();

        // ===== قائمة مندوبي limits fallback من sales_points =====
        $limitUsers = DB::table('sales_points as sp')
            ->joinSub($latestSpSub, 'lu', fn($j) => $j->on('sp.id','=','lu.max_id'))
            ->select(DB::raw('sp.user_id as id'), 'sp.user_name as name')
            ->orderBy('sp.user_name')
            ->get();

        // ===== إحصاءات المندوبين (رصيد+سقف للشهر الحالي) =====
        $periodKey = now()->format('Y-m');
        $userStats = DB::table('sales_points as sp')
            ->joinSub($latestSpSub, 'ls', fn($j) => $j->on('sp.id','=','ls.max_id'))
            ->leftJoin('sales_limits as sl', 'sl.user_id','=','sp.user_id')
            ->select('sp.user_id','sp.user_name','sp.balance','sp.balance_month_key','sl.monthly_limit')
            ->get();

        // ===== عمليات شهرية (Transactions) مع الاسم =====
        $salesTransactions = DB::table('sales_transactions as t')
            ->leftJoinSub($latestSpSub, 'ls', fn($j) => $j->on('ls.user_id','=','t.user_id'))
            ->leftJoin('sales_points as sp', fn($j) => $j->on('sp.id','=','ls.max_id'))
            ->select('t.*', DB::raw('COALESCE(sp.user_name, "غير معروف") as user_name'))
            ->where('t.period_key', $periodKey)
            ->orderByDesc('t.id')
            ->paginate(15)
            ->appends($request->query())
            ->appends(['tab' => $activeTab]);

        // 🔽 قائمة المحافظات لإظهارها في المودال
        $governorates = [
            'عمان','الزرقاء','إربد','البلقاء','الكرك','معان',
            'المفرق','مادبا','عجلون','جرش','الطفيلة','العقبة',
        ];

        // ===== عرض الصفحة المركزية =====
        return view('sales.dashboard', compact(
            'totalVouchers',
            'totalSales',
            'salesByPlatform',
            'salesByUser',
            'salesUsers',
            'salesPoints',
            'points',
            'activeTab',
            'salesLimits',
            'limitUsers',
            'userStats',
            'salesTransactions',
            'periodKey',
            'perPlatforms',
            'perSellers',
            'governorates' // << جديد
        ));
    }

    /**
     * API: قائمة نقاط البيع (باجينيشن JSON)
     */
    public function list(Request $request)
    {
        $perPage = (int) max(5, min(100, (int) $request->query('per_page', 10)));

        $points = DB::table('sales_points')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json($points);
    }

    /**
     * حفظ (ويب) — يدعم رفع صورة ويضيف حد شهري 200 في sales_limits
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'          => ['required','integer'],
            'user_name'        => ['nullable','string','max:255'], // اختياري (يأتي مخفيًا من الواجهة)
            'store_name'       => ['required','string','max:255'],
            'phone'            => ['required','string','max:20'],
            'location_url'     => ['nullable','string','max:255'],
            'sell_method'      => ['required', Rule::in(['online','in_person','both'])],
            'image'            => ['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],
            'discount_percent' => ['nullable','numeric','min:0','max:100'],
            'governorate'      => ['nullable','string','max:50'],
            'address_details'  => ['nullable','string','max:255'],
        ]);

        // 1) حاول أخذ الاسم المُرسل (مخفي من الواجهة)
        $userName = trim((string)($validated['user_name'] ?? ''));

        // 2) لو فاضي، جرّب users المحلي
        if ($userName === '') {
            try {
                $found = DB::table('users')->where('id', $validated['user_id'])->value('name');
                if ($found) $userName = $found;
            } catch (\Throwable $e) {
                // جدول users قد لا يتوفر
            }
        }

        // 3) لو فاضي، جرّب AUTH /sales-users
        if ($userName === '') {
            try {
                $url = rtrim((string) config('services.auth.base_url'), '/') . '/sales-users';
                $res = Http::withHeaders([
                    'Authorization' => 'Bearer ' . (string) config('services.auth.secret_key'),
                    'Accept'        => 'application/json',
                ])->get($url);

                if ($res->ok()) {
                    $arr   = collect($res->json());
                    $match = $arr->firstWhere('id', (int) $validated['user_id']);
                    if ($match && !empty($match['name'])) {
                        $userName = $match['name'];
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('AUTH name fetch failed: ' . $e->getMessage());
            }
        }

        if ($userName === '') $userName = 'غير معروف';

        $discount = $request->input('discount_percent', 0);
        $discount = is_null($discount) ? 0 : (float) $discount;
        $discount = max(0, min(100, $discount));

        $data = [
            'user_id'          => $validated['user_id'],
            'user_name'        => $userName,                 // ← ثابت بعد الاستخراج
            'store_name'       => $validated['store_name'],
            'phone'            => $validated['phone'],
            'location_url'     => $validated['location_url'] ?? null,
            'sell_method'      => $validated['sell_method'],
            'discount_percent' => $discount,
            'governorate'      => $validated['governorate'] ?? null,
            'address_details'  => $validated['address_details'] ?? null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ];

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('sales_points', 'public');
            $data['image_url'] = Storage::url($path);
        }

        $id = DB::table('sales_points')->insertGetId($data);

        DB::table('sales_limits')->updateOrInsert(
            ['user_id' => $validated['user_id']],
            ['monthly_limit' => 200, 'updated_at'=>now(), 'created_at'=>now()]
        );

        session(['pos_active_tab' => 'points']);
        return redirect()->route('pos.sales-points.index', ['tab' => 'points'])
            ->with('success', '✅ تمت إضافة نقطة البيع بنجاح');
    }

    /**
     * API: إضافة JSON (يدعم discount_percent + image_url الجاهز)
     */
    public function storeJson(Request $request)
    {
        $validated = $request->validate([
            'user_id'          => ['required','integer'],
            'user_name'        => ['required','string','max:255'],   // JSON يرسل اسم المندوب
            'store_name'       => ['required','string','max:255'],
            'phone'            => ['required','string','max:20'],
            'location_url'     => ['nullable','string','max:255'],
            'sell_method'      => ['required', Rule::in(['online','in_person','both'])],
            'image_url'        => ['nullable','string','max:255'],
            'discount_percent' => ['nullable','numeric','min:0','max:100'],
            'governorate'      => ['nullable','string','max:50'],
            'address_details'  => ['nullable','string','max:255'],
        ]);

        $discount = $request->input('discount_percent', 0);
        $discount = is_null($discount) ? 0 : (float) $discount;
        $discount = max(0, min(100, $discount));

        $id = DB::table('sales_points')->insertGetId([
            'user_id'          => $validated['user_id'],
            'user_name'        => $validated['user_name'],
            'store_name'       => $validated['store_name'],
            'phone'            => $validated['phone'],
            'location_url'     => $validated['location_url'] ?? null,
            'sell_method'      => $validated['sell_method'],
            'image_url'        => $validated['image_url'] ?? null,
            'discount_percent' => $discount,
            'governorate'      => $validated['governorate'] ?? null,
            'address_details'  => $validated['address_details'] ?? null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        DB::table('sales_limits')->updateOrInsert(
            ['user_id' => $validated['user_id']],
            ['monthly_limit' => 200, 'updated_at'=>now(), 'created_at'=>now()]
        );

        return response()->json(['status'=>'success','message'=>'تمت الإضافة','id'=>$id]);
    }

    /**
     * API: تعديل (يمنع تعديل user_name؛ يدعم حذف/رفع صورة + خصم)
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'store_name'       => ['required','string','max:255'],
            'phone'            => ['required','string','max:20'],
            'location_url'     => ['nullable','string','max:255'],
            'sell_method'      => ['required', Rule::in(['online','in_person','both'])],
            'image'            => ['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],
            'remove_image'     => ['nullable', Rule::in(['0','1'])],
            'discount_percent' => ['nullable','numeric','min:0','max:100'],
            'governorate'      => ['nullable','string','max:50'],
            'address_details'  => ['nullable','string','max:255'],
        ]);

        $row = DB::table('sales_points')->where('id', $id)->first();
        if (!$row) {
            return response()->json(['status'=>'error','message'=>'السجل غير موجود'], 404);
        }

        $data = [
            'store_name'      => $validated['store_name'],
            'phone'           => $validated['phone'],
            'location_url'    => $validated['location_url'] ?? null,
            'sell_method'     => $validated['sell_method'],
            'governorate'     => $validated['governorate'] ?? null,
            'address_details' => $validated['address_details'] ?? null,
            'updated_at'      => now(),
        ];

        // خصم [0..100] إن أُرسل
        if ($request->has('discount_percent')) {
            $discount = $request->input('discount_percent');
            $discount = is_null($discount) ? 0 : (float) $discount;
            $discount = max(0, min(100, $discount));
            $data['discount_percent'] = $discount;
        }

        // حذف الصورة (إذا طُلب)
        if ($request->input('remove_image') === '1') {
            $this->deleteImageByUrl($row->image_url ?? null);
            $data['image_url'] = null;
        }

        // رفع صورة جديدة (نحذف القديمة قبل التخزين)
        if ($request->hasFile('image')) {
            $this->deleteImageByUrl($row->image_url ?? null);
            $path = $request->file('image')->store('sales_points', 'public');
            $data['image_url'] = Storage::url($path);
        }

        DB::table('sales_points')->where('id', $id)->update($data);

        return response()->json(['status'=>'success','message'=>'✏️ تم تعديل نقطة البيع بنجاح']);
    }

    /**
     * حذف (ويب) — يحذف الصورة من القرص + يحذف سقف المبيعات + يحذف السجل
     */
    public function delete($id)
    {
        $row = DB::table('sales_points')->where('id', $id)->first();
        if ($row) {
            // حذف ملف الصورة الفعلي (إن وُجد)
            $this->deleteImageByUrl($row->image_url ?? null);

            // حذف سقف المبيعات للمندوب
            if (!empty($row->user_id)) {
                DB::table('sales_limits')->where('user_id', $row->user_id)->delete();
            }

            // حذف السجل
            DB::table('sales_points')->where('id', $id)->delete();
        }

        session(['pos_active_tab' => 'points']);
        return redirect()->route('pos.sales-points.index', ['tab' => 'points'])
            ->with('success', '🗑️ تم حذف نقطة البيع وكل مرفقاتها بنجاح');
    }

    /**
     * API: حذف الصورة فقط (اختياري)
     */
    public function removeImage($id)
    {
        $row = DB::table('sales_points')->where('id', $id)->first();
        if (!$row) {
            return response()->json(['status'=>'error','message'=>'السجل غير موجود'], 404);
        }

        $this->deleteImageByUrl($row->image_url ?? null);

        DB::table('sales_points')->where('id', $id)->update([
            'image_url'  => null,
            'updated_at' => now(),
        ]);

        return response()->json(['status'=>'success','message'=>'🧹 تم حذف الصورة بنجاح']);
    }

    /**
     * يحذف ملفًا فعليًا بناءً على image_url بصيغة: /storage/dir/file.ext
     */
    private function deleteImageByUrl(?string $imageUrl): void
    {
        if (!$imageUrl) return;

        if (Str::startsWith($imageUrl, '/storage/')) {
            $relative = Str::after($imageUrl, '/storage/'); // sales_points/xxx.png
            if ($relative && Storage::disk('public')->exists($relative)) {
                Storage::disk('public')->delete($relative);
            }
        }
    }
}

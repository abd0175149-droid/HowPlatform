<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** السقوف الشهرية للمندوبين — مُرحَّل من www POS. */
class SalesLimitsController extends Controller
{
    public function upsert(Request $request)
    {
        $v = $request->validate([
            'user_id'       => ['required', 'integer'],
            'monthly_limit' => ['required', 'numeric', 'min:0'],
        ]);

        DB::table('sales_limits')->updateOrInsert(
            ['user_id' => (int) $v['user_id']],
            ['monthly_limit' => (float) $v['monthly_limit'], 'updated_at' => now(), 'created_at' => now()]
        );

        return back()->with('ok', '✅ تم حفظ السقف الشهري.');
    }

    public function delete(int $userId)
    {
        DB::table('sales_limits')->where('user_id', $userId)->delete();

        return back()->with('ok', '🗑️ تم حذف السقف لهذا المندوب.');
    }
}

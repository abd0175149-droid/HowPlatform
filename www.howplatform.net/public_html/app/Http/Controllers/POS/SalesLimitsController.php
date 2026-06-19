<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesLimitsController extends Controller
{
    public function upsert(Request $request)
    {
        $userData = $request->session()->get('auth_user', []);
        if ((int)($userData['is_admin'] ?? 0) !== 1) {
            return redirect('/')->with('error', '🚫 غير مصرح لك بالوصول إلى صفحة المبيعات');
        }

        $data = $request->validate([
            'user_id'       => ['required','integer'],
            'monthly_limit' => ['required','numeric','min:0'],
        ]);

        DB::table('sales_limits')->updateOrInsert(
            ['user_id' => (int) $data['user_id']],
            [
                'monthly_limit' => (float) $data['monthly_limit'],
                'updated_at'    => now(),
                'created_at'    => now(),
            ]
        );
        session(['pos_active_tab' => 'limits']);
        return redirect()
            ->route('pos.sales-points.index', ['tab' => 'limits'])
            ->with('success', '✅ تم حفظ سقف المبيعات بنجاح')
            ->with('flash_tab', '#limits');

    }

    public function delete($userId)
    {
        DB::table('sales_limits')->where('user_id', (int) $userId)->delete();

        session(['pos_active_tab' => 'limits']);
        return redirect()
            ->route('pos.sales-points.index', ['tab' => 'limits'])
            ->with('success', '🗑️ تم حذف السقف لهذا المندوب')
            ->with('flash_tab', '#limits');
    }
}

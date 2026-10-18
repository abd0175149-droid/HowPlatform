<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PointPackage;
use Illuminate\Http\Request;

/**
 * إدارة سلّم تسعير النقاط (حزم النقاط المسعّرة).
 * تُستخدَم عند إصدار بطاقات النقاط (أدمن/بائع) لربط عدد النقاط بسعره.
 */
class PointPackagesController extends Controller
{
    public function index()
    {
        return view('admin.packages.index', [
            'packages' => PointPackage::orderBy('sort_order')->orderBy('points')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        PointPackage::create($data);

        return back()->with('ok', 'تمت إضافة الحزمة.');
    }

    public function update(Request $request, PointPackage $package)
    {
        $data = $this->validateData($request);
        $package->update($data);

        return back()->with('ok', 'تم تحديث الحزمة.');
    }

    public function toggle(PointPackage $package)
    {
        $package->update(['is_active' => $package->is_active ? 0 : 1]);

        return back()->with('ok', 'تم التحديث.');
    }

    public function destroy(PointPackage $package)
    {
        $package->delete();

        return back()->with('ok', 'تم حذف الحزمة.');
    }

    private function validateData(Request $request): array
    {
        $v = $request->validate([
            'label'          => ['nullable', 'string', 'max:80'],
            'points'         => ['required', 'integer', 'min:1', 'max:100000000'],
            'price_amount'   => ['required', 'numeric', 'min:0', 'max:99999999'],
            'price_currency' => ['nullable', 'string', 'size:3'],
            'sort_order'     => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        return [
            'label'          => $v['label'] ?? null,
            'points'         => (int) $v['points'],
            'price_amount'   => $v['price_amount'],
            'price_currency' => strtoupper($v['price_currency'] ?? 'JOD'),
            'sort_order'     => (int) ($v['sort_order'] ?? 0),
            'is_active'      => $request->boolean('is_active') ? 1 : 0,
        ];
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\FeatureCatalog;

class PlansController extends Controller
{
    /** المنصّات المتاحة للمزايا */
    private const PLATFORMS = ['all', 'diploma', 'tools', 'library', 'www'];

    public function index()
    {
        $plans = Plan::withCount('features')
            ->orderBy('sort_order')->orderBy('level')
            ->get();

        return view('admin.plans.index', compact('plans'));
    }

    public function create()
    {
        return view('admin.plans.form', [
            'plan'    => new Plan(['currency' => 'JOD', 'duration_days' => 30, 'level' => 1, 'is_active' => 1]),
            'catalog' => $this->catalog(),
        ]);
    }

    /** كتالوج المزايا المتاحة (مُستخرَج من كود المنصّات) */
    private function catalog()
    {
        return FeatureCatalog::where('is_active', 1)
            ->orderBy('platform')->orderBy('sort_order')->get();
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request, null);
        $plan = Plan::create($data);

        return redirect()->route('admin.plans.edit', $plan)->with('ok', 'تم إنشاء الخطة. أضِف المزايا الآن.');
    }

    public function edit(Plan $plan)
    {
        $plan->load('features');

        return view('admin.plans.form', [
            'plan'    => $plan,
            'catalog' => $this->catalog(),
        ]);
    }

    public function update(Request $request, Plan $plan)
    {
        $data = $this->validateData($request, $plan->id);
        $plan->update($data);

        return redirect()->route('admin.plans.edit', $plan)->with('ok', 'تم تحديث الخطة.');
    }

    public function destroy(Plan $plan)
    {
        if ($plan->subscriptions()->exists()) {
            return back()->with('err', 'لا يمكن حذف خطة مرتبطة باشتراكات.');
        }
        $plan->delete();

        return redirect()->route('admin.plans.index')->with('ok', 'تم حذف الخطة.');
    }

    public function toggle(Plan $plan)
    {
        $plan->update(['is_active' => $plan->is_active ? 0 : 1]);
        return back()->with('ok', 'تم التحديث.');
    }

    public function addFeature(Request $request, Plan $plan)
    {
        $v = $request->validate([
            'catalog_id' => ['required', 'integer', 'exists:feature_catalog,id'],
            'value'      => ['nullable', 'string', 'max:191'],
        ]);

        $cf = FeatureCatalog::findOrFail($v['catalog_id']);

        PlanFeature::updateOrCreate(
            ['plan_id' => $plan->id, 'platform' => $cf->platform, 'feature_key' => $cf->feature_key],
            ['label' => $cf->label, 'value' => ($v['value'] ?? '') !== '' ? $v['value'] : $cf->default_value]
        );

        return back()->with('ok', 'تمت إضافة/تحديث الميزة «' . $cf->label . '».');
    }

    public function deleteFeature(PlanFeature $feature)
    {
        $planId = $feature->plan_id;
        $feature->delete();

        return redirect()->route('admin.plans.edit', $planId)->with('ok', 'تم حذف الميزة.');
    }

    private function validateData(Request $request, ?int $id): array
    {
        $v = $request->validate([
            'name'          => ['required', 'string', 'max:120'],
            'slug'          => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9\-]+$/', Rule::unique('plans', 'slug')->ignore($id)],
            'level'         => ['required', 'integer', 'min:1', 'max:255'],
            'price'         => ['required', 'numeric', 'min:0', 'max:99999999'],
            'currency'      => ['required', 'string', 'size:3'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:36500'],
            'grant_points'  => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'sort_order'    => ['nullable', 'integer', 'min:0', 'max:65535'],
            'description'   => ['nullable', 'string', 'max:500'],
        ]);

        $slug = $v['slug'] ?? '';
        if ($slug === '') {
            $slug = Str::slug($v['name']) ?: ('plan-' . Str::lower(Str::random(6)));
        }
        // ضمان فرادة الـ slug المولّد
        $base = $slug; $i = 2;
        while (Plan::where('slug', $slug)->when($id, fn ($q) => $q->where('id', '<>', $id))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return [
            'name'          => $v['name'],
            'slug'          => $slug,
            'level'         => (int) $v['level'],
            'price'         => $v['price'],
            'currency'      => strtoupper($v['currency']),
            'duration_days' => (int) $v['duration_days'],
            'grant_points'  => (int) ($v['grant_points'] ?? 0),
            'sort_order'    => (int) ($v['sort_order'] ?? 0),
            'description'   => $v['description'] ?? null,
            'is_active'     => $request->boolean('is_active') ? 1 : 0,
        ];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PointPackage;
use Illuminate\Http\JsonResponse;

/**
 * كتالوج عام للعرض في الواجهة الموحّدة (أوث): الخطط المفعّلة + كل مزاياها عبر
 * المنصّات + سلّم أسعار حزم النقاط. قراءة فقط، عديمة الأثر.
 */
class CatalogController extends Controller
{
    /** GET /api/v1/catalog — الخطط (بكل المزايا) + حزم النقاط. */
    public function index(): JsonResponse
    {
        $plans = Plan::with(['features' => fn ($q) => $q->orderBy('platform')])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('level')
            ->get()
            ->map(fn (Plan $p) => [
                'id'            => (int) $p->id,
                'name'          => $p->name,
                'slug'          => $p->slug,
                'level'         => (int) $p->level,
                'price'         => $p->price,
                'currency'      => $p->currency ?? 'JOD',
                'duration_days' => (int) $p->duration_days,
                'grant_points'  => (int) $p->grant_points,
                'features'      => ($p->features ?? collect())->map(fn ($f) => [
                    'platform'     => $f->platform,
                    'feature_key'  => $f->feature_key,
                    'feature_text' => $f->label,
                    'value'        => $f->value,
                ])->values(),
            ]);

        $packages = PointPackage::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('points')
            ->get()
            ->map(fn (PointPackage $pkg) => [
                'points'         => (int) $pkg->points,
                'price_amount'   => $pkg->price_amount,
                'price_currency' => $pkg->price_currency,
                'label'          => $pkg->label,
            ]);

        return response()->json([
            'ok'       => true,
            'plans'    => $plans,
            'packages' => $packages,
        ]);
    }
}

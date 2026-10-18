<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * نقطة قراءة (للدبلوما): قائمة الخطط ومزاياها الخام.
 * عديمة الأثر.
 */
class PlansController extends Controller
{
    /**
     * GET /api/v1/plans?active=1&platform=diploma — الخطط + المزايا.
     * active=1 يُرشّح المفعّلة. platform يُرشّح المزايا للمنصّة المطلوبة + 'all' (افتراضي diploma).
     */
    public function index(Request $request): JsonResponse
    {
        $v = $request->validate([
            'active'   => ['nullable', 'in:0,1'],
            'platform' => ['nullable', 'in:diploma,tools,library,www,all'],
        ]);

        $platform = $v['platform'] ?? 'diploma';

        $query = Plan::with([
            'features' => fn ($q) => $q->whereIn('platform', [$platform, 'all']),
        ])->orderBy('sort_order')->orderBy('level');
        if (($v['active'] ?? null) === '1') {
            $query->where('is_active', true);
        }

        $plans = $query->get()->map(fn (Plan $p) => [
            'id'            => (int) $p->id,
            'name'          => $p->name,
            'slug'          => $p->slug,
            'level'         => (int) $p->level,
            'price'         => $p->price,
            'duration_days' => (int) $p->duration_days,
            'grant_points'  => (int) $p->grant_points,
            'features'      => ($p->features ?? collect())->map(fn ($f) => [
                'platform'     => $f->platform,
                'feature_key'  => $f->feature_key,
                'feature_text' => $f->label, // اسم بشري لعرضه في صفحة الترقية (وإلا يظهر المفتاح الخام)
                'value'        => $f->value,
                'limits_json'  => $f->value_json, // JSON الخام: {limit, per_day, messages_per_day, ...}
            ])->values(),
        ]);

        return response()->json([
            'ok'    => true,
            'plans' => $plans,
        ]);
    }
}

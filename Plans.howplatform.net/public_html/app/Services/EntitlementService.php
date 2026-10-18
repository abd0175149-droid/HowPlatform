<?php

namespace App\Services;

use App\Models\Subscription;

/**
 * يحلّ صلاحيات المستخدم: الخطّة الفعّالة (الأعلى رتبة) + مزاياها عبر المنصّات.
 */
class EntitlementService
{
    public function forUser(int $userId): array
    {
        $subs = Subscription::with(['plan.features'])
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('end_at')->orWhere('end_at', '>', now());
            })
            ->get();

        if ($subs->isEmpty()) {
            return ['has_plan' => false, 'plan' => null, 'features' => [], 'features_flat' => []];
        }

        // الأعلى رتبة، ثم الأبعد انتهاءً
        $best = $subs->sort(function ($a, $b) {
            $la = (int) (optional($a->plan)->level ?? 0);
            $lb = (int) (optional($b->plan)->level ?? 0);
            if ($la !== $lb) return $lb <=> $la;
            return (optional($b->end_at)->timestamp ?? 0) <=> (optional($a->end_at)->timestamp ?? 0);
        })->first();

        $plan = $best->plan;
        $features = [];
        $flat = [];
        foreach (($plan->features ?? []) as $f) {
            $features[$f->platform][$f->feature_key] = $f->value;
            $flat[$f->feature_key] = $f->value;
        }

        return [
            'has_plan'      => true,
            'plan'          => [
                'id'    => (int) $plan->id,
                'slug'  => $plan->slug,
                'name'  => $plan->name,
                'level' => (int) $plan->level,
            ],
            'end_at'        => optional($best->end_at)->toIso8601String(),
            'grant_points'  => (int) $plan->grant_points,
            'features'      => $features,      // مجمّعة حسب المنصّة
            'features_flat' => $flat,          // feature_key => value
        ];
    }
}

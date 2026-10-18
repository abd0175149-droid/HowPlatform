<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * نقطة قراءة (للدبلوما): اشتراك المستخدم الحالي + القادم.
 * عديمة الأثر.
 */
class SubscriptionsController extends Controller
{
    /** GET /api/v1/subscriptions?user_id= — الاشتراك الحالي والمجدول القادم. */
    public function index(Request $request): JsonResponse
    {
        $v = $request->validate(['user_id' => ['required', 'integer', 'min:1']]);
        $userId = (int) $v['user_id'];

        // الحالي: فعّال + لم ينتهِ بعد، الأبعد انتهاءً.
        $current = Subscription::with('plan')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where('end_at', '>', now())
            ->orderByDesc('end_at')
            ->first();

        // القادم: مجدول + يبدأ مستقبلًا، الأقرب بدءًا.
        $upcoming = Subscription::with('plan')
            ->where('user_id', $userId)
            ->where('status', 'scheduled')
            ->where('start_at', '>', now())
            ->orderBy('start_at')
            ->first();

        // السجلّ الكامل (الأحدث أولًا) — للعرض في الواجهة الموحّدة.
        $history = Subscription::with('plan')
            ->where('user_id', $userId)
            ->orderByDesc('start_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn ($s) => [
                'id'       => (int) $s->id,
                'plan_id'  => (int) $s->plan_id,
                'name'     => optional($s->plan)->name,
                'level'    => (int) (optional($s->plan)->level ?? 0),
                'status'   => $s->status,
                'start_at' => optional($s->start_at)->toIso8601String(),
                'end_at'   => optional($s->end_at)->toIso8601String(),
                'source'   => $s->source,
            ])->values();

        return response()->json([
            'ok'      => true,
            'current' => $current ? [
                'id'      => (int) $current->id,
                'plan_id' => (int) $current->plan_id,
                'name'    => optional($current->plan)->name,
                'level'   => (int) (optional($current->plan)->level ?? 0),
                'end_at'  => optional($current->end_at)->toIso8601String(),
            ] : null,
            'upcoming' => $upcoming ? [
                'id'       => (int) $upcoming->id,
                'plan_id'  => (int) $upcoming->plan_id,
                'name'     => optional($upcoming->plan)->name,
                'level'    => (int) (optional($upcoming->plan)->level ?? 0),
                'start_at' => optional($upcoming->start_at)->toIso8601String(),
            ] : null,
            'history' => $history,
        ]);
    }
}

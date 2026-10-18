<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * نقطة قراءة إدارية (تستدعيها الدبلوما من الخادم): إحصاءات الاشتراكات.
 * عديمة الأثر.
 */
class AdminController extends Controller
{
    /** GET /api/v1/admin/subscription-stats — توزيع المشتركين الفعّالين حسب اسم الخطة. */
    public function subscriptionStats(): JsonResponse
    {
        $rows = Subscription::query()
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('subscriptions.status', 'active')
            ->where('subscriptions.end_at', '>', now())
            ->groupBy('plans.name')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get([
                'plans.name as label',
                DB::raw('COUNT(*) as users_count'),
            ]);

        $distribution = $rows->map(fn ($r) => [
            'label'       => $r->label,
            'users_count' => (int) $r->users_count,
        ]);

        return response()->json([
            'ok'           => true,
            'distribution' => $distribution,
        ]);
    }
}

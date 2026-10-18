<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PointPackage;
use App\Services\EntitlementService;

/**
 * صفحة تفاصيل الخطط وأسعار النقاط (للمستخدم).
 * عرض فقط: تعرض الخطط المفعّلة ومزاياها + سلّم أسعار حزم النقاط،
 * وتتيح استبدال كود بطاقة. الشراء يتم عبر البطاقات (لا دفع إلكتروني).
 */
class PlansController extends Controller
{
    public function index(EntitlementService $entitlements)
    {
        $uid = (int) (session('auth_user')['id'] ?? 0);

        // الخطط المفعّلة + كل مزاياها عبر المنصّات
        $plans = Plan::with(['features' => fn ($q) => $q->orderBy('platform')])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('level')
            ->get();

        // حزم النقاط المسعّرة
        $packages = PointPackage::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('points')
            ->get();

        // الخطّة الحالية للمستخدم (لتمييزها)
        $ent = $entitlements->forUser($uid);
        $currentPlanId = $ent['plan']['id'] ?? null;

        return view('user.plans', [
            'plans'         => $plans,
            'packages'      => $packages,
            'currentPlanId' => $currentPlanId,
            'platformLabels' => [
                'diploma' => 'منصة الدبلوم',
                'tools'   => 'الأدوات',
                'library' => 'المكتبة',
                'www'     => 'الموقع',
                'all'     => 'كل المنصّات',
            ],
        ]);
    }
}

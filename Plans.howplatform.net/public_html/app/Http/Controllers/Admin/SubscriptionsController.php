<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Plan;
use App\Services\UserDirectory;
use Illuminate\Http\Request;

/**
 * قائمة الاشتراكات العامة (كل المستخدمين) مع فلاتر الحالة/الخطة.
 * إدارة كل اشتراك (تمديد/إلغاء) تبقى في شاشة المستخدم الواحد (admin.users).
 */
class SubscriptionsController extends Controller
{
    public function index(Request $request, UserDirectory $dir)
    {
        $q = Subscription::with('plan')->latest('id');

        $status = $request->string('status')->value();
        if ($status === 'active_now') {
            $q->where('status', 'active')
              ->where(fn ($w) => $w->whereNull('end_at')->orWhere('end_at', '>', now()));
        } elseif ($status) {
            $q->where('status', $status);
        }
        if ($plan = (int) $request->input('plan_id')) {
            $q->where('plan_id', $plan);
        }

        $subs  = $q->paginate(30)->withQueryString();
        $names = $dir->names($subs->pluck('user_id')->all());

        $activeNow = fn () => Subscription::where('status', 'active')
            ->where(fn ($w) => $w->whereNull('end_at')->orWhere('end_at', '>', now()));

        return view('admin.subscriptions.index', [
            'subs'  => $subs,
            'names' => $names,
            'plans' => Plan::orderBy('name')->get(),
            'f'     => $request->only('status', 'plan_id'),
            'stats' => [
                'total'      => Subscription::count(),
                'active_now' => $activeNow()->count(),
                'scheduled'  => Subscription::where('status', 'scheduled')->count(),
                'expired'    => Subscription::where('status', 'expired')->count(),
                'canceled'   => Subscription::where('status', 'canceled')->count(),
            ],
        ]);
    }
}

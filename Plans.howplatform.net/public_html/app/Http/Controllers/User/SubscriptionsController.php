<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Subscription;

/**
 * صفحة اشتراكات المستخدم: الاشتراك الفعّال الحالي + سجلّ الاشتراكات السابقة.
 */
class SubscriptionsController extends Controller
{
    public function index()
    {
        $uid = (int) (session('auth_user')['id'] ?? 0);

        $subs = Subscription::with('plan')
            ->where('user_id', $uid)
            ->orderByDesc('start_at')
            ->orderByDesc('id')
            ->get();

        // الاشتراك الفعّال الحالي (نشط ولم ينتهِ بعد، الأبعد انتهاءً)
        $current = $subs->first(function ($s) {
            return $s->status === 'active'
                && (is_null($s->end_at) || $s->end_at->isFuture());
        });

        return view('user.subscriptions', [
            'current' => $current,
            'subs'    => $subs,
        ]);
    }
}

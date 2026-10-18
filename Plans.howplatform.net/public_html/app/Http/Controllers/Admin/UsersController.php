<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Card;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\WalletAccount;
use App\Models\WalletLedger;
use App\Services\WalletService;
use App\Services\UserDirectory;

class UsersController extends Controller
{
    /** بحث/عرض مستخدم بالاسم/الإيميل/الهاتف أو المعرّف user_id */
    public function index(Request $request, UserDirectory $dir)
    {
        // q = البحث النصّي (اسم/إيميل/هاتف/معرّف)؛ u = معرّف مباشر (من روابط النتائج).
        $q   = trim((string) ($request->input('q', $request->input('u', ''))));
        $data = ['q' => $q, 'searched' => $request->has('q') || $request->has('u'), 'results' => [], 'academic' => [], 'uid' => 0, 'profile' => null];

        if ($q !== '') {
            // رقم قصير (≤7) أو رابط u= = معرّف مباشر؛ الأرقام الأطول (هواتف) تمرّ عبر البحث
            if ($request->has('u') || (ctype_digit($q) && strlen($q) <= 7)) {
                $data['uid'] = (int) $q;
            } else {
                $results = $dir->search($q, 50, true);
                if (count($results) === 1) {
                    $data['uid'] = (int) $results[0]['id'];
                } else {
                    $data['results']  = $results;
                    $data['academic'] = $dir->academic(array_column($results, 'id'));
                }
            }
        }

        $uid = (int) $data['uid'];
        if ($uid > 0) {
            $data['profile']     = $dir->resolve($uid);
            $data['account']     = $dir->account($uid);
            $data['acad']        = $dir->academic([$uid])[$uid] ?? null;
            $data['cards']       = Card::with('plan')->where('redeemed_by', $uid)->latest('redeemed_at')->limit(20)->get();
            $data['subs']        = Subscription::with('plan')->where('user_id', $uid)->orderByDesc('id')->get();
            $data['balance']     = (int) (WalletAccount::where('user_id', $uid)->value('balance') ?? 0);
            $data['ledger']      = WalletLedger::where('user_id', $uid)->orderByDesc('id')->limit(20)->get();
            $data['plans']       = Plan::where('is_active', 1)->orderBy('level')->get();
            $data['adjustToken'] = (string) Str::uuid();
        }

        return view('admin.users.index', $data);
    }

    /** بحث AJAX عن مستخدم بالاسم/الإيميل/الهاتف/المعرّف — يُعيد JSON [{id,name,email,phone}]. */
    public function search(Request $request, UserDirectory $dir)
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        return response()->json($dir->search($q, 15));
    }

    public function grantSubscription(Request $request)
    {
        $v = $request->validate([
            'user_id'      => ['required', 'integer', 'min:1'],
            'plan_id'      => ['required', 'integer', 'exists:plans,id'],
            'days'         => ['nullable', 'integer', 'min:1', 'max:3650'],
            'grant_points' => ['nullable', 'boolean'],
        ]);

        $plan  = Plan::findOrFail($v['plan_id']);
        $days  = (int) ($v['days'] ?? $plan->duration_days);
        $start = now();

        $sub = Subscription::create([
            'user_id'    => $v['user_id'],
            'plan_id'    => $plan->id,
            'status'     => 'active',
            'start_at'   => $start,
            'end_at'     => (clone $start)->addDays($days),
            'source'     => 'admin',
            'created_by' => (int) (session('auth_user')['id'] ?? 0) ?: null,
        ]);

        if ($request->boolean('grant_points') && (int) $plan->grant_points > 0) {
            app(WalletService::class)->earn($v['user_id'], (int) $plan->grant_points, [
                'service_client'  => 'plans',
                'idempotency_key' => 'plans:grant:sub:' . $sub->id,
                'event_type'      => 'plans.subscription.granted',
                'event_ref_type'  => 'subscription',
                'event_ref_id'    => (string) $sub->id,
                'description'      => 'منحة نقاط اشتراك (منح إداري)',
            ]);
        }

        \App\Models\AdminNotification::notify('subscription', 'منح اشتراك «' . $plan->name . '» للمستخدم ' . $v['user_id'], [
            'icon' => 'fa-id-card', 'color' => '#6366f1', 'link' => '/admin/users?u=' . $v['user_id'],
        ]);

        return $this->backToUser($v['user_id'], 'تم منح الاشتراك (#' . $sub->id . ').');
    }

    public function extendSubscription(Request $request, Subscription $subscription)
    {
        $v = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:3650']]);

        $base = ($subscription->end_at && $subscription->end_at->isFuture()) ? $subscription->end_at : now();
        $subscription->update([
            'end_at' => (clone $base)->addDays((int) $v['days']),
            'status' => 'active',
        ]);

        return $this->backToUser($subscription->user_id, 'تم تمديد الاشتراك ' . $v['days'] . ' يومًا.');
    }

    public function cancelSubscription(Subscription $subscription)
    {
        $subscription->update(['status' => 'canceled']);
        return $this->backToUser($subscription->user_id, 'تم إلغاء الاشتراك.');
    }

    public function adjustWallet(Request $request)
    {
        $v = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
            'amount'  => ['required', 'integer', 'not_in:0', 'min:-100000000', 'max:100000000'],
            'reason'  => ['required', 'string', 'max:191'],
            'token'   => ['required', 'string', 'max:64'],
        ]);

        $res = app(WalletService::class)->adjust((int) $v['user_id'], (int) $v['amount'], [
            'service_client'  => 'plans',
            'idempotency_key' => 'plans:adjust:' . $v['token'],
            'event_type'      => 'plans.admin.adjust',
            'description'     => $v['reason'],
        ]);

        if (($res['code'] ?? 0) === 200) {
            return $this->backToUser($v['user_id'], 'تم تعديل المحفظة. الرصيد الآن: ' . ($res['body']['balance'] ?? '?'));
        }
        if (($res['code'] ?? 0) === 402) {
            return $this->backToUser($v['user_id'], null, 'الرصيد لا يكفي للخصم المطلوب.');
        }
        return $this->backToUser($v['user_id'], null, 'تعذّر التعديل.');
    }

    private function backToUser(int $uid, ?string $ok, ?string $err = null)
    {
        $redirect = redirect()->route('admin.users.index', ['u' => $uid]);
        return $err ? $redirect->with('err', $err) : $redirect->with('ok', $ok);
    }
}

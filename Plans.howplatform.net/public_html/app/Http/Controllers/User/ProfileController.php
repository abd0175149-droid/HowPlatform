<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Subscription;
use App\Services\EarningService;
use App\Services\CardRedemptionService;
use App\Services\WalletService;
use App\Services\EntitlementService;

class ProfileController extends Controller
{
    public function show(EarningService $earning, WalletService $wallet, EntitlementService $entitlements)
    {
        $au  = session('auth_user') ?? [];
        $uid = (int) ($au['id'] ?? 0);

        $code = $earning->referralCode($uid);
        $link = 'https://auth.howplatform.net/register?ref=' . $code;

        // الاشتراك الفعّال الحالي (لعرض تفاصيله)
        $currentSub = Subscription::with('plan')
            ->where('user_id', $uid)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('end_at')->orWhere('end_at', '>', now());
            })
            ->orderByDesc('end_at')
            ->first();

        return view('user.profile', [
            'name'       => $au['name'] ?? '',
            'email'      => $au['email'] ?? '',
            'code'       => $code,
            'link'       => $link,
            'refStats'   => $earning->referralStats($uid),
            'balance'    => $wallet->balance($uid),
            'ent'        => $entitlements->forUser($uid),
            'currentSub' => $currentSub,
        ]);
    }

    /** صفحة هبوط تفعيل بطاقة (يصلها المستخدم من QR code: /redeem?code=...). */
    public function redeemLanding(Request $request)
    {
        return view('user.redeem', [
            'code' => (string) $request->query('code', ''),
        ]);
    }

    public function redeemCard(Request $request, CardRedemptionService $svc)
    {
        $v = $request->validate([
            'code' => ['required', 'string', 'max:60'],
        ]);

        $uid = (int) (session('auth_user')['id'] ?? 0);

        $res = $svc->redeem($v['code'], $uid, 'user');

        if (!empty($res['ok'])) {
            $msg = 'تم استبدال البطاقة بنجاح.';
            if (!empty($res['subscription_id'])) {
                $msg .= ' فُعّل اشتراكك';
                if (!empty($res['end_at'])) $msg .= ' حتى ' . $res['end_at'];
                $msg .= '.';
            }
            if (!empty($res['points_granted'])) {
                $msg .= ' أُضيفت ' . $res['points_granted'] . ' نقطة إلى محفظتك.';
            }
            return back()->with('ok', $msg);
        }

        return back()->with('err', 'تعذّر استبدال البطاقة: ' . $this->errLabel($res['error'] ?? 'unknown'));
    }

    private function errLabel(string $err): string
    {
        return [
            'card_not_found'   => 'الكود غير موجود',
            'already_redeemed' => 'البطاقة مستبدَلة مسبقًا',
            'card_disabled'    => 'البطاقة معطّلة',
            'card_expired'     => 'انتهت صلاحية البطاقة',
            'invalid_input'    => 'مدخلات غير صحيحة',
        ][$err] ?? $err;
    }
}

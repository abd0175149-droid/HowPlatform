<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * استبدال بطاقة: عملية ذرّية تُفعّل اشتراكًا و/أو تمنح نقاطًا.
 * - قفل صفّ البطاقة يمنع الاستبدال المزدوج.
 * - منح النقاط عبر WalletService بمفاتيح idempotency مرتبطة بالبطاقة (لا تكرار).
 */
class CardRedemptionService
{
    public function __construct(private WalletService $wallet) {}

    /** تطبيع كود الإدخال: أحرف كبيرة + إزالة الفواصل/المسافات. */
    public static function normalize(string $code): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($code)));
    }

    /**
     * @return array ['ok'=>bool,'code'=>int,'error'=>?string, ...result]
     */
    /** قيم enum المسموحة لعمود subscriptions.source */
    private const SUB_SOURCES = ['card', 'admin', 'promo', 'migration'];

    public function redeem(string $codeInput, int $userId, string $source = 'card'): array
    {
        $code = self::normalize($codeInput);
        if ($code === '' || $userId <= 0) {
            return ['ok' => false, 'code' => 422, 'error' => 'invalid_input'];
        }

        // مصدر الاشتراك يجب أن يكون قيمة enum صحيحة؛ أي فاعل آخر (user/seller/api…) = استبدال بطاقة = 'card'
        $subSource = in_array($source, self::SUB_SOURCES, true) ? $source : 'card';

        $result = DB::transaction(function () use ($code, $userId, $source, $subSource) {
            $card = Card::where('code', $code)->lockForUpdate()->first();

            if (!$card) {
                return ['ok' => false, 'code' => 404, 'error' => 'card_not_found'];
            }
            if ($card->status === 'redeemed') {
                return ['ok' => false, 'code' => 409, 'error' => 'already_redeemed'];
            }
            if ($card->status !== 'active') {
                return ['ok' => false, 'code' => 409, 'error' => 'card_disabled'];
            }
            if ($card->expires_at && $card->expires_at->isPast()) {
                return ['ok' => false, 'code' => 410, 'error' => 'card_expired'];
            }

            // 1) علّم البطاقة مستبدَلة
            $card->update([
                'status'      => 'redeemed',
                'redeemed_by' => $userId,
                'redeemed_at' => now(),
            ]);

            $result = ['ok' => true, 'code' => 200, 'card_id' => $card->id, 'subscription_id' => null, 'points_granted' => 0];

            // 2) فعّل اشتراكًا إن كانت بطاقة اشتراك/مختلطة ولها خطة
            if (in_array($card->type, ['subscription', 'mixed'], true) && $card->plan_id) {
                $plan = $card->plan; // belongsTo
                $start = now();
                // مدّة البطاقة (إن حدّدها البائع) تَغلِب مدّة الخطة الافتراضية
                $days  = (int) ($card->duration_days ?: ($plan->duration_days ?? 30));
                $end   = (clone $start)->addDays($days);

                $sub = Subscription::create([
                    'user_id'    => $userId,
                    'plan_id'    => $plan->id,
                    'status'     => 'active',
                    'start_at'   => $start,
                    'end_at'     => $end,
                    'source'     => $subSource,
                    'card_id'    => $card->id,
                ]);
                $result['subscription_id']      = $sub->id;
                $result['end_at']               = $end->toDateString();   // ليعرضه عميل الدبلوما في رسالة النجاح
                $result['action_applied']       = 'new_activation';

                // منحة نقاط الخطة (إن وُجدت)
                if ((int) $plan->grant_points > 0) {
                    $this->wallet->earn($userId, (int) $plan->grant_points, [
                        'service_client'  => 'plans',
                        'idempotency_key' => 'plans:card:' . $card->id . ':subpts',
                        'event_type'      => 'plans.subscription.granted',
                        'event_ref_type'  => 'card',
                        'event_ref_id'    => (string) $card->id,
                        'description'     => 'منحة نقاط اشتراك من بطاقة ' . $card->code,
                    ]);
                    $result['points_granted'] += (int) $plan->grant_points;
                }
            }

            // 3) نقاط البطاقة المباشرة (points/mixed)
            if ((int) $card->points > 0) {
                $this->wallet->earn($userId, (int) $card->points, [
                    'service_client'  => 'plans',
                    'idempotency_key' => 'plans:card:' . $card->id . ':pts',
                    'event_type'      => 'plans.card.points',
                    'event_ref_type'  => 'card',
                    'event_ref_id'    => (string) $card->id,
                    'description'     => 'نقاط بطاقة ' . $card->code,
                ]);
                $result['points_granted'] += (int) $card->points;
            }

            return $result;
        }, 3);

        // إشعار للأدمن بعد نجاح الاستبدال (مفصول عن معاملة المال — لا يكسرها)
        if (! empty($result['ok'])) {
            $body = trim((! empty($result['subscription_id']) ? 'فُعّل اشتراك #' . $result['subscription_id'] . '. ' : '')
                . ((int) ($result['points_granted'] ?? 0) > 0 ? 'مُنحت ' . $result['points_granted'] . ' نقطة.' : ''));
            \App\Models\AdminNotification::notify('card_redeemed', 'تم استبدال بطاقة ' . $code . ' للمستخدم ' . $userId, [
                'icon'  => 'fa-ticket',
                'color' => '#16a34a',
                'link'  => '/admin/users?u=' . $userId,
                'body'  => $body !== '' ? $body : null,
            ]);
        }

        return $result;
    }
}

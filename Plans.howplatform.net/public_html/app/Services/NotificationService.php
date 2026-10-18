<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Subscription;

/**
 * إشعارات لوحة الأدمن — أحداث حديثة مُشتقّة من البيانات (استبدال بطاقات، اشتراكات جديدة).
 */
class NotificationService
{
    public function recent(int $limit = 8): array
    {
        $items = [];

        try {
            foreach (Card::where('status', 'redeemed')->whereNotNull('redeemed_at')
                ->orderByDesc('redeemed_at')->limit($limit)->get() as $c) {
                $items[] = [
                    'icon'  => 'fa-ticket',
                    'color' => '#16a34a',
                    'text'  => 'تم استبدال بطاقة ' . $c->code,
                    'time'  => optional($c->redeemed_at)->diffForHumans(),
                    'ts'    => optional($c->redeemed_at)->timestamp ?? 0,
                ];
            }

            foreach (Subscription::with('plan')->orderByDesc('created_at')->limit($limit)->get() as $s) {
                $items[] = [
                    'icon'  => 'fa-id-card',
                    'color' => '#6366f1',
                    'text'  => 'اشتراك جديد (' . (optional($s->plan)->name ?? 'خطة') . ') للمستخدم ' . $s->user_id,
                    'time'  => optional($s->created_at)->diffForHumans(),
                    'ts'    => optional($s->created_at)->timestamp ?? 0,
                ];
            }
        } catch (\Throwable $e) {
            return [];
        }

        usort($items, fn ($a, $b) => $b['ts'] <=> $a['ts']);
        return array_slice($items, 0, $limit);
    }
}

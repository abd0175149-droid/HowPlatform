<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\UserSubscriptionModel;
use App\Models\VoucherModel;
use App\Services\RewardsService;
use CodeIgniter\Database\RawSql;

class Vouchers extends BaseController
{
    /** POST /api/vouchers/verify */
    public function verify()
    {
        $userId = (int) (session('user_id') ?? 0);
        if (!$userId) {
            return $this->response->setStatusCode(401)->setJSON(['status' => 'unauthenticated']);
        }

        $code = strtoupper(trim(str_replace([' ', '-', '_'], '', (string) $this->request->getPost('code'))));
        if ($code === '') {
            return $this->response->setJSON(['status' => 'unknown']);
        }

        $vModel = new VoucherModel();
        $voucher = $vModel->findByCode($code);

        if (!$voucher) {
            return $this->response->setJSON(['status' => 'unknown']);
        }

        if ((int) $voucher['is_redeemed'] === 1) {
            return $this->response->setJSON(['status' => 'redeemed']);
        }

        $now = date('Y-m-d H:i:s');
        if (!empty($voucher['expires_at']) && $now > $voucher['expires_at']) {
            return $this->response->setJSON(['status' => 'expired']);
        }

        // جلب بيانات الخطة
        $db = db_connect();
        $plan = $db->table('plans')->select('*')->where('id', (int) $voucher['plan_id'])->get()->getRowArray();
        if (!$plan || (int) $plan['is_active'] !== 1) {
            // لو الخطة غير مفعلة نرفض التفعيل بسياسة واضحة
            return $this->response->setJSON(['status' => 'policy_denied']);
        }

        $duration = (int) ($voucher['duration_days'] ?? $plan['default_duration_days'] ?? 30);

        // الاشتراك الحالي/القادم
        $usModel = new UserSubscriptionModel();
        $current = $usModel->getActiveNow($userId);

        $action = 'new_activation';
        $willExpireTo = null;

        if (!$current) {
            $action = 'new_activation';
            $willExpireTo = date('Y-m-d H:i:s', strtotime("+{$duration} days"));
        } else {
            $currentPlan = $db->table('plans')->where('id', (int) $current['plan_id'])->get()->getRowArray();
            $Lv = (int) $plan['level'];
            $Lc = (int) $currentPlan['level'];
            $currentEnd = $current['end_at'];

            if ((int) $voucher['plan_id'] === (int) $current['plan_id']) {
                // تمديد نفس الخطة
                $action = 'extend';
                $willExpireTo = date('Y-m-d H:i:s', strtotime($currentEnd . " +{$duration} days"));
            } elseif ($Lv > $Lc) {
                // ترقية فورية
                $action = 'switch_now';
                $carry = (bool) env('UPGRADE_CARRY_REMAINING', false);
                if ($carry) {
                    $remainingDays = max(0, (int) ceil((strtotime($currentEnd) - time()) / 86400));
                    $willExpireTo = date('Y-m-d H:i:s', strtotime("+" . ($duration + $remainingDays) . " days"));
                } else {
                    $willExpireTo = date('Y-m-d H:i:s', strtotime("+{$duration} days"));
                }
            } else {
                // خطة أدنى: سياسة التخفيض الافتراضية queue
                $policy = (string) env('DOWNGRADE_POLICY', 'queue');
                if ($policy === 'deny') {
                    return $this->response->setJSON(['status' => 'policy_denied']);
                }
                $action = 'queue';
                $willExpireTo = date('Y-m-d H:i:s', strtotime($currentEnd . " +{$duration} days"));
            }
        }

        return $this->response->setJSON([
            'status' => 'valid',
            'data'   => [
                'plan_id'        => (int) $plan['id'],
                'plan_name'      => $plan['name'],
                'level'          => (int) $plan['level'],
                'duration_days'  => $duration,
                'action'         => $action,
                'will_expire_to' => $willExpireTo,
            ],
        ]);
    }

    /** POST /api/vouchers/redeem (ذرّي + FOR UPDATE) */
    public function redeem()
    {
        $userId = (int) (session('user_id') ?? 0);
        if (!$userId) {
            return $this->response->setStatusCode(401)->setJSON(['status' => 'unauthenticated']);
        }

        $code = strtoupper(trim(str_replace([' ', '-', '_'], '', (string) $this->request->getPost('code'))));
        if ($code === '') {
            return $this->response->setJSON(['status' => 'unknown']);
        }

        $db = db_connect();
        $db->transStart();

        try {
            // 🔒 قفل صف الفاوتشر
            $voucher = $db->query(
                'SELECT * FROM vouchers WHERE code = ? FOR UPDATE',
                [$code]
            )->getRowArray();

            if (!$voucher) {
                $db->transRollback();
                return $this->response->setJSON(['status' => 'unknown']);
            }
            if ((int) $voucher['is_redeemed'] === 1) {
                $db->transRollback();
                return $this->response->setJSON(['status' => 'redeemed']);
            }
            $now = date('Y-m-d H:i:s');
            if (!empty($voucher['expires_at']) && $now > $voucher['expires_at']) {
                $db->transRollback();
                return $this->response->setJSON(['status' => 'expired']);
            }

            // 🔒 قراءة الاشتراك النشط بقفل منطقي (نقفل كل اشتراكات المستخدم المؤثرة)
            $lockSubs = $db->query(
                'SELECT * FROM user_subscriptions WHERE user_id = ? AND (status IN ("active","scheduled")) FOR UPDATE',
                [$userId]
            )->getResultArray();

            // جلب الخطة
            $plan = $db->table('plans')->where('id', (int) $voucher['plan_id'])->get()->getRowArray();
            if (!$plan || (int) $plan['is_active'] !== 1) {
                $db->transRollback();
                return $this->response->setJSON(['status' => 'policy_denied']);
            }

            $duration = (int) ($voucher['duration_days'] ?? $plan['default_duration_days'] ?? 30);

            // ابحث عن active الآن
            $current = null;
            foreach ($lockSubs as $s) {
                if ($s['status'] === 'active' && $s['end_at'] > $now) {
                    $current = $s;
                    break;
                }
            }

            $actionApplied = null;
            $newExpiry     = null;
            $subscriptionId = null;

            if (!$current) {
                // new_activation
                $startAt = $now;
                $endAt   = date('Y-m-d H:i:s', strtotime("+{$duration} days"));
                $db->table('user_subscriptions')->insert([
                    'user_id'      => $userId,
                    'plan_id'      => (int) $plan['id'],
                    'status'       => 'active',
                    'start_at'     => $startAt,
                    'end_at'       => $endAt,
                    'source'       => 'voucher',
                    'voucher_code' => $code,
                ]);
                $subscriptionId = (int)$db->insertID();
                $newExpiry     = $endAt;
                $actionApplied = 'new_activation';
                $this->audit($db, $userId, $subscriptionId, 'redeem', [
                    'action' => $actionApplied,
                    'plan'   => $plan['name'],
                ]);
            } else {
                $currentPlan = $db->table('plans')->where('id', (int) $current['plan_id'])->get()->getRowArray();
                $Lv = (int) $plan['level'];
                $Lc = (int) $currentPlan['level'];

                if ((int) $plan['id'] === (int) $current['plan_id']) {
                    // extend
                    $endAt = date('Y-m-d H:i:s', strtotime($current['end_at'] . " +{$duration} days"));
                    $db->table('user_subscriptions')->update(
                        ['end_at' => $endAt, 'updated_at' => new RawSql('NOW()')],
                        ['id' => (int) $current['id']]
                    );
                    $subscriptionId = (int) $current['id'];
                    $newExpiry     = $endAt;
                    $actionApplied = 'extend';
                    $this->audit($db, $userId, $subscriptionId, 'extend', [
                        'from' => $current['end_at'],
                        'to'   => $endAt,
                        'plan' => $currentPlan['name'],
                    ]);
                } elseif ($Lv > $Lc) {
                    // switch_now (upgrade)
                    $carry = (bool) env('UPGRADE_CARRY_REMAINING', false);
                    $extraDays = 0;
                    if ($carry) {
                        $extraDays = max(0, (int) ceil((strtotime($current['end_at']) - time()) / 86400));
                    }

                    // أنهِ الحالي الآن
                    $db->table('user_subscriptions')->update(
                        ['status' => 'canceled', 'end_at' => $now, 'updated_at' => new RawSql('NOW()')],
                        ['id' => (int) $current['id']]
                    );

                    // أنشئ اشتراكًا جديدًا أعلى
                    $startAt = $now;
                    $endAt   = date('Y-m-d H:i:s', strtotime('+' . ($duration + $extraDays) . ' days'));
                    $db->table('user_subscriptions')->insert([
                        'user_id'      => $userId,
                        'plan_id'      => (int) $plan['id'],
                        'status'       => 'active',
                        'start_at'     => $startAt,
                        'end_at'       => $endAt,
                        'source'       => 'voucher',
                        'voucher_code' => $code,
                    ]);
                    $subscriptionId = (int)$db->insertID();
                    $newExpiry     = $endAt;
                    $actionApplied = 'switch_now';
                    $this->audit($db, $userId, $subscriptionId, 'upgrade', [
                        'from_plan' => $currentPlan['name'],
                        'to_plan'   => $plan['name'],
                        'carry'     => $carry,
                    ]);
                } else {
                    // downgrade: queue or deny
                    $policy = (string) env('DOWNGRADE_POLICY', 'queue');
                    if ($policy === 'deny') {
                        $db->transRollback();
                        return $this->response->setJSON(['status' => 'policy_denied']);
                    }

                    // queue: دمج مع آخر Scheduled لنفس الخطة (إن وجد)
                    $scheduled = $db->table('user_subscriptions')
                        ->where([
                            'user_id' => $userId,
                            'status'  => 'scheduled',
                            'plan_id' => (int) $plan['id'],
                        ])->orderBy('end_at', 'DESC')->get()->getRowArray();

                    if ($scheduled) {
                        $endAt = date('Y-m-d H:i:s', strtotime($scheduled['end_at'] . " +{$duration} days"));
                        $db->table('user_subscriptions')->update(
                            ['end_at' => $endAt, 'updated_at' => new RawSql('NOW()')],
                            ['id' => (int) $scheduled['id']]
                        );
                        $actionApplied = 'merge_queue';
                        $this->audit($db, $userId, (int) $scheduled['id'], 'merge_queue', [
                            'plan' => $plan['name'],
                        ]);
                    } else {
                        $startAt = $current['end_at'];
                        $endAt   = date('Y-m-d H:i:s', strtotime($startAt . " +{$duration} days"));
                        $db->table('user_subscriptions')->insert([
                            'user_id'      => $userId,
                            'plan_id'      => (int) $plan['id'],
                            'status'       => 'scheduled',
                            'start_at'     => $startAt,
                            'end_at'       => $endAt,
                            'source'       => 'voucher',
                            'voucher_code' => $code,
                        ]);
                        $actionApplied = 'downgrade_queue';
                        $this->audit($db, $userId, null, 'downgrade_queue', [
                            'plan' => $plan['name'],
                        ]);
                    }

                    // لا يوجد newExpiry فورًا؛ الحالي لم يتغير
                }
            }

            // تحديث الفاوتشر (استهلاك)
            $db->table('vouchers')->update([
                'is_redeemed'         => 1,
                'redeemed_by_user_id' => $userId,
                'redeemed_at'         => $now,
                'redeemed_ip'         => $this->request->getIPAddress(),
                'redeemed_ua'         => substr((string) $this->request->getUserAgent(), 0, 255),
            ], ['code' => $code]);

            // منح النقاط عند تفعيل الاشتراك (فقط للاشتراكات الجديدة أو التمديد)
            // يتم داخل نفس Transaction
            if ($subscriptionId && in_array($actionApplied, ['new_activation', 'extend', 'switch_now'], true)) {
                // محاولة قراءة النقاط من ميزة الخطة أولاً
                $points = 0;
                $pointsFeature = $db->table('plan_features')
                    ->where('plan_id', (int)$plan['id'])
                    ->where('feature_key', 'نقاط الاشتراك')
                    ->get()
                    ->getRowArray();
                
                if ($pointsFeature && !empty($pointsFeature['limits_json'])) {
                    $limits = json_decode($pointsFeature['limits_json'], true);
                    if (is_array($limits) && isset($limits['points'])) {
                        $points = (int)$limits['points'];
                    }
                }
                
                // إذا لم توجد ميزة، حساب النقاط ديناميكياً
                if ($points <= 0) {
                    $points = $this->calculateSubscriptionPoints($duration);
                }
                
                if ($points > 0) {
                    // التحقق من عدم منح النقاط مرتين
                    $existingPoints = $db->table('rewards_transactions')
                        ->where('user_id', $userId)
                        ->where('reward_type', 'subscription_points')
                        ->where('reward_type_id', $subscriptionId)
                        ->get()
                        ->getRowArray();

                    if (!$existingPoints) {
                        // جلب أو إنشاء سجل النقاط
                        $userPointsRow = $db->table('rewards_users')
                            ->where('user_id', $userId)
                            ->get()
                            ->getRowArray();
                        
                        if (!$userPointsRow) {
                            $db->table('rewards_users')->insert([
                                'user_id' => $userId,
                                'total_points' => 0,
                            ]);
                            $currentPoints = 0;
                        } else {
                            $currentPoints = (int)($userPointsRow['total_points'] ?? 0);
                        }
                        
                        // إضافة النقاط
                        $newTotal = $currentPoints + $points;
                        $db->table('rewards_users')
                            ->where('user_id', $userId)
                            ->update(['total_points' => $newTotal]);
                        
                        // تسجيل المعاملة
                        $db->table('rewards_transactions')->insert([
                            'user_id'       => $userId,
                            'points'        => $points,
                            'reward_type'   => 'subscription_points',
                            'reward_type_id' => $subscriptionId,
                            'description'   => "نقاط اشتراك: {$plan['name']} ({$duration} يوم)",
                            'created_at'    => $now,
                        ]);
                    }
                }
            }

            $db->transCommit();

            return $this->response->setJSON([
                'status'                => 'ok',
                'action_applied'        => $actionApplied,
                'new_subscription_expiry' => $newExpiry,
            ]);
        } catch (\Throwable $e) {
            if ($db->transStatus()) {
                $db->transRollback();
            }
            log_message('error', 'Voucher redeem error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['status' => 'error', 'reason' => 'server_error']);
        }
    }

    /**
     * حساب النقاط حسب مدة الاشتراك
     *
     * @param int $durationDays مدة الاشتراك بالأيام
     * @return int عدد النقاط
     */
    private function calculateSubscriptionPoints(int $durationDays): int
    {
        return match($durationDays) {
            30 => 500,      // شهر واحد
            90 => 1650,     // 3 أشهر
            365 => 3300,    // سنة
            default => (int)($durationDays * 16.67) // حساب ديناميكي
        };
    }

    /** تسجيل حدث تدقيقي */
    private function audit($db, int $userId, ?int $subscriptionId, string $type, array $meta = []): void
    {
        $db->table('subscription_events')->insert([
            'user_id'         => $userId,
            'subscription_id' => $subscriptionId,
            'type'            => $type,
            'metadata'        => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'ip'              => $this->request->getIPAddress(),
            'user_agent'      => substr((string) $this->request->getUserAgent(), 0, 255),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers\Cron;

use App\Controllers\BaseController;
use App\Services\RewardsService;
use CodeIgniter\API\ResponseTrait;

/**
 * RewardsProcessor
 * معالج يومي لتحديث الأيام المتتالية ومنح المكافآت
 * يمكن تشغيله عبر Cron Job كبديل أو مكمل لـ RewardsFilter
 * 
 * HTTP: GET /cron/rewards/process?key=SECRET
 * CLI : php spark rewards:process
 */
class RewardsProcessor extends BaseController
{
    use ResponseTrait;

    protected RewardsService $rewardsService;

    public function __construct()
    {
        $this->rewardsService = new RewardsService();
    }

    /**
     * معالجة المكافآت لجميع المستخدمين النشطين
     */
    public function process(?string $keyOverride = null)
    {
        // 🔐 التحقق من المفتاح (موحّد مع AiJobsRunner: CRON_SECRET)
        $secretKey = (string) (getenv('CRON_SECRET') ?: '');
        $key = $keyOverride ?? $this->request->getGet('key');

        if ($secretKey === '' || $key !== $secretKey) {
            return $this->out([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // ⛔ في وضع plans.earning.mode=api هذا الكرون خطر: يسجّل "دخولًا يوميًا" في Plans
        // لكل مستخدم في الجدول القديم حتى لو لم يدخل فعلًا → يضخّ مكافآت تتابع وهمية.
        // إشارة الدخول الحقيقية = RewardsFilter عند تصفّح فعلي. لذا نعطّله نهائيًا هنا.
        try {
            $plansClient = new \App\Libraries\PlansClient();
            if ($plansClient->earningMode() === 'api') {
                log_message('info', 'RewardsProcessor skipped: earning is handled by Plans (api mode).');
                return $this->out([
                    'success'   => true,
                    'processed' => 0,
                    'errors'    => 0,
                    'total'     => 0,
                    'skipped'   => 'earning_mode_api',
                ]);
            }
        } catch (\Throwable $e) {
            // تعذّر فحص الوضع → تصرّف بحذر ولا تعالج
            return $this->out(['success' => false, 'message' => 'plans mode check failed'], 500);
        }

        $db = db_connect();
        
        // جلب جميع المستخدمين الذين لديهم سجلات في rewards_consecutive_days
        $users = $db->table('rewards_consecutive_days')
            ->select('user_id')
            ->get()
            ->getResultArray();

        $processed = 0;
        $errors = 0;

        foreach ($users as $user) {
            try {
                $userId = (int)$user['user_id'];
                $result = $this->rewardsService->updateConsecutiveDays($userId);
                
                if ($result['rewarded']) {
                    $processed++;
                }
            } catch (\Throwable $e) {
                $errors++;
                log_message('error', "RewardsProcessor error for user {$user['user_id']}: " . $e->getMessage());
            }
        }

        return $this->out([
            'success'   => true,
            'processed' => $processed,
            'errors'    => $errors,
            'total'     => count($users),
        ]);
    }

    /**
     * إخراج JSON آمن للويب والـ CLI
     */
    protected function out(array $data, int $status = 200)
    {
        if (is_cli()) {
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            return $data;
        }

        if (isset($this->response)) {
            return $this->response->setStatusCode($status)->setJSON($data);
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $data;
    }
}


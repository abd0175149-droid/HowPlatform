<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\FateenUsageLogModel;
use App\Services\SubscriptionService;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\API\ResponseTrait;

class FateenAi extends BaseController
{
    use ResponseTrait;

    // الحد الأقصى لعدد الرسائل المحفوظة في جلسة المحادثة (turns)
    private int $maxTurns = 8;

    /**
     * إرسال رسالة للذكاء الاصطناعي (Gemini)
     */
    public function generate(): ResponseInterface
    {
        helper('auth');
        $userId = session('user_id');
        if (! $userId) {
            return $this->failUnauthorized('Unauthorized: user not logged in');
        }
        log_message('error', 'DEBUG FateenAi::generate user_id='. session('user_id'));

        // ✅ جلب خطة المستخدم عبر SubscriptionService
        $subSrv = new SubscriptionService(db_connect());
        $plan   = $subSrv->getActivePlanForUser($userId);

        // إذا لم يكن لديه خطة → الحد الافتراضي = 7 رسائل يومياً
        $limit = $plan['messages_per_day'] ?? 7;

        // ✅ حساب عدد الرسائل المستخدمة اليوم
        $usageModel = new FateenUsageLogModel();
        $startOfDay = date('Y-m-d 00:00:00');
        $endOfDay   = date('Y-m-d 23:59:59');

        $usedToday = $usageModel->where('user_id', $userId)
            ->where('used_at >=', $startOfDay)
            ->where('used_at <=', $endOfDay)
            ->countAllResults();

        if ($usedToday >= $limit) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'error'   => "❌ لقد تجاوزت الحد اليومي المسموح به وهو {$limit} رسالة. يرجى المحاولة غدًا أو الترقية إلى خطة أعلى."
            ]);
        }

        // ✅ قراءة البيانات القادمة من الواجهة
        $payload = $this->request->getJSON(true) ?: [];
        $systemInstruction = $payload['system_instruction'] ?? null;
        $contents          = $payload['contents'] ?? [];
        $generationConfig  = $payload['generationConfig'] ?? [];
        $safetySettings    = $payload['safetySettings'] ?? [];

        // لا نتجاوز maxTurns
        if (count($contents) > $this->maxTurns) {
            $contents = array_slice($contents, -$this->maxTurns);
        }

        $apiKey = getenv('GEMINI_API_KEY');
        if (! $apiKey) {
            return $this->failServerError('API key is missing');
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . urlencode($apiKey);

        $body = ['contents' => $contents];
        if (is_string($systemInstruction) && $systemInstruction !== '') {
            $body['system_instruction'] = [
                'parts' => [[ 'text' => $systemInstruction ]]
            ];
        } elseif (is_array($systemInstruction)) {
            $body['system_instruction'] = $systemInstruction;
        }
        if (!empty($generationConfig)) $body['generationConfig'] = $generationConfig;
        if (!empty($safetySettings))   $body['safetySettings']   = $safetySettings;

        // ✅ تنفيذ الطلب عبر cURL
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $respBody = curl_exec($ch);
        $http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($respBody === false) {
            return $this->fail('Upstream error: ' . $err, 502);
        }
        if ($http >= 400) {
            return $this->fail($respBody, $http);
        }

        // ✅ تسجيل الاستخدام بعد النجاح
        $usageModel->insert([
            'user_id' => $userId,
            'success' => 1,
        ]);

        return $this->response
            ->setStatusCode(ResponseInterface::HTTP_OK)
            ->setJSON(json_decode($respBody, true));
    }

    /**
     * إرجاع حالة الاستخدام (used / remaining / limit)
     */
    public function usage(): ResponseInterface
    {
        helper('auth');
        $userId = session('user_id');
        if (! $userId) return $this->failUnauthorized('غير مسجل دخولاً');

        $subSrv = new SubscriptionService(db_connect());
        $plan   = $subSrv->getActivePlanForUser($userId);

        $limit = $plan['messages_per_day'] ?? 7;

        $start = date('Y-m-d 00:00:00');
        $end   = date('Y-m-d 23:59:59');

        $usageModel = new FateenUsageLogModel();
        $count = $usageModel->where('user_id', $userId)
                            ->where('used_at >=', $start)
                            ->where('used_at <=', $end)
                            ->countAllResults();

        $remaining = max(0, $limit - $count);

        return $this->response->setStatusCode(200)->setJSON([
            'used'      => $count,
            'remaining' => $remaining,
            'limit'     => $limit,
            'plan'      => $plan ? $plan['name'] : 'Free'
        ]);
    }

    /**
     * للبثّ المتدرّج مستقبلاً
     */
    public function stream()
    {
        return $this->fail('Streaming not implemented yet', 501);
    }
}

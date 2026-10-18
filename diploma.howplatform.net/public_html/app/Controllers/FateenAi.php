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

        // ✅ الحد اليومي: من عدّاد Plans المركزي في وضع api (بتوقيت عمّان)، وإلا العدّ المحلي.
        $usageModel  = new FateenUsageLogModel();
        $plansClient = new \App\Libraries\PlansClient();
        $snapshot    = $plansClient->usageSnapshot((int) $userId, 'fateen_messages_per_day', 7);

        if (is_array($snapshot)) {
            $limit     = $snapshot['limit'];             // null = بلا حد
            $usedToday = (int) ($snapshot['used'] ?? 0);
        } else {
            // fail-open: العدّ المحلي (وضع off أو تعذّر الاتصال بـ Plans)
            $subSrv = new SubscriptionService(db_connect());
            $plan   = $subSrv->getActivePlanForUser($userId);

            // إذا لم يكن لديه خطة → الحد الافتراضي = 7 رسائل يومياً
            $limit = $plan['messages_per_day'] ?? 7;

            $startOfDay = date('Y-m-d 00:00:00');
            $endOfDay   = date('Y-m-d 23:59:59');

            $usedToday = $usageModel->where('user_id', $userId)
                ->where('used_at >=', $startOfDay)
                ->where('used_at <=', $endOfDay)
                ->countAllResults();
        }

        if ($limit !== null && $usedToday >= (int) $limit) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'error'   => "❌ لقد تجاوزت الحد اليومي المسموح به وهو {$limit} رسالة. يرجى المحاولة غدًا أو الترقية إلى خطة أعلى."
            ]);
        }

        // ✅ قراءة البيانات القادمة من الواجهة
        $payload = $this->request->getJSON(true) ?: [];
        $systemInstruction = $payload['system_instruction'] ?? null;
        $contents          = $payload['contents'] ?? [];

        // ✅ نُلحق سياق المنصّة + توجيه ربط الخدمات بحاجات الطالب فوق توجيه فطين الخاص (دون حذفه).
        try {
            $platformCtx = \App\Libraries\AiReply::platformContext();
        } catch (\Throwable $e) {
            $platformCtx = '';
        }
        if ($platformCtx !== '') {
            // اسم المستخدم الذي يحادث فطين (لمخاطبته باسمه بلطف)
            $userName = trim((string) (session('user_name') ?? ''));
            $nameLine = $userName !== ''
                ? ("\n\nاسم المستخدم الذي تحادثه الآن: «{$userName}». خاطبه باسمه الأول بلطف عند المناسبة (لا تُكثر من تكراره).")
                : '';

            $linkDirective = "\n\nتوجيه إضافي لفطين: أنت مساعد منصّة كيف للطلبة (المعلمين المتدربين). "
                . "اربط حاجة الطالب بما توفّره المنصّة: إذا كان سؤاله يخصّ مهمة تساعدها إحدى الأدوات أعلاه فوجّهه إليها بالاسم واشرح فائدتها باختصار وكيف يصل إليها، "
                . "وإذا احتاج متابعة بشرية أو مشكلة خاصة فأرشده لإنشاء «طلب خدمة» من قسم الخدمة. "
                . "لا تختلق أدوات أو خدمات غير مذكورة، والتزم بالسياق أعلاه.";
            $ctxBlock = $platformCtx . $nameLine . $linkDirective;
            if (is_string($systemInstruction) && $systemInstruction !== '') {
                $systemInstruction = trim($systemInstruction) . "\n\n" . $ctxBlock;
            } elseif (is_array($systemInstruction)) {
                $systemInstruction['parts'][] = ['text' => $ctxBlock];
            } else {
                $systemInstruction = $ctxBlock;
            }
        }
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

        // ✅ تسجيل الاستخدام بعد النجاح (محليًا للتحليلات + عدّاد Plans المركزي)
        $usageModel->insert([
            'user_id' => $userId,
            'success' => 1,
        ]);
        try {
            $plansClient->usageConsume((int) $userId, 'fateen_messages_per_day', 1, 7);
        } catch (\Throwable $e) {
            log_message('warning', 'FateenAi usageConsume failed: ' . $e->getMessage());
        }

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

        // وضع api: العدّاد من Plans المركزي (بتوقيت عمّان)، وإلا العدّ المحلي.
        $plansClient = new \App\Libraries\PlansClient();
        $snapshot    = $plansClient->usageSnapshot((int) $userId, 'fateen_messages_per_day', 7);

        if (is_array($snapshot)) {
            $limit = $snapshot['limit'] ?? 7;
            $count = (int) ($snapshot['used'] ?? 0);
        } else {
            $limit = $plan['messages_per_day'] ?? 7;

            $start = date('Y-m-d 00:00:00');
            $end   = date('Y-m-d 23:59:59');

            $usageModel = new FateenUsageLogModel();
            $count = $usageModel->where('user_id', $userId)
                                ->where('used_at >=', $start)
                                ->where('used_at <=', $end)
                                ->countAllResults();
        }

        $remaining = $limit === null ? null : max(0, (int) $limit - $count);

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

<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Models\AiUsageLogModel;
use CodeIgniter\HTTP\ResponseInterface;

class Ai extends BaseController
{
    // حدّ أقصى تفرضه الخِدمة جانب الخادم (حماية إضافية)
    private int $maxTurns = 8;


public function generate(): ResponseInterface
{
    helper('auth');
    $userId = session('user_id');

    if (! $userId) {
        return $this->response->setStatusCode(401)->setJSON([
            'success' => false,
            'error' => 'Unauthorized: user not logged in'
        ]);
    }

    // ✅ مصادقة التوكن
    $clientToken = $this->request->getHeaderLine('X-Client-Token');
    if (!$clientToken || $clientToken !== getenv('APP_AI_PROXY_TOKEN')) {
        return $this->response->setStatusCode(401)->setJSON([
            'success' => false,
            'error' => 'Unauthorized: invalid token'
        ]);
    }

    // ✅ تحميل المستخدم
    $userModel = new \App\Models\UserModel();
    $user = $userModel->find($userId);
    if (! $user) {
        return $this->response->setStatusCode(404)->setJSON([
            'success' => false,
            'error' => 'User not found'
        ]);
    }

    // ✅ التحقق من الحد اليومي للمستخدم غير المميز
    if (! $user['is_premium']) {
        $aiLogModel = new \App\Models\AiUsageLogModel();

        $startOfDay = date('Y-m-d 00:00:00');
        $endOfDay   = date('Y-m-d 23:59:59');

        $usedToday = $aiLogModel->where('user_id', $userId)
            ->where('used_at >=', $startOfDay)
            ->where('used_at <=', $endOfDay)
            ->countAllResults();

        if ($usedToday >= 7) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'error' => '❌ لقد تجاوزت الحد اليومي البالغ 7 محادثات. يرجى المحاولة غدًا أو ترقية حسابك إلى بريميوم.'
            ]);
        }
    }

    // ✅ إعداد الاتصال بـ Gemini
    $payload = $this->request->getJSON(true) ?: [];
    $systemInstruction = $payload['system_instruction'] ?? null;
    $contents          = $payload['contents'] ?? [];
    $generationConfig  = $payload['generationConfig'] ?? [];
    $safetySettings    = $payload['safetySettings'] ?? [];

    $maxTurns = $this->maxTurns;
    if (count($contents) > $maxTurns) {
        $contents = array_slice($contents, -$maxTurns);
    }

    $apiKey = getenv('GEMINI_API_KEY');
    if (! $apiKey) {
        return $this->response->setStatusCode(500)->setJSON([
            'success' => false,
            'error' => 'API key is missing'
        ]);
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . urlencode($apiKey);

    $body = ['contents' => $contents];
    if (is_string($systemInstruction) && $systemInstruction !== '') {
        $body['system_instruction'] = [
            'parts' => [ [ 'text' => $systemInstruction ] ]
        ];
    } elseif (is_array($systemInstruction)) {
        $body['system_instruction'] = $systemInstruction;
    }
    if (!empty($generationConfig)) $body['generationConfig'] = $generationConfig;
    if (!empty($safetySettings))   $body['safetySettings']   = $safetySettings;

    // ✅ تنفيذ الطلب إلى Gemini
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
        return $this->response->setStatusCode(502)->setJSON([
            'success' => false,
            'error' => 'Upstream error: ' . $err
        ]);
    }

    if ($http >= 400) {
        return $this->response->setStatusCode($http)->setJSON([
            'success' => false,
            'error' => $respBody
        ]);
    }

    // ✅ تسجيل الاستخدام (فقط إذا نجح)
  
        $aiLogModel = new \App\Models\AiUsageLogModel();
        $aiLogModel->insert([
            'user_id' => $userId,    
            'success' => 1,
        ]);
    

    return $this->response
        ->setStatusCode(ResponseInterface::HTTP_OK)
        ->setJSON(json_decode($respBody, true));
}

public function usage(): ResponseInterface
{
    helper('auth');
    $userId = session('user_id');

    if (! $userId) {
        return $this->failUnauthorized('غير مسجل دخولاً');
    }

    $userModel = new \App\Models\UserModel();
    $user = $userModel->find($userId);
    if (! $user) {
        return $this->failNotFound('المستخدم غير موجود');
    }

    // إذا بريميوم، لا يوجد حد
    if ($user['is_premium']) {
        return $this->response->setStatusCode(200)->setJSON(['unlimited' => true]);

    }

    $start = date('Y-m-d 00:00:00');
    $end   = date('Y-m-d 23:59:59');

    $logModel = new \App\Models\AiUsageLogModel();
    $count = $logModel->where('user_id', $userId)
                      ->where('used_at >=', $start)
                      ->where('used_at <=', $end)
                      ->countAllResults();

    $remaining = max(0, 7 - $count);

    return $this->response
    ->setStatusCode(200)
    ->setJSON([
        'used'      => $count,
        'remaining' => $remaining,
        'limit'     => 7,
    ]);
}

    // للبثّ المتدرّج مستقبلاً
    public function stream()
    {
        // لاحقًا: استخدم streamGenerateContent وCURLOPT_WRITEFUNCTION
        return $this->response->setStatusCode(501)->setJSON([
            'success' => false, 'error' => 'Streaming not implemented yet'
        ]);
    }
}

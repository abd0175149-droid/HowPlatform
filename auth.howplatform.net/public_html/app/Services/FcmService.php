<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    private ?GoogleClient $googleClient = null;
    private string $projectId = '';
    private string $fcmUrl = '';

    public function __construct()
    {
        $serviceAccountPath = config('services.fcm.service_account_path', env('FCM_SERVICE_ACCOUNT_PATH', ''));
        
        // إذا لم يُحدد مسار، حاول البحث في عدة مواقع
        if (empty($serviceAccountPath)) {
            $possiblePaths = [
                base_path('howplatform-471514-firebase-adminsdk-fbsvc-b35be63f88.json'),
                base_path('public_html/howplatform-471514-firebase-adminsdk-fbsvc-b35be63f88.json'),
                public_path('howplatform-471514-firebase-adminsdk-fbsvc-b35be63f88.json'),
                __DIR__ . '/../../howplatform-471514-firebase-adminsdk-fbsvc-b35be63f88.json',
                __DIR__ . '/../../../howplatform-471514-firebase-adminsdk-fbsvc-b35be63f88.json',
            ];
            
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $serviceAccountPath = $path;
                    Log::info('[FCM] Found service account file', ['path' => $path]);
                    break;
                }
            }
        }
        
        if (empty($serviceAccountPath) || !file_exists($serviceAccountPath)) {
            Log::warning('[FCM] Service account file not found', ['path' => $serviceAccountPath]);
            return;
        }

        try {
            $this->googleClient = new GoogleClient();
            $this->googleClient->setAuthConfig($serviceAccountPath);
            $this->googleClient->addScope('https://www.googleapis.com/auth/firebase.messaging');
            
            $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);
            $this->projectId = $serviceAccount['project_id'] ?? '';
            
            if (!empty($this->projectId)) {
                $this->fcmUrl = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
            }
        } catch (\Exception $e) {
            Log::error('[FCM] Failed to initialize Google Client', ['error' => $e->getMessage()]);
            $this->googleClient = null;
        }
    }

    /**
     * الحصول على Access Token من Google
     */
    private function getAccessToken(): ?string
    {
        try {
            if (!$this->googleClient) {
                return null;
            }
            
            $this->googleClient->fetchAccessTokenWithAssertion();
            $token = $this->googleClient->getAccessToken();
            
            return $token['access_token'] ?? null;
        } catch (\Exception $e) {
            Log::error('[FCM] Failed to get access token', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * إرسال إشعار FCM إلى جهاز واحد (FCM v1)
     *
     * @param string $token Device token
     * @param string $title عنوان الإشعار
     * @param string $body نص الإشعار
     * @param array $data بيانات إضافية (اختياري)
     * @return bool
     */
    public function sendToDevice(string $token, string $title, string $body, array $data = []): bool
    {
        if (!$this->googleClient || empty($this->projectId) || empty($this->fcmUrl)) {
            Log::error('[FCM] Cannot send notification: Service account not configured');
            return false;
        }

        if (empty($token)) {
            Log::warning('[FCM] Cannot send notification: Empty device token');
            return false;
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            Log::error('[FCM] Failed to get access token');
            return false;
        }

        // بناء payload وفق FCM v1 API
        $message = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'android' => [
                    'priority' => 'high',
                ],
            ],
        ];

        // إضافة data payload إذا كان موجوداً
        if (!empty($data)) {
            $message['message']['data'] = array_map('strval', $data);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($this->fcmUrl, $message);

            if ($response->successful()) {
                $result = $response->json();
                if (isset($result['name'])) {
                    Log::info('[FCM] Notification sent successfully', [
                        'token' => substr($token, 0, 20) . '...',
                        'message_id' => $result['name'] ?? null,
                    ]);
                    return true;
                } else {
                    Log::warning('[FCM] FCM API returned unexpected response', ['response' => $result]);
                    return false;
                }
            } else {
                Log::error('[FCM] HTTP error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('[FCM] Exception while sending notification', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * إرسال إشعار FCM إلى عدة أجهزة
     *
     * @param array $tokens قائمة Device tokens
     * @param string $title عنوان الإشعار
     * @param string $body نص الإشعار
     * @param array $data بيانات إضافية (اختياري)
     * @return array ['success' => count, 'failed' => count]
     */
    public function sendToMultipleDevices(array $tokens, string $title, string $body, array $data = []): array
    {
        $tokens = array_filter(array_unique($tokens));
        
        if (empty($tokens)) {
            return ['success' => 0, 'failed' => 0];
        }

        $success = 0;
        $failed = 0;

        foreach ($tokens as $token) {
            if ($this->sendToDevice($token, $title, $body, $data)) {
                $success++;
            } else {
                $failed++;
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'total' => count($tokens),
        ];
    }

    /**
     * إرسال إشعار FCM باستخدام topic (لإرسال جماعي) - FCM v1
     *
     * @param string $topic اسم الـ topic
     * @param string $title عنوان الإشعار
     * @param string $body نص الإشعار
     * @param array $data بيانات إضافية (اختياري)
     * @return bool
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): bool
    {
        if (!$this->googleClient || empty($this->projectId) || empty($this->fcmUrl)) {
            Log::error('[FCM] Cannot send notification: Service account not configured');
            return false;
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            Log::error('[FCM] Failed to get access token');
            return false;
        }

        $message = [
            'message' => [
                'topic' => $topic,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'android' => [
                    'priority' => 'high',
                ],
            ],
        ];

        if (!empty($data)) {
            $message['message']['data'] = array_map('strval', $data);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($this->fcmUrl, $message);

            if ($response->successful()) {
                $result = $response->json();
                if (isset($result['name'])) {
                    Log::info('[FCM] Topic notification sent successfully', [
                        'topic' => $topic,
                        'message_id' => $result['name'] ?? null,
                    ]);
                    return true;
                } else {
                    Log::warning('[FCM] FCM API returned unexpected response for topic', [
                        'topic' => $topic,
                        'response' => $result,
                    ]);
                    return false;
                }
            } else {
                Log::error('[FCM] HTTP error for topic', [
                    'topic' => $topic,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('[FCM] Exception while sending topic notification', [
                'topic' => $topic,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}


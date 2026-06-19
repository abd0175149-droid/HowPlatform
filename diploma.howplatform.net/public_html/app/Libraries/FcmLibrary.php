<?php

namespace App\Libraries;

use Google\Client as GoogleClient;
use CodeIgniter\HTTP\CURLRequest;

class FcmLibrary
{
    private ?GoogleClient $googleClient = null;
    private string $projectId = '';
    private string $fcmUrl = '';
    private CURLRequest $client;

    public function __construct()
    {
        $serviceAccountPath = getenv('FCM_SERVICE_ACCOUNT_PATH') ?: config('App')->fcmServiceAccountPath ?? '';
        
        // محاولة البحث عن الملف في جذر المشروع إذا لم يُحدد مسار
        if (empty($serviceAccountPath)) {
            $possiblePaths = [
                ROOTPATH . 'howplatform-471514-firebase-adminsdk-fbsvc-b35be63f88.json',
                FCPATH . '../howplatform-471514-firebase-adminsdk-fbsvc-b35be63f88.json',
            ];
            
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $serviceAccountPath = $path;
                    break;
                }
            }
        }
        
        if (empty($serviceAccountPath) || !file_exists($serviceAccountPath)) {
            log_message('warning', '[FCM] Service account file not found', ['path' => $serviceAccountPath]);
            $this->client = \Config\Services::curlrequest();
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
            log_message('error', '[FCM] Failed to initialize Google Client', ['error' => $e->getMessage()]);
        }

        $this->client = \Config\Services::curlrequest();
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
            log_message('error', '[FCM] Failed to get access token', ['error' => $e->getMessage()]);
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
            log_message('error', '[FCM] Cannot send notification: Service account not configured');
            return false;
        }

        if (empty($token)) {
            log_message('warning', '[FCM] Cannot send notification: Empty device token');
            return false;
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            log_message('error', '[FCM] Failed to get access token');
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
            $response = $this->client->request('POST', $this->fcmUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $message,
                'http_errors' => false,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody();

            if ($statusCode === 200) {
                $result = json_decode($body, true);
                if (isset($result['name'])) {
                    log_message('info', '[FCM] Notification sent successfully', [
                        'token' => substr($token, 0, 20) . '...',
                        'message_id' => $result['name'] ?? null,
                    ]);
                    return true;
                } else {
                    log_message('warning', '[FCM] FCM API returned unexpected response', ['response' => $result]);
                    return false;
                }
            } else {
                log_message('error', '[FCM] HTTP error', [
                    'status' => $statusCode,
                    'body' => $body,
                ]);
                return false;
            }
        } catch (\Exception $e) {
            log_message('error', '[FCM] Exception while sending notification', [
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
            return ['success' => 0, 'failed' => 0, 'total' => 0];
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
            log_message('error', '[FCM] Cannot send notification: Service account not configured');
            return false;
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            log_message('error', '[FCM] Failed to get access token');
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
            $response = $this->client->request('POST', $this->fcmUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $message,
                'http_errors' => false,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody();

            if ($statusCode === 200) {
                $result = json_decode($body, true);
                if (isset($result['name'])) {
                    log_message('info', '[FCM] Topic notification sent successfully', [
                        'topic' => $topic,
                        'message_id' => $result['name'] ?? null,
                    ]);
                    return true;
                } else {
                    log_message('warning', '[FCM] FCM API returned unexpected response for topic', [
                        'topic' => $topic,
                        'response' => $result,
                    ]);
                    return false;
                }
            } else {
                log_message('error', '[FCM] HTTP error for topic', [
                    'topic' => $topic,
                    'status' => $statusCode,
                    'body' => $body,
                ]);
                return false;
            }
        } catch (\Exception $e) {
            log_message('error', '[FCM] Exception while sending topic notification', [
                'topic' => $topic,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}


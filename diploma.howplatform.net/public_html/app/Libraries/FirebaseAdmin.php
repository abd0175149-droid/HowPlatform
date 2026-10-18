<?php

declare(strict_types=1);

namespace App\Libraries;

use Firebase\JWT\JWT;

/**
 * طبقة Firebase Admin خفيفة (بلا SDK ثقيل) مبنية على firebase/php-jwt + curlrequest.
 *
 * توفّر:
 *  - createCustomToken(): توكن مخصص لتسجيل دخول العميل عبر signInWithCustomToken().
 *  - getAccessToken(): OAuth2 access token (JWT-bearer) لاستدعاء Firestore REST و FCM v1.
 *  - firestoreSet()/firestoreCreate()/firestoreDelete(): كتابة مستندات Firestore.
 *  - fcmSendToTokens(): إرسال إشعار FCM v1 لمجموعة أجهزة.
 *
 * مفتاح حساب الخدمة يُقرأ من ملف سري مُركّب (افتراضياً /var/www/secure-keys/firebase-adminsdk.json).
 */
class FirebaseAdmin
{
    private const IDENTITY_AUD = 'https://identitytoolkit.googleapis.com/google.identity.identitytoolkit.v1.IdentityToolkit';
    private const SCOPES       = 'https://www.googleapis.com/auth/datastore https://www.googleapis.com/auth/firebase.messaging';

    private array  $sa;
    private string $projectId;

    /** كاش access token داخل العملية الواحدة */
    private static ?string $accessToken    = null;
    private static int     $accessTokenExp = 0;

    public function __construct(?string $credPath = null)
    {
        $credPath = $credPath
            ?: (getenv('FIREBASE_CREDENTIALS') ?: '/var/www/secure-keys/firebase-adminsdk.json');

        if (! is_file($credPath) || ! is_readable($credPath)) {
            throw new \RuntimeException("Firebase credentials not readable at {$credPath}");
        }

        $json = json_decode((string) file_get_contents($credPath), true);
        if (! is_array($json) || empty($json['private_key']) || empty($json['client_email'])) {
            throw new \RuntimeException('Invalid Firebase service account JSON');
        }

        $this->sa        = $json;
        $this->projectId = (string) ($json['project_id'] ?: (getenv('FIREBASE_PROJECT_ID') ?: ''));
    }

    public function projectId(): string
    {
        return $this->projectId;
    }

    /* ============================ Auth ============================ */

    /** توكن مخصص لتسجيل دخول مستخدم عبر Firebase Auth (RS256، صلاحية ساعة). */
    public function createCustomToken(string $uid, array $claims = []): string
    {
        $now     = time();
        $payload = [
            'iss' => $this->sa['client_email'],
            'sub' => $this->sa['client_email'],
            'aud' => self::IDENTITY_AUD,
            'iat' => $now,
            'exp' => $now + 3600,
            'uid' => $uid,
        ];
        if (! empty($claims)) {
            $payload['claims'] = $claims;
        }

        return JWT::encode($payload, $this->sa['private_key'], 'RS256');
    }

    /** OAuth2 access token عبر JWT-bearer grant (مع كاش داخل العملية). */
    public function getAccessToken(): string
    {
        $now = time();
        if (self::$accessToken !== null && $now < (self::$accessTokenExp - 60)) {
            return self::$accessToken;
        }

        $tokenUri  = $this->sa['token_uri'] ?? 'https://oauth2.googleapis.com/token';
        $assertion = JWT::encode([
            'iss'   => $this->sa['client_email'],
            'scope' => self::SCOPES,
            'aud'   => $tokenUri,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ], $this->sa['private_key'], 'RS256');

        $client = \Config\Services::curlrequest(['timeout' => 20]);
        $res    = $client->post($tokenUri, [
            'form_params' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $assertion,
            ],
            'http_errors' => false,
        ]);

        $data = json_decode((string) $res->getBody(), true);
        if (empty($data['access_token'])) {
            throw new \RuntimeException('Failed to obtain access token: ' . substr((string) $res->getBody(), 0, 300));
        }

        self::$accessToken    = (string) $data['access_token'];
        self::$accessTokenExp = $now + (int) ($data['expires_in'] ?? 3600);

        return self::$accessToken;
    }

    /* ========================== Firestore ========================== */

    private function firestoreBase(): string
    {
        return "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents";
    }

    /**
     * إنشاء مستند بمعرّف تلقائي ضمن مجموعة. $collectionPath نسبي مثل:
     * "serviceRequests/123/messages". يعيد معرّف المستند المُنشأ.
     */
    public function firestoreCreate(string $collectionPath, array $fields): string
    {
        $url    = $this->firestoreBase() . '/' . trim($collectionPath, '/');
        $client = \Config\Services::curlrequest(['timeout' => 20]);
        $res    = $client->post($url, [
            'headers'     => ['Authorization' => 'Bearer ' . $this->getAccessToken()],
            'json'        => ['fields' => self::toFirestoreFields($fields)],
            'http_errors' => false,
        ]);

        $body = json_decode((string) $res->getBody(), true);
        if (($res->getStatusCode() >= 300) || empty($body['name'])) {
            throw new \RuntimeException('Firestore create failed: ' . substr((string) $res->getBody(), 0, 300));
        }

        $parts = explode('/', (string) $body['name']);
        return end($parts);
    }

    /** تعيين/تحديث مستند بمسار محدد مثل "serviceRequests/123". */
    public function firestoreSet(string $docPath, array $fields, bool $merge = true): void
    {
        $url = $this->firestoreBase() . '/' . trim($docPath, '/');
        if ($merge) {
            $mask = array_map(static fn ($k) => 'updateMask.fieldPaths=' . rawurlencode($k), array_keys($fields));
            $url .= '?' . implode('&', $mask);
        }

        $client = \Config\Services::curlrequest(['timeout' => 20]);
        $res    = $client->patch($url, [
            'headers'     => ['Authorization' => 'Bearer ' . $this->getAccessToken()],
            'json'        => ['fields' => self::toFirestoreFields($fields)],
            'http_errors' => false,
        ]);

        if ($res->getStatusCode() >= 300) {
            throw new \RuntimeException('Firestore set failed: ' . substr((string) $res->getBody(), 0, 300));
        }
    }

    /** حذف مستند Firestore بمسار محدد. */
    public function firestoreDelete(string $docPath): void
    {
        $url    = $this->firestoreBase() . '/' . trim($docPath, '/');
        $client = \Config\Services::curlrequest(['timeout' => 20]);
        $res    = $client->delete($url, [
            'headers'     => ['Authorization' => 'Bearer ' . $this->getAccessToken()],
            'http_errors' => false,
        ]);
        if ($res->getStatusCode() >= 300) {
            throw new \RuntimeException('Firestore delete failed: ' . substr((string) $res->getBody(), 0, 300));
        }
    }

    /** تحويل مصفوفة PHP إلى تنسيق حقول Firestore المُكتّب. */
    private static function toFirestoreFields(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $out[$k] = self::toFirestoreValue($v);
        }
        return $out;
    }

    private static function toFirestoreValue($v): array
    {
        if ($v === null) {
            return ['nullValue' => null];
        }
        if (is_bool($v)) {
            return ['booleanValue' => $v];
        }
        if (is_int($v)) {
            return ['integerValue' => (string) $v];
        }
        if (is_float($v)) {
            return ['doubleValue' => $v];
        }
        if (is_array($v)) {
            // قيمة Firestore مُكتّبة مسبقاً (مثل nowTimestamp) → مرّرها كما هي
            if (count($v) === 1) {
                $typeKeys = ['nullValue', 'booleanValue', 'integerValue', 'doubleValue',
                    'timestampValue', 'stringValue', 'bytesValue', 'referenceValue',
                    'geoPointValue', 'arrayValue', 'mapValue'];
                if (in_array(array_key_first($v), $typeKeys, true)) {
                    return $v;
                }
            }
            $isList = array_keys($v) === range(0, count($v) - 1);
            if ($isList) {
                return ['arrayValue' => ['values' => array_map([self::class, 'toFirestoreValue'], $v)]];
            }
            return ['mapValue' => ['fields' => self::toFirestoreFields($v)]];
        }
        return ['stringValue' => (string) $v];
    }

    /** قيمة طابع زمني للخادم (serverTimestamp بديل: نمرّر وقت الخادم الحالي). */
    public static function nowTimestamp(): array
    {
        return ['timestampValue' => gmdate('Y-m-d\TH:i:s\Z')];
    }

    /* ============================= FCM ============================= */

    /**
     * إرسال إشعار FCM v1 لقائمة توكنات. يعيد ['success'=>n,'failure'=>n,'invalid'=>[tokens]].
     * $data اختياري (حمولة بيانات لإجراءات العميل مثل فتح المحادثة).
     */
    public function fcmSendToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        $tokens = array_values(array_filter(array_unique($tokens)));
        $result = ['success' => 0, 'failure' => 0, 'invalid' => []];
        if (empty($tokens)) {
            return $result;
        }

        $access = $this->getAccessToken();
        $url    = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
        $client = \Config\Services::curlrequest(['timeout' => 20]);

        // FCM v1 يرسل لتوكن واحد لكل طلب
        foreach ($tokens as $token) {
            $message = [
                'message' => [
                    'token'        => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data'         => array_map('strval', $data),
                    'webpush'      => [
                        'headers' => ['Urgency' => 'high'],
                        'fcmOptions' => array_filter(['link' => $data['link'] ?? null]),
                    ],
                ],
            ];

            $res = $client->post($url, [
                'headers'     => ['Authorization' => 'Bearer ' . $access],
                'json'        => $message,
                'http_errors' => false,
            ]);

            $code = $res->getStatusCode();
            if ($code >= 200 && $code < 300) {
                $result['success']++;
            } else {
                $result['failure']++;
                // توكن غير صالح/منتهي → علّمه للحذف
                if ($code === 404 || $code === 400) {
                    $result['invalid'][] = $token;
                }
            }
        }

        return $result;
    }

    /* ========================== Security Rules ========================== */

    /** نشر قواعد أمان Firestore عبر Firebase Rules API (ruleset + release cloud.firestore). */
    public function publishFirestoreRules(string $source): string
    {
        $access = $this->getAccessToken();
        $client = \Config\Services::curlrequest(['timeout' => 30]);
        $base   = 'https://firebaserules.googleapis.com/v1';

        // 1) إنشاء ruleset
        $res = $client->post("{$base}/projects/{$this->projectId}/rulesets", [
            'headers'     => ['Authorization' => 'Bearer ' . $access],
            'json'        => ['source' => ['files' => [['name' => 'firestore.rules', 'content' => $source]]]],
            'http_errors' => false,
        ]);
        $body = json_decode((string) $res->getBody(), true);
        if (empty($body['name'])) {
            throw new \RuntimeException('ruleset create failed: ' . substr((string) $res->getBody(), 0, 400));
        }
        $rulesetName = (string) $body['name'];

        // 2) ربط الإصدار cloud.firestore بالـ ruleset (patch، وإن لم يوجد ننشئه)
        $relName = "projects/{$this->projectId}/releases/cloud.firestore";
        $res2 = $client->patch("{$base}/{$relName}", [
            'headers'     => ['Authorization' => 'Bearer ' . $access],
            'json'        => ['release' => ['name' => $relName, 'rulesetName' => $rulesetName]],
            'http_errors' => false,
        ]);
        if ($res2->getStatusCode() >= 300) {
            $res3 = $client->post("{$base}/projects/{$this->projectId}/releases", [
                'headers'     => ['Authorization' => 'Bearer ' . $access],
                'json'        => ['name' => $relName, 'rulesetName' => $rulesetName],
                'http_errors' => false,
            ]);
            if ($res3->getStatusCode() >= 300) {
                throw new \RuntimeException('release update/create failed: '
                    . substr((string) $res2->getBody() . ' | ' . (string) $res3->getBody(), 0, 400));
            }
        }

        return $rulesetName;
    }
}

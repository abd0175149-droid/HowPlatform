<?php
declare(strict_types=1);

namespace App\Libraries;

/**
 * AiClient: عميل بسيط لاستدعاء موفّر الذكاء وإرجاع JSON خالص.
 * - يدعم Gemini أو OpenAI وفق الإعداد.
 * - يفرض إخراج JSON فقط.
 * - يوفّر لوج غني للتتبّع (prompt/req/res/http).
 */
class AiClient
{
    /** Trace ID للتمييز في اللوج */
    protected ?string $traceId = null;

    /** آخر نص موحّد تم إرساله (system + IMPORTANT + userPrompt) */
    protected ?string $lastMergedPrompt = null;

    /** آخر جسم طلب JSON تم إرساله للمزوّد */
    protected ?string $lastRequestBody = null;

    /** آخر رد خام مُفكّك من المزود (كـ array) */
    protected ?array $lastResponse = null;

    /** معلومات HTTP أخيرة (كود/نص) لأغراض التشخيص */
    protected ?int $lastHttpCode = null;
    protected ?string $lastHttpRaw = null;

    protected string $provider;
    protected string $model;
    protected ?string $apiKey;

    public function __construct()
    {
        $this->provider = getenv('AI_PROVIDER') ?: 'gemini';

        if ($this->provider === 'openai') {
            $this->model  = getenv('AI_OPENAI_MODEL') ?: 'gpt-4o-mini';
            $this->apiKey = getenv('AI_OPENAI_API_KEY') ?: null;
        } elseif ($this->provider === 'vertex') {
            $this->model  = getenv('AI_VERTEX_MODEL') ?: 'gemini-pro';
            // Vertex AI لا يستخدم API Key - يتطلب Service Account أو Access Token
            // لا نضع apiKey هنا لأن getVertexAccessToken() سيتعامل مع المصادقة
            $this->apiKey = null;
        } else {
            // ⚠️ تأكّد أن هذا مضبوط في .env عندك (مثال: gemini-2.0-flash)
            $this->model  = getenv('AI_GEMINI_MODEL') ?: 'gemini-2.0-flash';
            $this->apiKey = getenv('AI_GEMINI_API_KEY') ?: null;
        }
    }

    // ——— أدوات تتبّع للـ Runner ———
    public function withTrace(string $id): self { $this->traceId = $id; return $this; }
    public function getTraceId(): ?string { return $this->traceId; }
    public function getProvider(): string { return $this->provider; }
    public function getModel(): string { return $this->model; }
    public function getLastMergedPrompt(): ?string { return $this->lastMergedPrompt; }
    public function getLastRequestBody(): ?string { return $this->lastRequestBody; }
    public function getLastResponseRaw(): ?string
    {
        return $this->lastResponse ? json_encode($this->lastResponse, JSON_UNESCAPED_UNICODE) : null;
    }
    public function getLastHttpInfo(): array
    {
        return ['code' => $this->lastHttpCode, 'raw' => $this->lastHttpRaw];
    }

    /**
     * استدعاء النموذج وفرض JSON فقط.
     *
     * @param string $systemPrompt التوجيه الثابت (من ai_job_types.system_prompt - للمراجعة فقط)
     * @param array  $payload      مدخلات المستخدم كما أرسلتها الواجهة
     * @param string $typeKey      نوع الأداة (لاختيار PromptMaker المناسب)
     * @return string              JSON string
     */
    public function generateJson(string $systemPrompt, array $payload, string $typeKey): string
    {
        // 1) استيراد PromptMakerFactory وإنشاء PromptMaker المناسب
        $promptMaker = \App\Libraries\PromptMaker\PromptMakerFactory::create($typeKey);
        
        // 2) بناء الرسائل (system + user)
        $msgs = $promptMaker->buildMessages($systemPrompt, $payload);
        $sys = $msgs['system'];
        $usr = $msgs['user'];
    
        $this->lastMergedPrompt = $sys . "\n\n" .
            "IMPORTANT: Output MUST be JSON only with no extra text outside JSON.\n\n" .
            $usr;
    
        if ($this->provider === 'openai') {
            return $this->callOpenAI($sys, $usr, $payload);
        } elseif ($this->provider === 'vertex') {
            return $this->callVertexAI($sys, $usr, $payload);
        } else {
            return $this->callGemini($sys, $usr, $payload);
        }
    }


    /**
     * تحويل مدخلات المستخدم إلى نص متسلسل — يُستخدم فقط كسقوط احتياطي لغير lesson_plan_v1.
     */
    protected function formatUserContext(array $p): string
    {
        $lines = [
            '## USER INPUT (Arabic)',
            'teacher_name: '      . ($p['teacher_name']      ?? ''),
            'topic_summary: '     . ($p['topic_summary']     ?? ''),
            'school_name: '       . ($p['school_name']       ?? ''),
            'grade_level: '       . ($p['grade_level']       ?? ''),
            'duration_minutes: '  . (string)($p['duration_minutes'] ?? 40),
            'focus_axis: '        . ($p['focus_axis']        ?? ''),
            'outcomes_count: '    . (string)($p['outcomes_count']   ?? 2),
            'learning_assets: '   . ($p['learning_assets']   ?? ''),
            'class_size: '        . (string)($p['class_size']       ?? 30),
            'constraints: '       . ($p['constraints']       ?? ''),
            'sen_present: '       . (isset($p['sen_present']) ? ($p['sen_present'] ? 'true' : 'false') : 'false'),
            'sen_support: '       . ($p['sen_support']       ?? ''),
            'rough_goals_note: '  . ($p['rough_goals_note']  ?? ''),
        ];

        return implode("\n", $lines);
    }

    /**
     * يبني safetySettings صحيحة حسب المتغير البيئي AI_SAFETY = off|low|med|high.
     * - off  => BLOCK_NONE
     * - low  => BLOCK_ONLY_HIGH
     * - med  => BLOCK_MEDIUM_AND_ABOVE
     * - high => BLOCK_LOW_AND_ABOVE
     */
    protected function buildSafetySettings(): array
    {
        $mode = strtolower((string) (getenv('AI_SAFETY') ?: 'low'));

        $thresholdMap = [
            'off'  => 'BLOCK_NONE',
            'low'  => 'BLOCK_ONLY_HIGH',
            'med'  => 'BLOCK_MEDIUM_AND_ABOVE',
            'high' => 'BLOCK_LOW_AND_ABOVE',
        ];
        $threshold = $thresholdMap[$mode] ?? 'BLOCK_ONLY_HIGH';

        // ✅ الأسماء الصحيحة للفئات
        $categories = [
            'HARM_CATEGORY_HARASSMENT',
            'HARM_CATEGORY_HATE_SPEECH',
            'HARM_CATEGORY_DANGEROUS_CONTENT',
            'HARM_CATEGORY_SEXUALLY_EXPLICIT',
        ];

        $settings = [];
        foreach ($categories as $cat) {
            $settings[] = [
                'category'  => $cat,
                'threshold' => $threshold,
            ];
        }
        return $settings;
    }

    /**
     * استدعاء Gemini مع دعم safety/fallback وتخزين لوج غني.
     */
    protected function callGemini(string $systemPrompt, string $userPrompt, array $payload = []): string
    {
        if (!$this->apiKey) {
            throw new \RuntimeException('Missing AI_GEMINI_API_KEY');
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
             . rawurlencode($this->model) . ':generateContent?key=' . urlencode($this->apiKey);

        // لوج موحّد
        $this->lastMergedPrompt = $systemPrompt
            . "\n\nIMPORTANT: Output MUST be JSON only with no extra text outside JSON.\n\n"
            . $userPrompt;

        $requestBody = [
            // تمرير التوجيه كنظام
            'system_instruction' => [
                'role'  => 'system',
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => [[
                'role'  => 'user',
                'parts' => $this->buildParts($userPrompt, $payload),
            ]],
            'generationConfig' => [
                'temperature'        => 0.2,
                'response_mime_type' => 'application/json', // فرض JSON
            ],
            'safetySettings' => $this->buildSafetySettings(),
        ];

        $resp = $this->curlJson($url, $requestBody);
        $json = $this->extractTextFromGemini($resp, $blockedReason);

        if ($json !== '') {
            $this->assertJson($json);
            return $json;
        }

        // ——— Fallback: إزالة response_mime_type وجعل كل شيء في رسالة واحدة ———
        $fallbackPayload = [
            'contents' => [[
                'parts' => [[ 'text' => $this->lastMergedPrompt ]],
            ]],
            'generationConfig' => [ 'temperature' => 0.2 ],
            'safetySettings'   => $this->buildSafetySettings(),
        ];

        $resp2 = $this->curlJson($url, $fallbackPayload);
        $json2 = $this->extractTextFromGemini($resp2, $blockedReason2);
        if ($json2 !== '') {
            $this->assertJson($json2);
            return $json2;
        }

        $why = $blockedReason ?: $blockedReason2 ?: 'no candidates/text';
        throw new \RuntimeException('AI empty response (Gemini): ' . $why);
    }

    /**
     * استدعاء Vertex AI (Chat API).
     */
    protected function callVertexAI(string $systemPrompt, string $userPrompt, array $payload = []): string
    {
        $projectId = function_exists('env') 
            ? (env('VERTEX_PROJECT_ID') ?: '') 
            : (getenv('VERTEX_PROJECT_ID') ?: '');
        $location = function_exists('env') 
            ? (env('VERTEX_LOCATION') ?: 'us-west1') 
            : (getenv('VERTEX_LOCATION') ?: 'us-west1');
        
        if ($projectId === '') {
            throw new \RuntimeException('Missing VERTEX_PROJECT_ID');
        }

        // الحصول على access token
        $accessToken = $this->getVertexAccessToken();
        if (!$accessToken) {
            throw new \RuntimeException('Failed to get Vertex AI access token. Vertex AI requires OAuth 2.0 access token. Please set VERTEX_SERVICE_ACCOUNT_PATH (preferred) or VERTEX_ACCESS_TOKEN in .env file. Note: API Keys are NOT supported by Vertex AI.');
        }

        // بناء URL لـ Vertex AI generateContent endpoint
        $url = sprintf(
            'https://%s-aiplatform.googleapis.com/v1/projects/%s/locations/%s/publishers/google/models/%s:generateContent',
            $location,
            $projectId,
            $location,
            rawurlencode($this->model)
        );

        // لوج موحّد
        $this->lastMergedPrompt = $systemPrompt
            . "\n\nIMPORTANT: Output MUST be JSON only with no extra text outside JSON.\n\n"
            . $userPrompt;

        // RAG Corpus configuration (إن كان محدد)
        $ragCorpus = function_exists('env') 
            ? (env('VERTEX_RAG_CORPUS') ?: '') 
            : (getenv('VERTEX_RAG_CORPUS') ?: '');
        
        $requestBody = [
            'contents' => [[
                'role'  => 'user',
                'parts' => $this->buildParts($userPrompt, $payload),
            ]],
            'generationConfig' => [
                'temperature'        => 0.2,
                'responseMimeType'    => 'application/json', // فرض JSON
            ],
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'safetySettings' => $this->buildSafetySettings(),
        ];
        
        // إضافة RAG tools إذا كان corpus محدد (الصيغة الصحيحة حسب Google)
        if ($ragCorpus !== '') {
            $requestBody['tools'] = [[
                'retrieval' => [
                    'vertex_rag_store' => [
                        'rag_resources' => [
                            ['rag_corpus' => $ragCorpus]
                        ],
                    ]
                ]
            ]];
            
            log_message('debug', '[Vertex AI] Using RAG Corpus: ' . $ragCorpus);
        }

        // استخدام Bearer token authentication
        $resp = $this->curlJson($url, $requestBody, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ]);

        $json = $this->extractTextFromGemini($resp, $blockedReason);

        if ($json !== '') {
            $this->assertJson($json);
            return $json;
        }

        // ——— Fallback: إزالة responseMimeType وجعل كل شيء في رسالة واحدة ———
        $fallbackPayload = [
            'contents' => [[
                'parts' => [[ 'text' => $this->lastMergedPrompt ]],
            ]],
            'generationConfig' => [ 'temperature' => 0.2 ],
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'safetySettings' => $this->buildSafetySettings(),
        ];
        
        // إضافة RAG tools في fallback أيضاً إذا كان corpus محدد
        if ($ragCorpus !== '') {
            $fallbackPayload['tools'] = [[
                'retrieval' => [
                    'vertex_rag_store' => [
                        'rag_resources' => [
                            ['rag_corpus' => $ragCorpus]
                        ],
                    ]
                ]
            ]];
        }

        $resp2 = $this->curlJson($url, $fallbackPayload, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ]);
        $json2 = $this->extractTextFromGemini($resp2, $blockedReason2);
        if ($json2 !== '') {
            $this->assertJson($json2);
            return $json2;
        }

        $why = $blockedReason ?: $blockedReason2 ?: 'no candidates/text';
        throw new \RuntimeException('AI empty response (Vertex AI): ' . $why);
    }

    /**
     * الحصول على OAuth 2.0 access token لـ Vertex AI
     * يدعم طريقتين:
     * 1. Service Account JSON file (مفضل ومطلوب)
     * 2. Access Token مباشر (إن كان متوفر)
     * 
     * ملاحظة: Vertex AI لا يقبل API Key - يتطلب OAuth 2.0 access token فقط
     */
    protected function getVertexAccessToken(): ?string
    {
        // الطريقة 1: استخدام Service Account JSON (مفضل ومطلوب)
        // استخدام env() helper من CodeIgniter بدلاً من getenv() مباشرة
        $serviceAccountPath = function_exists('env') 
            ? (env('VERTEX_SERVICE_ACCOUNT_PATH') ?: '') 
            : (getenv('VERTEX_SERVICE_ACCOUNT_PATH') ?: '');
        
        log_message('debug', '[Vertex AI] Service Account Path from env: ' . ($serviceAccountPath ?: 'NOT SET'));
        
        if ($serviceAccountPath !== '' && file_exists($serviceAccountPath)) {
            try {
                if (!class_exists(\Google\Client::class)) {
                    log_message('error', '[Vertex AI] Google Client library not found. Install via: composer require google/apiclient');
                    return null;
                }

                $client = new \Google\Client();
                $client->setAuthConfig($serviceAccountPath);
                $client->addScope('https://www.googleapis.com/auth/cloud-platform');
                $client->fetchAccessTokenWithAssertion();
                $token = $client->getAccessToken();
                
                $accessToken = $token['access_token'] ?? null;
                if ($accessToken) {
                    log_message('info', '[Vertex AI] Successfully obtained access token from Service Account');
                    return $accessToken;
                } else {
                    log_message('error', '[Vertex AI] Failed to get access_token from Service Account response');
                    return null;
                }
            } catch (\Throwable $e) {
                log_message('error', '[Vertex AI] Failed to get access token from Service Account: ' . $e->getMessage());
                log_message('error', '[Vertex AI] Service Account path: ' . $serviceAccountPath);
                return null;
            }
        } else {
            if ($serviceAccountPath !== '') {
                log_message('error', '[Vertex AI] Service Account file not found: ' . $serviceAccountPath);
                log_message('error', '[Vertex AI] File exists check: ' . (file_exists($serviceAccountPath) ? 'YES' : 'NO'));
                log_message('error', '[Vertex AI] Is readable: ' . (is_readable($serviceAccountPath) ? 'YES' : 'NO'));
            } else {
                log_message('error', '[Vertex AI] VERTEX_SERVICE_ACCOUNT_PATH not set in environment variables');
                log_message('error', '[Vertex AI] Please add VERTEX_SERVICE_ACCOUNT_PATH to your .env file');
            }
        }

        // الطريقة 2: استخدام Access Token مباشر (إن كان متوفر)
        // ملاحظة: يجب أن يكون access token صالح، وليس API Key
        $accessToken = function_exists('env') 
            ? (env('VERTEX_ACCESS_TOKEN') ?: '') 
            : (getenv('VERTEX_ACCESS_TOKEN') ?: '');
        if ($accessToken !== '') {
            log_message('info', '[Vertex AI] Using VERTEX_ACCESS_TOKEN from environment');
            return $accessToken;
        }

        // تحذير: لا يمكن استخدام API Key مع Vertex AI
        if ($this->apiKey) {
            log_message('error', '[Vertex AI] Cannot use API Key with Vertex AI. Vertex AI requires OAuth 2.0 access token.');
            log_message('error', '[Vertex AI] Please set VERTEX_SERVICE_ACCOUNT_PATH or VERTEX_ACCESS_TOKEN in .env file');
        }

        return null;
    }

    /**
     * استدعاء OpenAI (Chat Completions).
     */
protected function callOpenAI(string $systemPrompt, string $userPrompt, array $payload = []): string
{
    if (!$this->apiKey) {
        throw new \RuntimeException('Missing AI_OPENAI_API_KEY');
    }

    // ✅ تأكيد أن الموديل مضبوط
    $model = trim((string) $this->model);
    if ($model === '') {
        // محاولة افتراضية ذكية
        $model = 'gpt-4o-mini';
        $this->model = $model;
    }

    $url = 'https://api.openai.com/v1/chat/completions';

    $this->lastMergedPrompt = $systemPrompt
        . "\n\nIMPORTANT: Output MUST be JSON only with no extra text outside JSON.\n\n"
        . $userPrompt;

    $messages = [
        [
            'role'    => 'system',
            'content' => $systemPrompt . "\n\nIMPORTANT: Output MUST be JSON only with no extra text outside JSON.",
        ],
        ['role' => 'user', 'content' => $userPrompt],
    ];

    $payload = [
        'model'           => $model,                  // ✅ تأكدنا منه
        'messages'        => $messages,
        'temperature'     => 0.2,
        'response_format' => ['type' => 'json_object'], // ✅ فرض JSON
    ];

    $resp = $this->curlJson($url, $payload, [
        'Authorization: Bearer ' . $this->apiKey,
        'Content-Type: application/json',
    ]);

    $json = $resp['choices'][0]['message']['content'] ?? '';
    if ($json === '') {
        throw new \RuntimeException('AI empty response (OpenAI).');
    }

    $this->assertJson($json);
    return $json;
}


    /**
     * CURL عام مع التقاط الـ raw والـ code للّوج.
     */
    protected function curlJson(string $url, array $payload, array $headers = []): array
    {
        $ch = curl_init($url);
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        // حفظ جسم الطلب لأغراض التشخيص
        $this->lastRequestBody = $body;

        $defaultHeaders = ['Content-Type: application/json'];
        // زيادة timeout للمقالات الطويلة (خاصة مع RAG) - افتراضي 300 ثانية (5 دقائق)
        $timeout = (int) (getenv('AI_HTTP_TIMEOUT') ?: 300);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array_merge($defaultHeaders, $headers),
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10, // 10 ثوانٍ للاتصال
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_VERBOSE        => false,
            CURLOPT_FAILONERROR    => false, // نريد رؤية الرد حتى لو كان خطأ HTTP
        ]);
        
        // Log قبل إرسال الطلب
        if (getenv('AI_DEBUG') === '1') {
            log_message('debug', "AI Client: Sending request to {$url}. Timeout: {$timeout}s, Body size: " . strlen($body) . " bytes");
        }

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrNo = curl_errno($ch);
        $curlError = curl_error($ch);
        $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        
        $this->lastHttpCode = $httpCode;
        $this->lastHttpRaw  = is_string($response) ? $response : null;

        if ($response === false) {
            curl_close($ch);
            $errorMsg = "AI HTTP error";
            if ($curlErrNo === CURLE_OPERATION_TIMEDOUT) {
                $errorMsg .= " (Timeout after {$timeout}s)";
            } elseif ($curlErrNo === CURLE_COULDNT_CONNECT) {
                $errorMsg .= " (Connection failed)";
            } else {
                $errorMsg .= " (CURL error #{$curlErrNo}: {$curlError})";
            }
            log_message('error', "AI Client: {$errorMsg}. URL: {$url}");
            throw new \RuntimeException($errorMsg);
        }
        
        // Log وقت الاستجابة
        if (getenv('AI_DEBUG') === '1') {
            log_message('debug', "AI Client: Response received. HTTP code: {$httpCode}, Total time: {$totalTime}s, Response size: " . strlen($response) . " bytes");
        }
        
        curl_close($ch);

        $decoded = json_decode($response, true);
        $this->lastResponse = is_array($decoded) ? $decoded : null;

        // Google يضع الأخطاء في حقل error
        if ($httpCode >= 300) {
            $msg = is_array($decoded) && isset($decoded['error']['message'])
                ? $decoded['error']['message']
                : (string) $response;

            throw new \RuntimeException('AI HTTP bad response [' . $httpCode . ']: ' . $msg);
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException('AI HTTP bad JSON: ' . (string) $response);
        }

        return $decoded;
    }

    /**
     * استخراج النص من Gemini + إرجاع سبب الحجب (إن وُجد).
     */
    protected function extractTextFromGemini(array $resp, ?string &$blockedReason = null): string
    {
        $blockedReason = null;

        if (isset($resp['promptFeedback'])) {
            $pf = $resp['promptFeedback'];

            if (!empty($pf['blockReason'])) {
                $blockedReason = 'blockReason=' . $pf['blockReason'];
            }

            if (!empty($pf['safetyRatings']) && is_array($pf['safetyRatings'])) {
                $cats = array_map(
                    static fn($r) => ($r['category'] ?? '?') . ':' . ($r['probability'] ?? '?'),
                    $pf['safetyRatings']
                );
                $blockedReason = trim(($blockedReason ? $blockedReason . '; ' : '') . 'safety=[' . implode(', ', $cats) . ']');
            }
        }

        // المسار المعتاد: candidates[...].content.parts[*].text
        if (!empty($resp['candidates']) && is_array($resp['candidates'])) {
            foreach ($resp['candidates'] as $cand) {
                if (isset($cand['finishReason']) && $cand['finishReason'] !== 'STOP') {
                    if ($blockedReason === null) {
                        $blockedReason = 'finishReason=' . $cand['finishReason'];
                    }
                }
                $parts = $cand['content']['parts'] ?? [];
                if (is_array($parts)) {
                    foreach ($parts as $part) {
                        if (isset($part['text']) && trim((string) $part['text']) !== '') {
                            return (string) $part['text'];
                        }
                    }
                }
            }
        }

        return '';
    }

    /**
     * تأكيد/إنقاذ JSON (يحاول استخراج أول كائن {..} إن وُجد نص زائد).
     */
    protected function assertJson(string &$text): void
    {
        // تنظيف النص أولاً: إزالة whitespace زائد في البداية والنهاية
        $originalText = $text;
        $text = trim($text);
        
        // محاولة فك التشفير مباشرة
        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return;
        }
        
        $firstError = json_last_error_msg();
        $firstErrorCode = json_last_error();
        
        // محاولة استخراج JSON من النص
        $maybe = $this->extractFirstJsonObject($text);
        if ($maybe !== null) {
            $text = $maybe;
            $decoded = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return;
            }
        }
        
        // إعطاء معلومات أفضل عن الخطأ
        $errorMsg = 'AI did not return pure JSON. JSON Error: ' . json_last_error_msg() . ' (code: ' . json_last_error() . ')';
        if ($firstError !== json_last_error_msg()) {
            $errorMsg .= ' (first attempt: ' . $firstError . ', code: ' . $firstErrorCode . ')';
        }
        
        // إضافة جزء من النص للتشخيص (أول 500 حرف)
        $preview = mb_substr($text, 0, 500, 'UTF-8');
        if (mb_strlen($text, 'UTF-8') > 500) {
            $preview .= '... (total length: ' . mb_strlen($text, 'UTF-8') . ' chars)';
        }
        
        // Log للتحليل
        log_message('error', '[AI Client] JSON decode failed. Error: ' . json_last_error_msg() . ', Text length: ' . strlen($text) . ', Preview: ' . substr($text, 0, 200));
        
        $errorMsg .= ' Text preview: ' . $preview;
        
        throw new \RuntimeException($errorMsg);
    }

    /**
     * يحاول إيجاد أول كائن JSON { ... } داخل النص باستخدام تتبع الأقواس المتداخلة.
     */
    protected function extractFirstJsonObject(string $text): ?string
    {
        // إزالة markdown code blocks أولاً
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/```\s*$/', '', $text);
        $text = trim($text);
        
        // إزالة BOM إن وُجد
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        
        // محاولة فك التشفير مباشرة أولاً
        json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $text;
        }
        
        // البحث عن أول `{` في النص
        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }
        
        // تتبع الأقواس المتداخلة للعثور على نهاية الكائن JSON
        $depth = 0;
        $inString = false;
        $escapeNext = false;
        $end = $start;
        
        for ($i = $start; $i < strlen($text); $i++) {
            $char = $text[$i];
            
            if ($escapeNext) {
                $escapeNext = false;
                continue;
            }
            
            if ($char === '\\') {
                $escapeNext = true;
                continue;
            }
            
            if ($char === '"') {
                $inString = !$inString;
                continue;
            }
            
            if ($inString) {
                continue;
            }
            
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }
        
        if ($depth !== 0 || $end <= $start) {
            // لم نجد كائن JSON صالح - جرب طريقة بديلة
            return $this->extractJsonByRegex($text);
        }
        
        $candidate = substr($text, $start, $end - $start + 1);
        $candidate = trim($candidate);
        
        json_decode($candidate, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $candidate;
        }
        
        // محاولة بديلة باستخدام regex
        return $this->extractJsonByRegex($text);
    }
    
    /**
     * استخراج JSON باستخدام regex كطريقة بديلة
     */
    protected function extractJsonByRegex(string $text): ?string
    {
        // محاولة استخراج JSON باستخدام regex
        // هذا regex يبحث عن كائن JSON كامل مع مراعاة الأقواس المتداخلة
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $text, $matches)) {
            $candidate = $matches[0];
            json_decode($candidate, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $candidate;
            }
        }
        
        // محاولة أخرى: البحث عن JSON بين markdown code blocks
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/is', $text, $matches)) {
            $candidate = trim($matches[1]);
            json_decode($candidate, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $candidate;
            }
        }
        
        return null;
    }
    
    public function withProviderModel(?string $provider, ?string $model): self
    {
        if ($provider) {
            $p = strtolower(trim($provider));
            if ($p === 'openai') {
                $this->provider = 'openai';
            } elseif ($p === 'vertex') {
                $this->provider = 'vertex';
            } else {
                $this->provider = 'gemini';
            }
        }
        if ($model !== null && trim($model) !== '') {
            $this->model = trim($model);
        } elseif ($this->provider === 'openai' && trim((string)$this->model) === '') {
            // افتراضي آمن
            $this->model = 'gpt-4o-mini';
        } elseif ($this->provider === 'vertex' && trim((string)$this->model) === '') {
            // افتراضي لـ Vertex AI
            $this->model = 'gemini-pro';
        }
    
        if ($this->provider === 'openai') {
            $this->apiKey = getenv('AI_OPENAI_API_KEY') ?: null;
        } elseif ($this->provider === 'vertex') {
            // Vertex AI لا يستخدم API Key - يتطلب Service Account أو Access Token
            // لا نضع apiKey هنا لأن getVertexAccessToken() سيتعامل مع المصادقة
            $this->apiKey = null;
        } else {
            $this->apiKey = getenv('AI_GEMINI_API_KEY') ?: null;
        }
    
        return $this;
    }



    /**
     * بناء أجزاء الرسالة (Text + Attachments)
     */
    protected function buildParts(string $userPrompt, array $payload): array
    {
        $parts = [];
        
        // 1. النص
        $parts[] = [
            'text' => "IMPORTANT: Output MUST be JSON only with no extra text outside JSON.\n\n" . $userPrompt
        ];

        // 2. المرفقات (إن وجدت في payload)
        // نتوقع أن تكون في $payload['attachments'] كـ array of ['mime' => ..., 'data' => base64]
        if (!empty($payload['attachments']) && is_array($payload['attachments'])) {
            foreach ($payload['attachments'] as $att) {
                if (!empty($att['mime']) && !empty($att['data'])) {
                    $parts[] = [
                        'inlineData' => [
                            'mimeType' => $att['mime'],
                            'data'     => $att['data']
                        ]
                    ];
                }
            }
        }

        return $parts;
    }
}


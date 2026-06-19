<?php
declare(strict_types=1);

namespace App\Services\Rag;

/**
 * EmbeddingService: خدمة إنشاء embeddings للنصوص
 * يدعم Mistral AI و OpenAI و Gemini
 */
class EmbeddingService
{
    private string $provider;
    private ?string $apiKey;
    private string $model;
    private int $dimension = 768; // Gemini embedding-001 default (768)

    public function __construct()
    {
        // تحديد provider للـ embeddings (يمكن أن يكون مختلفاً عن AI provider)
        // افتراضي: استخدام Gemini (embedding-001)
        $embeddingProvider = getenv('EMBEDDING_PROVIDER') ?: 'gemini';
        
        // قراءة البعد من .env أولاً (إذا كان محدداً)
        $envDimension = getenv('EMBEDDING_DIMENSION');
        if ($envDimension !== false && $envDimension !== '') {
            $this->dimension = (int)$envDimension;
        }
        
        if ($embeddingProvider === 'gemini') {
            $this->provider = 'gemini';
            $this->model = getenv('EMBEDDING_GEMINI_MODEL') ?: 'embedding-001';
            $this->apiKey = getenv('AI_GEMINI_API_KEY') ?: getenv('GEMINI_API_KEY') ?: null;
            // إذا لم يكن البعد محدداً في .env، استخدم القيمة الافتراضية لـ Gemini
            if ($envDimension === false || $envDimension === '') {
                $this->dimension = 768; // Gemini embedding-001 يعطي بعد 768
            }
        } elseif ($embeddingProvider === 'openai') {
            $this->provider = 'openai';
            $this->model = getenv('EMBEDDING_OPENAI_MODEL') ?: 'text-embedding-3-small';
            $this->apiKey = getenv('AI_OPENAI_API_KEY') ?: null;
            // إذا لم يكن البعد محدداً في .env، استخدم القيمة الافتراضية لـ OpenAI
            if ($envDimension === false || $envDimension === '') {
                $this->dimension = 1536; // OpenAI text-embedding-3-small
            }
        } elseif ($embeddingProvider === 'mistral') {
            $this->provider = 'mistral';
            $this->model = getenv('EMBEDDING_MISTRAL_MODEL') ?: 'mistral-embed';
            $this->apiKey = getenv('MISTRAL_API_KEY') ?: null;
            // إذا لم يكن البعد محدداً في .env، استخدم القيمة الافتراضية لـ Mistral
            if ($envDimension === false || $envDimension === '') {
                $this->dimension = 1024; // mistral-embed يعطي بعد 1024 في الإصدارات الحديثة
            }
        } else {
            // Fallback: استخدام Gemini
            $this->provider = 'gemini';
            $this->model = getenv('EMBEDDING_GEMINI_MODEL') ?: 'embedding-001';
            $this->apiKey = getenv('AI_GEMINI_API_KEY') ?: getenv('GEMINI_API_KEY') ?: null;
            if ($envDimension === false || $envDimension === '') {
                $this->dimension = 768;
            }
        }
        
        log_message('info', 'EmbeddingService initialized with provider: ' . $this->provider . ', model: ' . $this->model . ', expected dimension: ' . $this->dimension);
    }

    /**
     * إنشاء embedding لنص واحد
     * 
     * @param string $text النص المراد تحويله
     * @param string $taskType نوع المهمة: 'RETRIEVAL_DOCUMENT' أو 'RETRIEVAL_QUERY' (لـ Gemini فقط)
     * @return array|null embedding كمصفوفة من الأرقام أو null عند الفشل
     */
    public function createEmbedding(string $text, string $taskType = 'RETRIEVAL_DOCUMENT'): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if ($this->provider === 'gemini' || strpos($this->model, 'embedding') !== false) {
            return $this->createGeminiEmbedding($text, $taskType);
        } elseif ($this->provider === 'openai' || strpos($this->model, 'text-embedding') !== false) {
            return $this->createOpenAIEmbedding($text);
        } elseif ($this->provider === 'mistral' || strpos($this->model, 'mistral') !== false) {
            return $this->createMistralEmbedding($text);
        }

        // Fallback: Gemini
        return $this->createGeminiEmbedding($text, $taskType);
    }

    /**
     * إنشاء embedding باستخدام OpenAI
     */
    private function createOpenAIEmbedding(string $text): ?array
    {
        if (!$this->apiKey) {
            log_message('error', 'Missing OpenAI API key for embeddings');
            return null;
        }

        $url = 'https://api.openai.com/v1/embeddings';
        
        $payload = [
            'model' => $this->model,
            'input' => $text,
        ];

        if ($this->dimension === 1536 && $this->model === 'text-embedding-3-small') {
            // يمكن تحديد البعد للـ text-embedding-3-small
            $payload['dimensions'] = 384; // لتتوافق مع Mistral
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200) {
            log_message('error', 'OpenAI embedding error: ' . ($error ?: "HTTP $httpCode"));
            return null;
        }

        $data = json_decode($response, true);
        if (!isset($data['data'][0]['embedding'])) {
            log_message('error', 'Invalid OpenAI embedding response');
            return null;
        }

        $embedding = $data['data'][0]['embedding'];
        
        // تحديث البعد الفعلي من الـ response
        $actualDimension = count($embedding);
        if ($actualDimension !== $this->dimension) {
            log_message('warning', "OpenAI embedding dimension mismatch: expected {$this->dimension}, got {$actualDimension}. Model: {$this->model}");
            // تحديث البعد للتوافق مع البيانات الفعلية
            $this->dimension = $actualDimension;
        }

        return $embedding;
    }

    /**
     * إنشاء embedding باستخدام Mistral AI
     */
    private function createMistralEmbedding(string $text): ?array
    {
        if (!$this->apiKey) {
            log_message('error', 'Missing Mistral API key for embeddings');
            return null;
        }

        $url = 'https://api.mistral.ai/v1/embeddings';
        
        $payload = [
            'model' => $this->model,
            'input' => $text,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200) {
            log_message('error', 'Mistral embedding error: ' . ($error ?: "HTTP $httpCode"));
            return null;
        }

        $data = json_decode($response, true);
        if (!isset($data['data'][0]['embedding'])) {
            log_message('error', 'Invalid Mistral embedding response');
            return null;
        }

        $embedding = $data['data'][0]['embedding'];
        
        // تحديث البعد الفعلي من الـ response
        $actualDimension = count($embedding);
        
        // إذا كان البعد الفعلي أكبر من المتوقع، نقوم بتقليله
        // هذا حل مؤقت - الحل الأفضل هو استخدام model متوافق
        if ($actualDimension > $this->dimension) {
            log_message('warning', "Mistral embedding dimension mismatch: expected {$this->dimension}, got {$actualDimension}. Reducing dimension from {$actualDimension} to {$this->dimension}.");
            log_message('warning', "Note: This is a temporary workaround. The best solution is to use a model that produces {$this->dimension}-dimensional embeddings.");
            
            // تقليل البعد: نأخذ أول N عناصر (حل بسيط)
            // ملاحظة: يمكن استخدام PCA أو طرق أخرى أكثر تعقيداً، لكن هذا الحل البسيط يعمل
            $embedding = array_slice($embedding, 0, $this->dimension);
        } elseif ($actualDimension < $this->dimension) {
            log_message('error', "Mistral embedding dimension is smaller than expected: got {$actualDimension}, expected {$this->dimension}. Cannot pad embeddings.");
            return null;
        } elseif ($actualDimension !== $this->dimension) {
            log_message('error', "Mistral embedding dimension mismatch: expected {$this->dimension}, got {$actualDimension}. Model used: {$this->model}");
            return null;
        }

        return $embedding;
    }

    /**
     * إنشاء embedding باستخدام Gemini AI
     * 
     * @param string $text النص المراد تحويله
     * @param string $taskType نوع المهمة: 'RETRIEVAL_DOCUMENT' (للمستندات) أو 'RETRIEVAL_QUERY' (للأسئلة)
     */
    private function createGeminiEmbedding(string $text, string $taskType = 'RETRIEVAL_DOCUMENT'): ?array
    {
        if (!$this->apiKey) {
            log_message('error', 'Missing Gemini API key for embeddings');
            return null;
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':embedContent?key=' . urlencode($this->apiKey);
        
        $payload = [
            'model' => 'models/' . $this->model,
            'content' => [
                'parts' => [
                    ['text' => $text]
                ]
            ],
            'taskType' => $taskType // 'RETRIEVAL_DOCUMENT' للمستندات، 'RETRIEVAL_QUERY' للأسئلة
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200) {
            log_message('error', 'Gemini embedding error: ' . ($error ?: "HTTP $httpCode"));
            if ($response) {
                log_message('error', 'Gemini response: ' . substr($response, 0, 500));
            }
            return null;
        }

        $data = json_decode($response, true);
        if (!isset($data['embedding']['values'])) {
            log_message('error', 'Invalid Gemini embedding response: ' . substr($response, 0, 500));
            return null;
        }

        $embedding = $data['embedding']['values'];
        
        // تحديث البعد الفعلي من الـ response
        $actualDimension = count($embedding);
        
        // إذا كان البعد الفعلي أكبر من المتوقع، نقوم بتقليله
        if ($actualDimension > $this->dimension) {
            log_message('warning', "Gemini embedding dimension mismatch: expected {$this->dimension}, got {$actualDimension}. Reducing dimension from {$actualDimension} to {$this->dimension}.");
            $embedding = array_slice($embedding, 0, $this->dimension);
        } elseif ($actualDimension < $this->dimension) {
            log_message('error', "Gemini embedding dimension is smaller than expected: got {$actualDimension}, expected {$this->dimension}. Cannot pad embeddings.");
            return null;
        } elseif ($actualDimension !== $this->dimension) {
            log_message('error', "Gemini embedding dimension mismatch: expected {$this->dimension}, got {$actualDimension}. Model used: {$this->model}");
            return null;
        }

        return $embedding;
    }

    /**
     * الحصول على بعد الـ embedding
     */
    public function getDimension(): int
    {
        return $this->dimension;
    }
}


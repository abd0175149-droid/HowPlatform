<?php
declare(strict_types=1);

namespace App\Services\Rag;

use App\Libraries\AiClient;
use App\Services\Rag\EmbeddingService;
use Config\Database;

/**
 * SmartIngestionService: خدمة تحليل الملفات وإدخالها بذكاء باستخدام Gemini
 */
class SmartIngestionService
{
    private AiClient $aiClient;
    private EmbeddingService $embeddingService;
    private $db;

    public function __construct()
    {
        $this->aiClient = new AiClient();
        // استخدام Gemini 1.5 Flash للتحليل لأنه سريع ويدعم سياق طويل
        $this->aiClient->withProviderModel('gemini', 'gemini-1.5-flash');
        
        $this->embeddingService = new EmbeddingService();
        $this->db = Database::connect();
    }

    /**
     * تحليل ملف وإدخاله في قاعدة البيانات
     * 
     * @param string $filePath مسار الملف (PDF/DOCX)
     * @param int $fileId معرف الملف في جدول files
     * @param array $metadata بيانات وصفية إضافية (مثل المادة، السنة، إلخ)
     * @param string|null $customPrompt برومبت مخصص من المستخدم (اختياري)
     */
    public function ingestFile(string $filePath, int $fileId, array $metadata = [], ?string $customPrompt = null): array
    {
        if (!file_exists($filePath)) {
            return ['ok' => false, 'message' => 'File not found'];
        }

        // 1. قراءة الملف وتجهيزه للإرسال
        $mimeType = mime_content_type($filePath);
        $fileData = base64_encode(file_get_contents($filePath));

        // 2. تحديد البرومبت
        // إذا لم يحدد المستخدم برومبت، نستخدم الافتراضي
        $prompt = $customPrompt ?: <<<PROMPT
Analyze the attached document deeply.
Extract the key "Knowledge Units" from it. A Knowledge Unit is a self-contained concept, definition, learning outcome, or summary of a section.
PROMPT;

        try {
            // إرسال الملف مع الطلب
            $payload = [
                'prompt' => $prompt,
                'attachments' => [
                    [
                        'mime' => $mimeType,
                        'data' => $fileData
                    ]
                ]
            ];

            // استخدام SmartAnalyzerPromptMaker الذي يدمج البرومبت مع هيكلة JSON
            $jsonResponse = $this->aiClient->generateJson(
                "", // System prompt handled by PromptMaker
                $payload,
                'smart_analyzer_v1'
            );

            $data = json_decode($jsonResponse, true);
            
            // دعم كلا التنسيقين: "content" أو "sections"
            if (isset($data['content'])) {
                // تنسيق النص الكامل - نحفظه كوحدة واحدة
                $unit = [
                    'title' => 'Formatted Document',
                    'content' => $data['content'],
                    'type' => 'formatted_text',
                    'tags' => []
                ];
                $this->storeUnit($unit, $fileId, $metadata);
                return ['ok' => true, 'units_count' => 1, 'format' => 'content'];
            } elseif (isset($data['sections'])) {
                // تنسيق الأقسام - نحفظ كل قسم على حدة
                $count = 0;
                foreach ($data['sections'] as $section) {
                    $this->storeUnit($section, $fileId, $metadata);
                    $count++;
                }
                return ['ok' => true, 'units_count' => $count, 'format' => 'sections'];
            } elseif (isset($data['units'])) {
                // التنسيق القديم (للتوافق)
                $count = 0;
                foreach ($data['units'] as $unit) {
                    $this->storeUnit($unit, $fileId, $metadata);
                    $count++;
                }
                return ['ok' => true, 'units_count' => $count, 'format' => 'units'];
            } else {
                return ['ok' => false, 'message' => 'Invalid AI response structure', 'raw' => $jsonResponse];
            }

        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function storeUnit(array $unit, int $fileId, array $metadata): void
    {
        $text = $unit['title'] . "\n" . $unit['content'];
        
        log_message('info', '[SmartIngestion] Creating embedding for unit: ' . ($unit['title'] ?? 'untitled'));
        
        $embedding = $this->embeddingService->createEmbedding($text);

        if (!$embedding) {
            log_message('error', '[SmartIngestion] Failed to create embedding for unit: ' . ($unit['title'] ?? 'untitled'));
            return;
        }
        
        log_message('info', '[SmartIngestion] Embedding created successfully. Dimension: ' . count($embedding));

        // دمج الميتاداتا
        $meta = array_merge($metadata, [
            'type' => $unit['type'] ?? 'general',
            'tags' => $unit['tags'] ?? [],
            'title' => $unit['title'] ?? '',
            'file_id' => $fileId // Important for filtering
        ]);

        $data = [
            'document_id' => $fileId, // Assuming this maps to files.id
            'chunk_id' => uniqid('smart_'),
            'text' => $text,
            'file_name' => $metadata['file_name'] ?? 'unknown',
            'chunk_index' => 0, // Not applicable
            'page_count' => 0,
            'word_count' => str_word_count($text),
            'metadata' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'embedding' => json_encode($embedding)
        ];

        try {
            $this->db->table('pdf_embeddings')->insert($data);
            log_message('info', '[SmartIngestion] Unit saved to pdf_embeddings: ' . ($unit['title'] ?? 'untitled'));
        } catch (\Throwable $e) {
            log_message('error', '[SmartIngestion] Failed to insert into pdf_embeddings: ' . $e->getMessage());
        }
    }
}


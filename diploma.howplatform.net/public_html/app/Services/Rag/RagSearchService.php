<?php
declare(strict_types=1);

namespace App\Services\Rag;

use Config\Database;

/**
 * RagSearchService: خدمة البحث في embeddings باستخدام RAG
 * - البحث الدلالي (semantic search) باستخدام cosine similarity
 * - البحث النصي (keyword search) باستخدام FULLTEXT
 * - دمج النتائج (hybrid search)
 */
class RagSearchService
{
    private $db;
    private int $embeddingDimension = 768; // Gemini embedding-001 dimension (افتراضي - 768)
    
    public function __construct(?int $embeddingDimension = null)
    {
        $this->db = Database::connect();
        // السماح بتحديد البعد من الخارج (للتوافق مع مختلف providers)
        if ($embeddingDimension !== null) {
            $this->embeddingDimension = $embeddingDimension;
        }
    }

    /**
     * البحث الهجين: دمج البحث الدلالي والنصي
     * 
     * @param string $query نص السؤال
     * @param array|null $queryEmbedding embedding للسؤال (إن كان متوفراً)
     * @param int $limit عدد النتائج المطلوبة (افتراضي: 10)
     * @param float $semanticWeight وزن البحث الدلالي (0.0 - 1.0، افتراضي: 0.7)
     * @return array قائمة chunks مرتبة حسب الصلة
     */
    public function hybridSearch(
        string $query,
        ?array $queryEmbedding = null,
        int $limit = 10,
        float $semanticWeight = 0.6,
        array $filter = [] // فلترة إضافية (مثل file_id)
    ): array {
        $keywordWeight = 1.0 - $semanticWeight;

        // 1) البحث الدلالي (إن كان embedding متوفراً)
        // نزيد عدد النتائج المسترجعة لضمان جودة أفضل
        $semanticResults = [];
        if ($queryEmbedding !== null && count($queryEmbedding) === $this->embeddingDimension) {
            $semanticResults = $this->semanticSearch($queryEmbedding, $limit * 3, $filter);
        }

        // 2) البحث النصي (نعطيه وزن أكبر للكلمات المفتاحية المحددة)
        // نزيد عدد النتائج المسترجعة
        $keywordResults = $this->keywordSearch($query, $limit * 3, $filter);

        // 3) دمج النتائج
        return $this->mergeResults($semanticResults, $keywordResults, $semanticWeight, $keywordWeight, $limit);
    }

    /**
     * البحث الدلالي باستخدام cosine similarity
     * 
     * @param array $queryEmbedding embedding للسؤال (مصفوفة من الأرقام)
     * @param int $limit عدد النتائج
     * @return array قائمة chunks مع similarity scores
     */
    public function semanticSearch(array $queryEmbedding, int $limit = 10, array $filter = []): array
    {
        $queryDim = count($queryEmbedding);
        
        // إذا كان البعد أكبر من المتوقع، نقوم بتقليله
        if ($queryDim > $this->embeddingDimension) {
            log_message('warning', "Semantic search: Query embedding dimension ({$queryDim}) is larger than expected ({$this->embeddingDimension}). Reducing dimension.");
            $queryEmbedding = array_slice($queryEmbedding, 0, $this->embeddingDimension);
            $queryDim = $this->embeddingDimension;
        }
        
        if ($queryDim !== $this->embeddingDimension) {
            log_message('error', "Semantic search: Query embedding dimension mismatch. Expected {$this->embeddingDimension}, got {$queryDim}. Cannot perform search.");
            return [];
        }

        // بناء الاستعلام
        $builder = $this->db->table('pdf_embeddings')
            ->select('id, document_id, chunk_id, text, file_name, chunk_index, page_count, word_count, metadata, embedding');

        // تطبيق الفلترة
        if (!empty($filter['document_id'])) {
            if (is_array($filter['document_id'])) {
                $builder->whereIn('document_id', $filter['document_id']);
            } else {
                $builder->where('document_id', $filter['document_id']);
            }
        }

        // جلب النتائج
        $results = $builder->get()->getResultArray();
        
        log_message('info', 'Semantic search: Loaded ' . count($results) . ' embeddings from database');

        // حساب cosine similarity لكل نتيجة
        $scoredResults = [];
        $skipped = 0;
        foreach ($results as $row) {
            $embedding = json_decode($row['embedding'], true);
            if (!is_array($embedding)) {
                $skipped++;
                continue;
            }
            
            // التحقق من البعد (يجب أن يكون 384 لأن embeddings في قاعدة البيانات هي Mistral)
            $embeddingDim = count($embedding);
            if ($embeddingDim !== $this->embeddingDimension) {
                // إذا كان البعد مختلفاً، نتخطى هذا السجل
                $skipped++;
                log_message('debug', "Skipping embedding with dimension {$embeddingDim} (expected {$this->embeddingDimension})");
                continue;
            }

            $similarity = $this->cosineSimilarity($queryEmbedding, $embedding);
            $row['similarity'] = $similarity;
            $scoredResults[] = $row;
        }
        
        log_message('info', 'Semantic search: Calculated similarity for ' . count($scoredResults) . ' embeddings, skipped ' . $skipped);

        // ترتيب حسب similarity
        usort($scoredResults, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        // إرجاع أفضل النتائج (مع threshold أدنى للـ similarity)
        // threshold 0.6 يعني أن النتيجة يجب أن تكون مشابهة بنسبة 60% على الأقل
        // رفعنا threshold لتحسين جودة النتائج وتقليل النتائج غير ذات الصلة بشكل أكبر
        $minSimilarity = 0.6; // threshold أعلى لتحسين الدقة وتقليل النتائج غير ذات الصلة
        
        // أولاً: نأخذ أفضل النتائج فقط
        $topResults = array_slice($scoredResults, 0, $limit * 3); // نأخذ 3 أضعاف العدد المطلوب للفلترة
        
        // ثم نفلتر حسب threshold
        $filteredResults = array_filter($topResults, fn($r) => ($r['similarity'] ?? 0) > $minSimilarity);
        
        log_message('info', 'Semantic search: Top ' . count($topResults) . ' results, filtered to ' . count($filteredResults) . ' results with similarity > ' . $minSimilarity);
        
        // إذا كانت النتائج المفلترة أقل من المطلوب، نأخذ أفضل النتائج مع threshold أقل قليلاً
        if (count($filteredResults) < $limit && !empty($scoredResults)) {
            log_message('warning', 'Not enough results above threshold (' . $minSimilarity . '), using fallback threshold 0.4');
            // نأخذ أفضل النتائج مع threshold أقل قليلاً (0.4)
            $fallbackResults = array_filter($scoredResults, fn($r) => ($r['similarity'] ?? 0) > 0.4);
            if (count($fallbackResults) >= $limit) {
                return array_slice($fallbackResults, 0, $limit);
            }
            // إذا لم توجد نتائج حتى مع threshold 0.4، نأخذ threshold 0.35
            log_message('warning', 'Still not enough results, using threshold 0.35');
            $fallbackResults2 = array_filter($scoredResults, fn($r) => ($r['similarity'] ?? 0) > 0.35);
            if (count($fallbackResults2) >= $limit) {
                return array_slice($fallbackResults2, 0, $limit);
            }
            // كحل أخير، نعيد أفضل النتائج فقط
            log_message('warning', 'Very few results found, returning top ' . $limit . ' results with any similarity');
            return array_slice($scoredResults, 0, $limit);
        }

        // إرجاع أفضل النتائج
        return array_slice($filteredResults, 0, $limit);
    }

    /**
     * البحث النصي باستخدام FULLTEXT
     * 
     * @param string $query نص البحث
     * @param int $limit عدد النتائج
     * @return array قائمة chunks مع relevance scores
     */
    public function keywordSearch(string $query, int $limit = 10, array $filter = []): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        // تنظيف الاستعلام للبحث الآمن
        $searchTerms = $this->prepareSearchTerms($query);

        // بناء شرط الفلترة
        $filterSql = "";
        $filterParams = [];
        if (!empty($filter['document_id'])) {
            if (is_array($filter['document_id'])) {
                $ids = array_map('intval', $filter['document_id']);
                if (!empty($ids)) {
                    $filterSql = " AND document_id IN (" . implode(',', $ids) . ")";
                }
            } else {
                $filterSql = " AND document_id = ?";
                $filterParams[] = (int)$filter['document_id'];
            }
        }

        // استخدام MATCH AGAINST للبحث FULLTEXT
        // ملاحظة: إذا لم يعمل FULLTEXT، نستخدم LIKE كبديل
        try {
            $sql = "SELECT id, document_id, chunk_id, text, file_name, chunk_index, page_count, word_count, metadata,
                MATCH(text) AGAINST(? IN BOOLEAN MODE) as relevance
                FROM pdf_embeddings
                WHERE MATCH(text) AGAINST(? IN BOOLEAN MODE)
                {$filterSql}
                ORDER BY relevance DESC
                LIMIT ?";
            
            $params = array_merge([$searchTerms, $searchTerms], $filterParams, [$limit]);

            $results = $this->db->query($sql, $params)->getResultArray();
        } catch (\Exception $e) {
            // Fallback: استخدام LIKE للبحث
            log_message('warning', 'FULLTEXT search failed, using LIKE: ' . $e->getMessage());
            $words = explode(' ', $searchTerms);
            $words = array_filter($words, fn($w) => mb_strlen(trim($w, '+*')) > 2);
            
            $builder = $this->db->table('pdf_embeddings')
                ->select('id, document_id, chunk_id, text, file_name, chunk_index, page_count, word_count, metadata');
            
            // تطبيق الفلترة في Fallback
            if (!empty($filter['document_id'])) {
                if (is_array($filter['document_id'])) {
                    $builder->whereIn('document_id', $filter['document_id']);
                } else {
                    $builder->where('document_id', $filter['document_id']);
                }
            }

            foreach ($words as $word) {
                $cleanWord = trim($word, '+*');
                $builder->like('text', $cleanWord);
            }
            
            $results = $builder
                ->limit($limit)
                ->get()
                ->getResultArray();
            
            // إضافة score بسيط
            foreach ($results as &$result) {
                $result['score'] = 0.5; // score افتراضي
            }
        }

        // تحويل relevance إلى score (0.0 - 1.0)
        foreach ($results as &$result) {
            $result['score'] = min(1.0, (float)$result['relevance'] / 10.0); // تطبيع
            unset($result['relevance']);
        }

        return $results;
    }

    /**
     * دمج نتائج البحث الدلالي والنصي
     * 
     * @param array $semanticResults نتائج البحث الدلالي
     * @param array $keywordResults نتائج البحث النصي
     * @param float $semanticWeight وزن البحث الدلالي
     * @param float $keywordWeight وزن البحث النصي
     * @param int $limit عدد النتائج النهائية
     * @return array نتائج مدمجة ومرتبة
     */
    private function mergeResults(
        array $semanticResults,
        array $keywordResults,
        float $semanticWeight,
        float $keywordWeight,
        int $limit
    ): array {
        // إنشاء map للنتائج حسب id
        $merged = [];

        // إضافة نتائج البحث الدلالي
        foreach ($semanticResults as $result) {
            $id = (int)$result['id'];
            if (!isset($merged[$id])) {
                $merged[$id] = $result;
                $merged[$id]['final_score'] = 0.0;
            }
            $merged[$id]['final_score'] += ($result['similarity'] ?? 0.0) * $semanticWeight;
        }

        // إضافة نتائج البحث النصي
        foreach ($keywordResults as $result) {
            $id = (int)$result['id'];
            if (!isset($merged[$id])) {
                $merged[$id] = $result;
                $merged[$id]['final_score'] = 0.0;
            }
            $merged[$id]['final_score'] += ($result['score'] ?? 0.0) * $keywordWeight;
        }

        // ترتيب حسب final_score
        usort($merged, fn($a, $b) => $b['final_score'] <=> $a['final_score']);

        // إرجاع أفضل النتائج
        return array_slice($merged, 0, $limit);
    }

    /**
     * حساب cosine similarity بين embeddingين
     * 
     * @param array $a embedding الأول
     * @param array $b embedding الثاني
     * @return float قيمة similarity (0.0 - 1.0)
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    /**
     * تحضير مصطلحات البحث للاستخدام في FULLTEXT
     * 
     * @param string $query نص البحث
     * @return string مصطلحات البحث المعدة
     */
    private function prepareSearchTerms(string $query): string
    {
        // إزالة أحرف خاصة
        $query = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $query);
        
        // تقسيم إلى كلمات
        $words = preg_split('/\s+/', trim($query));
        
        // تصفية الكلمات (إزالة كلمات قصيرة جداً وكلمات شائعة)
        $stopWords = ['التي', 'التي', 'التي', 'التي', 'التي', 'التي', 'التي', 'التي', 'التي', 'التي'];
        $words = array_filter($words, function($w) use ($stopWords) {
            $w = trim($w);
            return mb_strlen($w) > 2 && !in_array($w, $stopWords);
        });
        
        // إضافة + قبل كل كلمة (يجب أن تظهر جميعها)
        // لكن نستخدم OR بدلاً من AND للبحث المرن
        $words = array_map(fn($w) => '+' . $w . '*', $words);
        
        return implode(' ', $words);
    }

    /**
     * تنظيف اسم الملف من الامتدادات والرموز
     * 
     * @param string $fileName اسم الملف الأصلي
     * @return string اسم الملف المنظف
     */
    private function cleanFileName(string $fileName): string
    {
        // إزالة الامتدادات (.json, .pdf, .docx, إلخ)
        $fileName = preg_replace('/\.(json|pdf|docx?|txt|odt)$/i', '', $fileName);
        
        // استبدال الشرطات السفلية والشرطات بمسافات
        $fileName = str_replace(['_', '-'], ' ', $fileName);
        
        // إزالة المسافات المتعددة
        $fileName = preg_replace('/\s+/', ' ', $fileName);
        
        // تقليم المسافات من البداية والنهاية
        $fileName = trim($fileName);
        
        return $fileName;
    }

    /**
     * تنسيق السياق المستخرج للاستخدام في prompt
     * 
     * @param array $chunks قائمة chunks
     * @param int $maxLength الحد الأقصى للطول الإجمالي
     * @return string سياق منسق
     */
    public function formatContext(array $chunks, int $maxLength = 8000): string
    {
        if (empty($chunks)) {
            return '';
        }

        $context = [];
        $currentLength = 0;
        $fileGroups = []; // لتجميع chunks حسب الملف

        // تجميع chunks حسب الملف
        foreach ($chunks as $chunk) {
            $text = '';
            $fileName = 'غير معروف';
            $chunkIndex = 0;
            $pageCount = 0;
            $similarity = 0;
            
            if (is_array($chunk)) {
                $text = trim($chunk['text'] ?? '');
                $fileName = $chunk['file_name'] ?? 'غير معروف';
                $chunkIndex = (int)($chunk['chunk_index'] ?? 0);
                $pageCount = (int)($chunk['page_count'] ?? 0);
                $similarity = (float)($chunk['similarity'] ?? $chunk['final_score'] ?? 0);
            } elseif (is_object($chunk)) {
                $text = trim($chunk->text ?? '');
                $fileName = $chunk->file_name ?? 'غير معروف';
                $chunkIndex = (int)($chunk->chunk_index ?? 0);
                $pageCount = (int)($chunk->page_count ?? 0);
                $similarity = (float)($chunk->similarity ?? $chunk->final_score ?? 0);
            }

            if ($text === '' || $fileName === 'غير معروف') {
                continue;
            }

            if (!isset($fileGroups[$fileName])) {
                $fileGroups[$fileName] = [];
            }

            $fileGroups[$fileName][] = [
                'text' => $text,
                'chunk_index' => $chunkIndex,
                'page_count' => $pageCount,
                'similarity' => $similarity
            ];
        }

        // ترتيب الملفات حسب أعلى similarity
        foreach ($fileGroups as $fileName => $fileChunks) {
            usort($fileChunks, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
        }

        // بناء السياق مع معلومات الملف بشكل أوضح
        foreach ($fileGroups as $fileName => $fileChunks) {
            // تنظيف اسم الملف من الامتدادات والرموز
            $cleanedFileName = $this->cleanFileName($fileName);
            $fileHeader = sprintf(
                "\n═══════════════════════════════════════════════════════════\n📄 المرجع: %s\n═══════════════════════════════════════════════════════════\n",
                $cleanedFileName
            );
            
            $headerLength = mb_strlen($fileHeader);
            if ($currentLength + $headerLength > $maxLength) {
                break;
            }
            
            $context[] = $fileHeader;
            $currentLength += $headerLength;

            foreach ($fileChunks as $chunk) {
                $chunkText = sprintf(
                    "[جزء %d%s - درجة الصلة: %.1f%%]\n%s",
                    $chunk['chunk_index'] + 1,
                    $chunk['page_count'] > 0 ? " من {$chunk['page_count']} صفحة" : '',
                    $chunk['similarity'] * 100,
                    $chunk['text']
                );

                $chunkLength = mb_strlen($chunkText);
                if ($currentLength + $chunkLength > $maxLength) {
                    break 2; // نخرج من الحلقتين
                }

                $context[] = $chunkText;
                $currentLength += $chunkLength;
            }
        }

        return implode("\n\n", $context);
    }
}


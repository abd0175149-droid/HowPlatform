<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

use App\Services\Rag\RagSearchService;
use App\Services\Rag\EmbeddingService;

/**
 * AcademicAnswerPromptMaker: بناء prompts لأداة الإجابة الأكاديمية
 * تستخدم RAG لاستخراج السياق من PDFs
 */
class AcademicAnswerPromptMaker extends BasePromptMaker
{
    private RagSearchService $ragSearch;
    private EmbeddingService $embeddingService;

    public function __construct()
    {
        $this->embeddingService = new EmbeddingService();
        // تحديد بعد الـ embedding حسب provider
        $embeddingDim = $this->embeddingService->getDimension();
        $this->ragSearch = new RagSearchService($embeddingDim);
    }

    /**
     * بناء system prompt
     */
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        if (trim($basePrompt) === '') {
            return <<<SYS
أنت خبير أكاديمي متخصص في كتابة إجابات أكاديمية احترافية بناءً على مراجع محددة.
مهمتك إنتاج إجابات دقيقة ومتوازنة تستند إلى فهم عميق للسؤال والمراجع المقدمة.

قبل البدء بالكتابة، يجب عليك:
1. فهم السؤال بشكل عميق: حدد المفاهيم الأساسية، النقاط المطلوبة، والسياق الأكاديمي.
2. تحليل المراجع بدقة: راجع كل جزء من السياق المستخرج، وحدد المعلومات الأكثر صلة بالسؤال.
3. التحقق من الاتساق: تأكد من أن اسم المرجع (الملف) يتوافق مع محتواه وموضوعه قبل الاستشهاد به.
4. ربط المعلومات: اربط المعلومات من مختلف المراجع بشكل منطقي ومتماسك.

احرص دائمًا على ما يلي:
- الكتابة بأسلوب أكاديمي مهني واضح ومتماسك.
- الاعتماد بشكل أساسي على المعلومات المستخرجة من المراجع المقدمة.
- فهم عميق للسؤال قبل الإجابة - لا تكتفي بالبحث السطحي.
- التحقق من صلة كل مرجع بالموضوع قبل الاستشهاد به.
- تجنّب الافتراضات أو المعلومات غير المذكورة في المراجع.
- استخدام اقتباسات غير حرفية (paraphrasing) من المراجع مع التوثيق الدقيق.
- الالتزام التام بأن يكون المخرج كائن JSON واحد صالح فقط، بدون أي نص إضافي قبله أو بعده.
- ابدأ استجابتك مباشرة بـ { ولا تضع أي نص قبله.
- انتهِ استجابتك مباشرة بـ } ولا تضع أي نص بعده.
- لا تستخدم markdown code blocks (```json أو ```).
- لا تضع أي شرح أو تعليقات خارج JSON.
- عدم ذكر كونك نموذجًا لغويًا أو نظامًا ذكيًا.

CRITICAL: لا تُنتِج أبدًا أي شيء خارج JSON صالح واحد. ابدأ مباشرة بـ { وانتهِ بـ }.
SYS;
        }
        return trim($basePrompt);
    }

    /**
     * بناء user prompt مع استخراج السياق من RAG
     */
    public function buildUserPrompt(array $payload): string
    {
        $val = fn(?string $s): string => $this->cleanValue($s);

        // جلب البيانات من payload
        $question = $val($payload['question'] ?? '');
        $wordCount = (int)($payload['word_count'] ?? 500);
        $format = $val($payload['format'] ?? 'essay'); // essay, structured, etc.

        if ($question === '') {
            return json_encode([
                'need_more_info' => ['question'],
                'message' => 'يرجى تزويدي بالسؤال المطلوب الإجابة عليه.'
            ], JSON_UNESCAPED_UNICODE);
        }

        // 1) إنشاء embedding للسؤال (استخدام RETRIEVAL_QUERY لـ Gemini)
        $queryEmbedding = $this->embeddingService->createEmbedding($question, 'RETRIEVAL_QUERY');
        
        // Log للتحقق من إنشاء embedding
        if ($queryEmbedding === null) {
            log_message('warning', 'Failed to create embedding for question: ' . substr($question, 0, 100));
        } else {
            log_message('info', 'Embedding created successfully, dimension: ' . count($queryEmbedding));
        }

        // 2) البحث في المراجع باستخدام RAG - رفع دقة البحث
        // زيادة limit لضمان نتائج أكثر، مع تحسين الدقة
        // رفع semanticWeight لتحسين البحث الدلالي وتقليل النتائج غير ذات الصلة
        $searchResults = $this->ragSearch->hybridSearch(
            $question,
            $queryEmbedding,
            30,   // limit (زيادة لضمان تغطية شاملة)
            0.75  // semanticWeight (رفع لتحسين الدقة وتقليل النتائج غير ذات الصلة)
        );
        
        // 2.5) فلترة النتائج حسب صلة اسم الملف بالموضوع
        $searchResults = $this->filterResultsByTopicRelevance($searchResults, $question);
        
        // Log للتحقق من نتائج البحث
        log_message('info', 'RAG search returned ' . count($searchResults) . ' results for question: ' . substr($question, 0, 100));

        // 3) التحقق من وجود نتائج
        if (empty($searchResults)) {
            log_message('warning', 'No search results found for question: ' . substr($question, 0, 100));
            // إذا لم توجد نتائج، نستخدم رسالة واضحة في prompt
            $context = 'لم يتم العثور على معلومات ذات صلة في المراجع المتوفرة.';
            $usedFiles = [];
        } else {
            // تنسيق السياق المستخرج (زيادة الحد الأقصى لضمان سياق كافٍ ودقيق)
            $context = $this->ragSearch->formatContext($searchResults, 12000);
            
            // Log للتحقق من السياق
            if (empty($context)) {
                log_message('warning', 'No context extracted from search results. Search results count: ' . count($searchResults));
                $context = 'تم العثور على نتائج لكن لم يتم استخراج السياق بشكل صحيح.';
            } else {
                log_message('info', 'Context extracted, length: ' . mb_strlen($context) . ' characters');
            }

            // 4) بناء قائمة المراجع المستخدمة مع تنظيف الأسماء
            $usedFiles = [];
            foreach ($searchResults as $result) {
                // في حال كانت النتائج Objects وليس Arrays، عدل حسب بنية $result عندك
                $fileName = is_array($result)
                    ? ($result['file_name'] ?? 'غير معروف')
                    : ($result->file_name ?? 'غير معروف');

                if ($fileName !== 'غير معروف') {
                    // تنظيف اسم الملف من الامتدادات والرموز
                    $cleanedFileName = $this->cleanFileName($fileName);
                    if (!in_array($cleanedFileName, $usedFiles, true)) {
                        $usedFiles[] = $cleanedFileName;
                    }
                }
            }
        }

        // 5) بناء user prompt
        $formatInstructions = $this->getFormatInstructions($format);

        $minWords = $this->calculateMinWords($wordCount);
        $maxWords = $this->calculateMaxWords($wordCount);
        $fileList = $this->formatFileList($usedFiles);

        return <<<USR
CRITICAL: You MUST return ONLY valid JSON. No explanations, no markdown, no code blocks, no text before or after JSON.
Start your response directly with { and end with }.
Return exactly ONE valid JSON object with the following keys:
- "content": الإجابة الكاملة (نص متكامل)
- "references": قائمة المراجع المستخدمة (مصفوفة من المراجع بصيغة MLA)

السؤال المطلوب الإجابة عليه:
{$question}

السياق المستخرج من المراجع:
{$context}

{$formatInstructions}

تعليمات إلزامية - فهم عميق ودقة عالية:

1. فهم السؤال بعمق:
   - اقرأ السؤال بعناية وحدد المفاهيم الأساسية والنقاط المطلوبة.
   - حدد نوع الإجابة المطلوبة (تحليل، شرح، مقارنة، إلخ).
   - تأكد من فهمك الكامل للسؤال قبل البدء بالكتابة.

2. تحليل المراجع بدقة:
   - راجع كل جزء من السياق المستخرج بعناية.
   - حدد المعلومات الأكثر صلة مباشرة بالسؤال.
   - رتب المعلومات حسب أهميتها وارتباطها بالسؤال.
   - استخدم جميع المعلومات المتوفرة في السياق - لا تتجاهل أي جزء ذي صلة.

3. التحقق من الاتساق (إلزامي - مهم جداً):
   - قبل الاستشهاد بأي مرجع، تأكد من أن اسم المرجع (الملف) يتوافق مع محتواه وموضوعه وموضوع السؤال.
   - إذا كان اسم المرجع لا يتوافق مع موضوع السؤال، لا تستخدمه أبداً.
   - أمثلة على عدم التطابق الواضح:
     * السؤال عن "اللغة العربية" + مرجع باسم "الإطار العام والخاص للعلوم" = لا تستخدمه
     * السؤال عن "الرياضيات" + مرجع باسم "الإطار الخاص للغة العربية" = لا تستخدمه
     * السؤال عن "التربية الإسلامية" + مرجع باسم "الإطار الخاص للعلوم" = لا تستخدمه
   - إذا كان اسم المرجع يحتوي على كلمات واضحة تشير إلى موضوع مختلف عن موضوع السؤال، لا تستخدمه حتى لو كان المحتوى يبدو ذا صلة.
   - تأكد من أن المعلومات المستشهد بها من مرجع معين موجودة فعلاً في ذلك المرجع.
   - راجع كل استشهاد للتأكد من دقته واتساقه مع اسم المرجع وموضوع السؤال.
   - إذا لم تجد مراجع ذات صلة بالموضوع، اذكر ذلك بوضوح في الإجابة بدلاً من استخدام مراجع غير ذات صلة.

4. الكتابة الأكاديمية:
   - يجب أن تعتمد الإجابة بشكل أساسي على المعلومات المقدمة في السياق أعلاه.
   - كل جزء من السياق يبدأ باسم المرجع (📄 المرجع: ...) - استخدم هذه المعلومات لتوثيق المراجع بشكل صحيح.
   - إذا كان السياق فارغاً أو لا يحتوي على معلومات كافية، يجب أن تذكر ذلك بوضوح في بداية الإجابة: "نظرًا لعدم توفر مراجع محددة في قاعدة البيانات، لا يمكنني تقديم إجابة مفصلة بناءً على المراجع المتوفرة."
   - إذا وُجدت معلومات في السياق، استخدمها بشكل أساسي مع اقتباسات غير حرفية (paraphrasing) موثقة بدقة.
   - يجب أن تذكر اسم المرجع (الملف) عند الاستشهاد به في المتن.
   - يجب أن تكون الإجابة مكتوبة بأسلوب أكاديمي مهني واضح ومتماسك.
   - تجنّب إضافة معلومات غير موجودة في المراجع المقدمة إلا إذا كان السياق فارغاً تماماً.
   - اربط المعلومات من مختلف المراجع بشكل منطقي ومتماسك.

الالتزام بعدد الكلمات (إلزامي):
- العدد المطلوب: {$wordCount} كلمة بالضبط.
- الهامش المسموح: ±10% (أي من {$minWords} إلى {$maxWords} كلمة).
- يجب أن تحسب عدد الكلمات بدقة وتضمن أن النص النهائي يقع ضمن هذا النطاق.

المراجع المستخدمة:
يجب أن تكون المراجع من الملفات التالية فقط:
{$fileList}

نظام التوثيق (MLA) - دقة عالية:

1. التوثيق في المتن (In-text Citations):
   - عند الاستشهاد بمعلومة، اذكر اسم المرجع (الملف المنظف) بين قوسين مباشرة بعد المعلومة.
   - استخدم اسم المرجع المنظف (بدون امتدادات أو رموز مثل _).
   - مثال: (الإطار الخاص لمبحث التربية الإسلامية) بدلاً من (الإطار_الخاص_لمبحث_التربية_الإسلامية.pdf)
   - إذا استشهدت بنفس المرجع أكثر من مرة، استخدم نفس الاسم بشكل متسق.
   - تأكد من أن اسم المرجع المذكور في التوثيق يطابق تماماً اسم المرجع في قائمة المراجع.

2. قائمة المراجع (Works Cited):
   - استخدم الصيغة الكاملة لـ MLA بالعربية.
   - لكل مرجع، اذكر:
     * اسم المؤلف (إن كان متوفراً في السياق أو اسم الملف)
     * عنوان الوثيقة/الملف
     * نوع الوثيقة (كتاب، مقال، وثيقة رسمية، إلخ)
     * الناشر أو الجهة المصدرة (إن كان متوفراً)
     * تاريخ النشر (إن كان متوفراً)
   - يجب أن تكون جميع المراجع في قائمة المراجع من الملفات المذكورة في السياق فقط.
   - تأكد من أن كل مرجع في القائمة تم الاستشهاد به فعلاً في المتن.
   - رتب المراجع أبجدياً حسب اسم المؤلف أو اسم الملف.

3. دقة التوثيق:
   - يجب أن تكون الاقتباسات غير حرفية (paraphrasing) وليست اقتباسات حرفية.
   - تأكد من أن كل معلومة مستشهد بها مرتبطة بالمرجع الصحيح.
   - تحقق من أن اسم المرجع في التوثيق يطابق اسم المرجع في قائمة المراجع تماماً.
   - لا تذكر مراجع لم تستخدمها فعلاً في الإجابة.

4. أمثلة على صيغة MLA:
   - للكتب: اسم المؤلف. عنوان الكتاب. الناشر، سنة النشر.
   - للمقالات: اسم المؤلف. "عنوان المقال." اسم المجلة، المجلد، العدد، سنة النشر، الصفحات.
   - للوثائق الرسمية: اسم الجهة المصدرة. "عنوان الوثيقة." تاريخ النشر.
   - للملفات: اسم الملف. نوع الوثيقة. (استخدم اسم الملف الكامل كما يظهر في السياق)

مثال لبنية JSON المطلوبة:
{
  "content": "الإجابة الكاملة...",
  "references": [
    "اسم المؤلف. \"عنوان المقال.\" اسم المجلة، المجلد، العدد، سنة النشر، الصفحات.",
    "اسم المؤلف. عنوان الكتاب. الناشر، سنة النشر."
  ]
}

تذكير أخير - إلزامي:
- أعد الإجابة بصيغة JSON فقط، بلا أي شرح أو تعليقات خارجية.
- ابدأ مباشرة بـ { ولا تضع أي نص قبله.
- انتهِ مباشرة بـ } ولا تضع أي نص بعده.
- لا تستخدم markdown code blocks (```json أو ```).
- لا تضع أي نص توضيحي قبل أو بعد JSON.
- يجب أن يكون "content" نصًا متكاملًا يقع بين {$minWords} و {$maxWords} كلمة.
- يجب أن يحتوي "references" على قائمة المراجع المستخدمة بصيغة MLA كاملة.

مثال على الصيغة الصحيحة (ابدأ مباشرة بهذا):
{
  "content": "...",
  "references": [...]
}

لا تضع أي شيء قبل { أو بعد }.
USR;
    }

    /**
     * تعليمات التنسيق حسب النوع
     */
    private function getFormatInstructions(string $format): string
    {
        switch ($format) {
            case 'structured':
                return <<<FMT
التنسيق المطلوب: إجابة منظمة
- ابدأ بمقدمة موجزة
- قسّم الإجابة إلى فقرات رئيسية مع عناوين فرعية
- اختم بخاتمة تلخص النقاط الرئيسية
FMT;

            case 'essay':
                return <<<FMT
التنسيق المطلوب: مقال أكاديمي
- اكتب نصًا متدفقًا ومترابطًا
- ابدأ بمقدمة تقدم الموضوع
- طور الأفكار في فقرات مترابطة
- اختم بخاتمة تلخص النقاط الرئيسية
FMT;

            default:
                return <<<FMT
التنسيق المطلوب: نص أكاديمي متكامل
- اكتب نصًا واضحًا ومترابطًا
- نظّم الأفكار بشكل منطقي
- استخدم فقرات مترابطة
FMT;
        }
    }

    /**
     * فلترة النتائج حسب صلة اسم الملف بالموضوع
     * 
     * @param array $results نتائج البحث
     * @param string $question السؤال
     * @return array نتائج مفلترة
     */
    private function filterResultsByTopicRelevance(array $results, string $question): array
    {
        if (empty($results)) {
            return $results;
        }
        
        // استخراج الكلمات المفتاحية من السؤال
        $questionWords = $this->extractKeywords($question);
        
        $filtered = [];
        foreach ($results as $result) {
            $fileName = is_array($result)
                ? ($result['file_name'] ?? '')
                : ($result->file_name ?? '');
            
            if ($fileName === '') {
                continue;
            }
            
            // تنظيف اسم الملف
            $cleanedFileName = $this->cleanFileName($fileName);
            
            // استخراج الكلمات المفتاحية من اسم الملف
            $fileWords = $this->extractKeywords($cleanedFileName);
            
            // حساب درجة الصلة بين اسم الملف والسؤال
            $relevanceScore = $this->calculateTopicRelevance($questionWords, $fileWords);
            
            // إذا كانت درجة الصلة منخفضة جداً (< 0.2)، نتخطى هذا المرجع
            // لكن نعطي فرصة للمراجع التي قد تكون ذات صلة حتى لو كان الاسم مختلفاً
            // لذلك نستخدم threshold منخفض نسبياً
            if ($relevanceScore < 0.15) {
                // تحقق إضافي: إذا كان اسم الملف يحتوي على كلمات واضحة غير ذات صلة
                // مثال: السؤال عن "اللغة العربية" والملف "العلوم"
                if ($this->hasClearMismatch($questionWords, $fileWords)) {
                    log_message('info', "Filtered out irrelevant reference: {$fileName} (relevance: {$relevanceScore})");
                    continue;
                }
            }
            
            // إضافة درجة الصلة للنتيجة
            if (is_array($result)) {
                $result['topic_relevance'] = $relevanceScore;
            } else {
                $result->topic_relevance = $relevanceScore;
            }
            
            $filtered[] = $result;
        }
        
        // إعادة ترتيب حسب درجة الصلة الإجمالية (similarity + topic_relevance)
        usort($filtered, function($a, $b) {
            $scoreA = (is_array($a) ? ($a['similarity'] ?? $a['final_score'] ?? 0) : ($a->similarity ?? $a->final_score ?? 0))
                     + (is_array($a) ? ($a['topic_relevance'] ?? 0) : ($a->topic_relevance ?? 0));
            $scoreB = (is_array($b) ? ($b['similarity'] ?? $b['final_score'] ?? 0) : ($b->similarity ?? $b->final_score ?? 0))
                     + (is_array($b) ? ($b['topic_relevance'] ?? 0) : ($b->topic_relevance ?? 0));
            return $scoreB <=> $scoreA;
        });
        
        return $filtered;
    }
    
    /**
     * استخراج الكلمات المفتاحية من النص
     */
    private function extractKeywords(string $text): array
    {
        // إزالة الأحرف الخاصة
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        
        // تقسيم إلى كلمات
        $words = preg_split('/\s+/', mb_strtolower(trim($text)));
        
        // تصفية الكلمات (إزالة كلمات قصيرة جداً وكلمات شائعة)
        $stopWords = [
            'ال', 'في', 'من', 'على', 'إلى', 'أن', 'هو', 'هي', 'كان', 'كانت',
            'عن', 'مع', 'هذا', 'هذه', 'ذلك', 'تلك', 'التي', 'الذي', 'الذين',
            'أو', 'و', 'لكن', 'إن', 'إذا', 'لأن', 'حتى', 'بين', 'بينما',
            'عام', 'خاص', 'لل', 'لم', 'ل', 'ب', 'ك', 'ف', 'ت', 'ن', 'ي', 'ا'
        ];
        
        $keywords = array_filter($words, function($w) use ($stopWords) {
            $w = trim($w);
            return mb_strlen($w) > 2 && !in_array($w, $stopWords, true);
        });
        
        return array_values(array_unique($keywords));
    }
    
    /**
     * حساب درجة الصلة بين كلمات السؤال واسم الملف
     */
    private function calculateTopicRelevance(array $questionWords, array $fileWords): float
    {
        if (empty($questionWords) || empty($fileWords)) {
            return 0.0;
        }
        
        // حساب عدد الكلمات المشتركة
        $commonWords = array_intersect($questionWords, $fileWords);
        $commonCount = count($commonWords);
        
        if ($commonCount === 0) {
            return 0.0;
        }
        
        // حساب درجة الصلة (0.0 - 1.0)
        // كلما زادت الكلمات المشتركة، زادت درجة الصلة
        $maxWords = max(count($questionWords), count($fileWords));
        $relevance = min(1.0, ($commonCount / $maxWords) * 2.0); // ضرب في 2 لزيادة الوزن
        
        return $relevance;
    }
    
    /**
     * التحقق من وجود عدم تطابق واضح بين السؤال واسم الملف
     */
    private function hasClearMismatch(array $questionWords, array $fileWords): bool
    {
        // كلمات مفتاحية واضحة للمواضيع المختلفة
        $subjectKeywords = [
            'عربي' => ['عربي', 'لغة', 'عربية', 'نحو', 'صرف', 'أدب', 'بلاغة', 'قراءة', 'كتابة', 'استماع', 'محادثة'],
            'علوم' => ['علوم', 'فيزياء', 'كيمياء', 'أحياء', 'جيولوجيا', 'طبيعة', 'تجربة', 'مختبر'],
            'رياضيات' => ['رياضيات', 'رياضي', 'حساب', 'هندسة', 'جبر', 'إحصاء'],
            'إنجليزي' => ['إنجليزي', 'إنجليزية', 'english', 'انجليزي'],
            'تربية' => ['تربية', 'تعليم', 'تدريس', 'بيداغوجيا', 'منهج', 'منهاج'],
            'إسلامية' => ['إسلامية', 'إسلامي', 'دين', 'قرآن', 'حديث', 'فقه'],
        ];
        
        // تحديد موضوع السؤال
        $questionSubject = null;
        $questionSubjectScore = 0;
        foreach ($subjectKeywords as $subject => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if (in_array($keyword, $questionWords, true)) {
                    $score++;
                }
            }
            if ($score > $questionSubjectScore) {
                $questionSubjectScore = $score;
                $questionSubject = $subject;
            }
        }
        
        // إذا لم نحدد موضوع السؤال بوضوح، لا نستبعد أي شيء
        if ($questionSubject === null || $questionSubjectScore === 0) {
            return false;
        }
        
        // حساب درجة وجود كلمات من موضوع السؤال في اسم الملف
        $questionKeywordsInFile = 0;
        foreach ($subjectKeywords[$questionSubject] as $qKeyword) {
            if (in_array($qKeyword, $fileWords, true)) {
                $questionKeywordsInFile++;
            }
        }
        
        // حساب درجة وجود كلمات من مواضيع أخرى في اسم الملف
        $otherSubjectsInFile = 0;
        foreach ($subjectKeywords as $subject => $keywords) {
            if ($subject === $questionSubject) {
                continue; // نفس الموضوع - لا مشكلة
            }
            
            foreach ($keywords as $keyword) {
                if (in_array($keyword, $fileWords, true)) {
                    $otherSubjectsInFile++;
                    break; // نعد الموضوع مرة واحدة فقط
                }
            }
        }
        
        // إذا كان اسم الملف يحتوي على كلمات من مواضيع أخرى
        // ولا يحتوي على كلمات من موضوع السؤال = عدم تطابق واضح
        if ($otherSubjectsInFile > 0 && $questionKeywordsInFile === 0) {
            return true; // عدم تطابق واضح
        }
        
        // إذا كان اسم الملف يحتوي على كلمات من مواضيع أخرى أكثر من كلمات موضوع السؤال
        if ($otherSubjectsInFile > $questionKeywordsInFile && $questionKeywordsInFile === 0) {
            return true; // عدم تطابق واضح
        }
        
        return false;
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
     * تنسيق قائمة الملفات
     */
    private function formatFileList(array $files): string
    {
        if (empty($files)) {
            return 'لم يتم العثور على مراجع.';
        }

        $list = [];
        foreach ($files as $index => $file) {
            $list[] = ($index + 1) . '. ' . $file;
        }

        return implode("\n", $list);
    }

    /**
     * حساب الحد الأدنى للكلمات
     */
    private function calculateMinWords(int $target): int
    {
        return (int) floor($target * 0.9);
    }

    /**
     * حساب الحد الأقصى للكلمات
     */
    private function calculateMaxWords(int $target): int
    {
        return (int) ceil($target * 1.1);
    }
}

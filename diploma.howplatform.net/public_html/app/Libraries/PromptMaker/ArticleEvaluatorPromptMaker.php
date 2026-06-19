<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

/**
 * ArticleEvaluatorPromptMaker: بناء توجيهات تقييم المقالات الأكاديمية
 * ✅ متوافق مع التعديلات الأخيرة:
 * - توحيد الفهم (مجالات الدرجات التحصيلية + دليل الكتابة) داخل التوجيهات
 * - PEEL تشخيص صريح داخل body
 * - feedback_items + text_anchor + feedback_paragraph
 * - word_counts object + formatting_compliance checklist
 * - ✅ تمرير task_prompt كاملًا عند كونه JSON لتجنب إسقاط الحقول المهمة
 */
class ArticleEvaluatorPromptMaker extends BasePromptMaker
{
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        // إرجاع System Prompt من قاعدة البيانات كما هو (يجب أن تكون النسخة الجديدة مخزنة في DB)
        return trim($basePrompt);
    }

    public function buildUserPrompt(array $payload): string
    {
        $val = fn($s): string => $this->cleanValue(is_scalar($s) ? (string)$s : '');

        // ====== مدخلات التقييم المبدئي ======
        $teacherName       = $val($payload['teacher_name'] ?? '');
        $studentNumber     = $val($payload['student_number'] ?? '');
        $teacherSupervisor = $val($payload['teacher_supervisor'] ?? '');
        $articleTitle      = $val($payload['article_title'] ?? '');
        $turnitinRaw       = $val($payload['turnitin_percentage'] ?? '');
        $referencesMatch   = $val($payload['references_match'] ?? '');

        // formatting_compliance: Checklist object (0/1) — مرره كـ JSON (لا تحوله لنص)
        $formattingComplianceObj = $payload['formatting_compliance'] ?? [];
        if (!is_array($formattingComplianceObj)) {
            $formattingComplianceObj = [];
        }
        $formattingComplianceJson = $this->jsonPretty($formattingComplianceObj);

        // word_counts: object — مرره كـ JSON (لا تحوله لنص)
        $wordCountsObj = $payload['word_counts'] ?? [];
        if (!is_array($wordCountsObj)) {
            $wordCountsObj = [];
        }
        $wordCountsJson = $this->jsonPretty($wordCountsObj);

        // total_words: نحسبه من parts فقط (لا يشمل Works Cited)
        $totalWords = $this->calculateTotalWords($payload);

        // نص المقال (مع فواصل نظامية للتقسيم وليست جزءًا من المقال)
        $articleSections = $this->buildArticleSections($payload, $wordCountsObj);

        // تعليمات task_prompt (قد تصل كنص أو JSON string أو array)
        $taskPromptSection = $this->buildTaskPromptSection($payload['task_prompt'] ?? null);

        // توجيهات عامة + هيكل JSON المطلوب
        $generalGuidance = $this->buildGeneralGuidance();
        $jsonStructure   = $this->buildJsonStructure();

        $user = <<<USR
IMPORTANT: Output MUST be JSON only with no extra text outside JSON. Start with { and end with }.

=== الجزء الأول: مدخلات التقييم المبدئي ===

بيانات المعلم/الطالب:
- اسم المعلم/الطالب: {$teacherName}
- رقم المعلم/الطالب: {$studentNumber}
- مدرس المعلمين: {$teacherSupervisor}

التقييم المبدئي:
- عنوان المقال (article_title): {$articleTitle}
- نسبة الاقتباس وفق Turnitin (turnitin_percentage): {$turnitinRaw}
- مستوى توافق المراجع (references_match): {$referencesMatch}

Checklist الالتزام بتعليمات الكتابة (formatting_compliance) — (1 = متحقق، 0 = غير متحقق):
{$formattingComplianceJson}

عدد الكلمات لكل قسم (word_counts) — أرقام مرسلة من النظام:
{$wordCountsJson}

عدد الكلمات الكلي (total_words) — محسوب من نص المقال فقط (لا يشمل Works Cited):
{$totalWords}

=== الجزء الثاني: نص المقال (فواصل التقسيم أدناه نظامية وليست جزءًا من النص الأصلي) ===

{$articleSections}

=== الجزء الثالث: تعليمات التغذية الراجعة (task_prompt) ===

{$taskPromptSection}

=== الجزء الرابع: توجيهات تنفيذ التقييم ===

{$generalGuidance}

=== الجزء الخامس: هيكل JSON المطلوب (التزم به حرفيًا) ===

{$jsonStructure}

تذكير نهائي صارم:
1) JSON فقط، بدون أي نص خارجي
2) ابدأ مباشرة بـ { ولا تضع أي نص قبله
3) انتهِ مباشرة بـ } ولا تضع أي نص بعده
4) لا تستخدم markdown code blocks (```json أو ```)
USR;

        return $user;
    }

    /**
     * حساب عدد الكلمات الكلي من parts (لا يشمل Works Cited)
     */
    private function calculateTotalWords(array $payload): int
    {
        $totalWords = 0;
        $sections = ['introduction', 'body', 'conclusion'];

        foreach ($sections as $sectionKey) {
            if (!isset($payload[$sectionKey]) || !is_array($payload[$sectionKey])) {
                continue;
            }

            $section = $payload[$sectionKey];
            if (!isset($section['parts']) || !is_array($section['parts'])) {
                continue;
            }

            foreach ($section['parts'] as $part) {
                $text = isset($part['text']) ? trim((string)$part['text']) : '';
                if ($text === '') {
                    continue;
                }

                $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
                $totalWords += is_array($words) ? count($words) : 0;
            }
        }

        return $totalWords;
    }

    /**
     * بناء أقسام المقال بصيغة واضحة (الفواصل ليست من النص الأصلي)
     */
    private function buildArticleSections(array $payload, array $wordCountsObj): string
    {
        $sectionsOut = [];

        $map = [
            'introduction' => 'المقدمة',
            'body'         => 'العرض',
            'conclusion'   => 'الخاتمة',
        ];

        foreach ($map as $key => $label) {
            $count = isset($wordCountsObj[$key]) ? (int)$wordCountsObj[$key] : 0;

            $sectionsOut[] = "【قسم: {$label}】 (عدد الكلمات المُرسل: {$count})";

            if (!isset($payload[$key]) || !is_array($payload[$key])) {
                $sectionsOut[] = "(لا توجد بيانات لهذا القسم)";
                continue;
            }

            $section = $payload[$key];
            $parts = $section['parts'] ?? [];

            if (!is_array($parts) || empty($parts)) {
                $sectionsOut[] = "(لا توجد أجزاء/فقرات ضمن هذا القسم)";
                continue;
            }

            $idx = 1;
            foreach ($parts as $part) {
                $partTitle = isset($part['title']) && trim((string)$part['title']) !== ''
                    ? trim((string)$part['title'])
                    : "جزء {$idx}";

                $partText = isset($part['text']) ? (string)$part['text'] : '';
                $partText = trim($partText);

                $sectionsOut[] = "— {$partTitle} —";
                $sectionsOut[] = $partText !== '' ? $partText : "(نص فارغ)";
                $idx++;
            }
        }

        return implode("\n\n", $sectionsOut);
    }

    /**
     * بناء قسم task_prompt (يدعم: array | JSON string | plain text)
     * ✅ تحسين: إذا كان JSON string صالح، أعد طباعة الـJSON كاملًا بدل تلخيص يسقط حقول مهمة
     */
    private function buildTaskPromptSection($taskPrompt): string
    {
        if (is_array($taskPrompt)) {
            return $this->jsonPretty($taskPrompt);
        }

        if (!is_string($taskPrompt) || trim($taskPrompt) === '') {
            return 'لا توجد تعليمات تقييم محددة (task_prompt).';
        }

        $raw = trim($taskPrompt);

        // إذا كان JSON string صالح
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $this->jsonPretty($decoded);
        }

        // نص عادي
        return $raw;
    }

    /**
     * توجيهات تنفيذ التقييم (مُحدّثة لتتوافق مع “توحيد الفهم” الجديد)
     */
    private function buildGeneralGuidance(): string
    {
        return <<<GUIDANCE
قواعد تنفيذ إلزامية (متوافقة مع توحيد الفهم):
1) اكتب general_feedback كفقرة واحدة متماسكة تعتمد على: article_title + task_prompt + formatting_compliance (0/1) + turnitin_percentage + total_words + references_match، مع الالتزام بالترتيب الإلزامي المذكور في السستم برومبت.
2) لا تحسب كلمات الأقسام بنفسك: اعتمد فقط على word_counts، وابدأ section_feedback لكل قسم بذكر عدد كلمات القسم وهل هو ضمن المدى المطلوب في task_prompt.
3) لكل قسم (section_feedback): اربط الحكم بمؤشرات “توحيد الفهم” (المعرفة، تطبيق المعرفة، التفكير الناقد، التأمل الناقد، المبادرة وحل المشكلات، فنون التدريس، العرض والتواصل) مع الاستناد لأدلة من النص.
4) داخل كل part: أعد feedback_items كمصفوفة من 3 إلى 4 عناصر. كل عنصر: item_index + text_anchor + feedback_paragraph.
5) feedback_paragraph = فقرة واحدة فقط تتضمن: (قوة محددة من النص + نقص محدد + حركة تعديل تنفيذية واحدة واضحة + سؤال تأملي واحد في النهاية؟)، ويجب تسمية مجال واحد على الأقل من “توحيد الفهم” داخل الفقرة.
6) في body تحديدًا: شخّص PEEL صراحة داخل نفس الفقرة (Point/Evidence/Explanation/Link). Explanation يُعد قويًا فقط إذا تضمّن تفسيرًا تربويًا (تطبيق المعرفة) و/أو حكمًا نقديًا على الأدب (التفكير الناقد) و/أو مراجعة ذاتية وأثرًا على الطلبة (التأمل الناقد) مع حلول سياقية عند اللزوم.
7) text_anchor بصيغة: "أول كلمتين…آخر كلمة" مع تنظيف آخر كلمة من علامات الترقيم الطرفية.
8) evaluation_summary: التزم بالخريطة:
   - "المعرفة والفهم والتطبيق" ⇐ (المعرفة + تطبيق المعرفة)
   - "المهارات الإدراكيّة" ⇐ (التفكير الناقد + التأمل الناقد)
   - "المهارات المهنيّة" ⇐ (المبادرة وحل المشكلات + فنون التدريس + توظيف معايير المعلمين)
   - "التقديم" ⇐ (العرض والتواصل + الالتزام بدليل الكتابة + MLA)
   لكل محور: 3 strengths + 3 improvement_areas بالضبط، وكل نقطة تبدأ بـ(يتضح/يظهر/ما زال/يحتاج...) وترتبط بسلوك محدد من النص.
9) المخرجات JSON فقط دون أي نص إضافي ودون Markdown.
GUIDANCE;
    }

    /**
     * هيكل JSON المطلوب (مطابق للبرومتات الأخيرة)
     * ✅ تحسين: توحيد علامة الاستفهام
     */
    private function buildJsonStructure(): string
    {
        return <<<STRUCTURE
{
  "general_feedback": "…",
  "introduction": {
    "section_feedback": "…",
    "parts": [
      {
        "part_index": 1,
        "part_title": "…",
        "part_text": "…",
        "feedback_items": [
          { "item_index": 1, "text_anchor": "…", "feedback_paragraph": "…؟" },
          { "item_index": 2, "text_anchor": "…", "feedback_paragraph": "…؟" },
          { "item_index": 3, "text_anchor": "…", "feedback_paragraph": "…؟" }
        ]
      }
    ]
  },
  "body": {
    "section_feedback": "…",
    "parts": [
      {
        "part_index": 1,
        "part_title": "…",
        "part_text": "…",
        "feedback_items": [
          { "item_index": 1, "text_anchor": "…", "feedback_paragraph": "…؟" },
          { "item_index": 2, "text_anchor": "…", "feedback_paragraph": "…？" },
          { "item_index": 3, "text_anchor": "…", "feedback_paragraph": "…؟" }
        ]
      }
    ]
  },
  "conclusion": {
    "section_feedback": "…",
    "parts": [
      {
        "part_index": 1,
        "part_title": "…",
        "part_text": "…",
        "feedback_items": [
          { "item_index": 1, "text_anchor": "…", "feedback_paragraph": "…؟" },
          { "item_index": 2, "text_anchor": "…", "feedback_paragraph": "…؟" },
          { "item_index": 3, "text_anchor": "…", "feedback_paragraph": "…؟" }
        ]
      }
    ]
  },
  "evaluation_summary": {
    "المعرفة والفهم والتطبيق": {
      "strengths": [ { "point": "…" }, { "point": "…" }, { "point": "…" } ],
      "improvement_areas": [ { "point": "…" }, { "point": "…" }, { "point": "…" } ]
    },
    "المهارات الإدراكيّة": {
      "strengths": [ { "point": "…" }, { "point": "…" }, { "point": "…" } ],
      "improvement_areas": [ { "point": "…" }, { "point": "…" }, { "point": "…" } ]
    },
    "المهارات المهنيّة": {
      "strengths": [ { "point": "…" }, { "point": "…" }, { "point": "…" } ],
      "improvement_areas": [ { "point": "…" }, { "point": "…" }, { "point": "…" } ]
    },
    "التقديم": {
      "strengths": [ { "point": "…" }, { "point": "…" }, { "point": "…" } ],
      "improvement_areas": [ { "point": "…" }, { "point": "…" }, { "point": "…" } ]
    }
  },
  "general_guidance": "…"
}
STRUCTURE;
    }

    /**
     * JSON pretty helper (Unicode-safe)
     */
    private function jsonPretty(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return $json !== false ? $json : "{}";
    }
}

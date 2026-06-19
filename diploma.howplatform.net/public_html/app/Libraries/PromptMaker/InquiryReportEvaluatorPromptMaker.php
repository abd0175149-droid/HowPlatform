<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

/**
 * InquiryReportEvaluatorPromptMaker: بناء توجيهات تقييم البحث الاستقصائي
 * تقيّم تقرير البحث الاستقصائي وفق 8 معايير (Rubric)
 * وتُنتج تقرير تغذية راجعة + جدول روبريك
 */
class InquiryReportEvaluatorPromptMaker extends BasePromptMaker
{
    /**
     * حدود الكلمات لكل قسم من أقسام البحث
     */
    private const WORD_LIMITS = [
        'introduction'           => ['min' => 350, 'max' => 450, 'label' => 'المقدمة'],
        'literature_review'      => ['min' => 700, 'max' => 900, 'label' => 'مراجعة الأدب التربوي'],
        'inquiry_overview'       => ['min' => 400, 'max' => 600, 'label' => 'نظرة عامة لعملية الاستقصاء'],
        'results'                => ['min' => 700, 'max' => 900, 'label' => 'النتائج'],
        'ethical_considerations' => ['min' => 400, 'max' => 600, 'label' => 'الاعتبارات الأخلاقية'],
        'reflective_discussion'  => ['min' => 400, 'max' => 600, 'label' => 'النقاش التأملي'],
    ];

    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        // إرجاع System Prompt من قاعدة البيانات كما هو
        return trim($basePrompt);
    }

    public function buildUserPrompt(array $payload): string
    {
        $val = fn($s): string => $this->cleanValue(is_scalar($s) ? (string)$s : '');

        // ====== بيانات الطالب ======
        $studentName       = $val($payload['student_name'] ?? '');
        $studentId         = $val($payload['student_id'] ?? '');
        $teacherSupervisor = $val($payload['teacher_supervisor'] ?? '');

        // ====== سؤال الاستقصاء ======
        $researchQuestion = trim((string)($payload['research_question'] ?? ''));

        // ====== أقسام البحث ======
        $sections = $this->buildResearchSections($payload);

        // ====== ملخص عدد الكلمات ======
        $wordCountSummary = $this->buildWordCountSummary($payload);

        // ====== المراجع والملاحق ======
        $references = trim((string)($payload['references'] ?? ''));
        $appendices = trim((string)($payload['appendices'] ?? ''));

        $user = <<<USR
IMPORTANT: Output MUST be JSON only with no extra text outside JSON. Start with { and end with }.

=== الجزء الأول: بيانات الطالب ===

- اسم الطالب (ثلاثياً): {$studentName}
- الرقم الوزاري: {$studentId}
- مدرس المعلمين: {$teacherSupervisor}

=== الجزء الثاني: ملخص عدد الكلمات لكل قسم ===

{$wordCountSummary}

=== الجزء الثالث: سؤال الاستقصاء (عنوان البحث) ===

{$researchQuestion}

=== الجزء الرابع: نصوص أقسام البحث ===

{$sections}

=== الجزء الخامس: قائمة المراجع ===

{$references}

=== الجزء السادس: الملاحق ===

{$appendices}

=== الجزء السابع: هيكل JSON المطلوب (التزم به حرفياً) ===

{$this->buildJsonStructure()}

تذكير نهائي صارم:
1) JSON فقط، بدون أي نص خارجي
2) ابدأ مباشرة بـ { ولا تضع أي نص قبله
3) انتهِ مباشرة بـ } ولا تضع أي نص بعده
4) لا تستخدم markdown code blocks
5) التزم بالتقديرات المسموحة: D, M, GP, P, F
USR;

        return $user;
    }

    /**
     * بناء نصوص أقسام البحث بتنسيق واضح
     */
    private function buildResearchSections(array $payload): string
    {
        $sectionsOut = [];

        $map = [
            'introduction'           => 'المقدمة',
            'literature_review'      => 'مراجعة الأدب التربوي السابق',
            'inquiry_overview'       => 'نظرة عامة لعملية الاستقصاء وإجراءاته',
            'results'                => 'النتائج',
            'ethical_considerations' => 'الاعتبارات الأخلاقية',
            'reflective_discussion'  => 'نقاش تأملي',
        ];

        foreach ($map as $key => $label) {
            $text = trim((string)($payload[$key] ?? ''));
            $wordCount = $this->countArabicWords($text);
            $limits = self::WORD_LIMITS[$key] ?? null;

            $limitInfo = '';
            if ($limits) {
                $status = ($wordCount >= $limits['min'] && $wordCount <= $limits['max'])
                    ? 'ضمن المدى'
                    : ($wordCount < $limits['min'] ? 'أقل من الحد الأدنى' : 'أكثر من الحد الأقصى');
                $limitInfo = " (المطلوب: {$limits['min']}-{$limits['max']} كلمة | الفعلي: {$wordCount} كلمة | {$status})";
            }

            $sectionsOut[] = "【{$label}】{$limitInfo}";
            $sectionsOut[] = $text !== '' ? $text : '(لا يوجد نص لهذا القسم)';
        }

        return implode("\n\n", $sectionsOut);
    }

    /**
     * بناء ملخص عدد الكلمات مع مقارنة بالحدود
     */
    private function buildWordCountSummary(array $payload): string
    {
        $lines = [];
        $totalWords = 0;

        foreach (self::WORD_LIMITS as $key => $limits) {
            $text = trim((string)($payload[$key] ?? ''));
            $count = $this->countArabicWords($text);
            $totalWords += $count;

            $status = '—';
            if ($text !== '') {
                $status = ($count >= $limits['min'] && $count <= $limits['max'])
                    ? '✓ ضمن المدى'
                    : ($count < $limits['min'] ? '✗ أقل من الحد الأدنى' : '✗ أكثر من الحد الأقصى');
            }

            $lines[] = "- {$limits['label']}: {$count} كلمة (المطلوب: {$limits['min']}-{$limits['max']}) — {$status}";
        }

        $lines[] = "";
        $lines[] = "المجموع الكلي: {$totalWords} كلمة (المطلوب: 3,500 كلمة تقريباً)";

        return implode("\n", $lines);
    }

    /**
     * حساب عدد الكلمات (يدعم العربية والإنجليزية)
     */
    private function countArabicWords(string $text): int
    {
        if (trim($text) === '') {
            return 0;
        }
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        return is_array($words) ? count($words) : 0;
    }

    /**
     * هيكل JSON المطلوب من الذكاء الاصطناعي
     */
    private function buildJsonStructure(): string
    {
        return <<<STRUCTURE
{
  "student_info": {
    "student_name": "اسم الطالب المُستخرج من النص أو المُدخل",
    "student_id": "الرقم الوزاري",
    "course_name": "مساق أساليب تدريس المبحث",
    "task_title": "تقرير البحث الاستقصائي"
  },
  "overall_evaluation": {
    "grade_symbol": "M",
    "grade_word": "ناجح بتفوّق"
  },
  "rubric_grades": [
    {
      "criterion_id": 1,
      "criterion_name": "المعرفة والفهم - (أ) المعرفة والفهم",
      "grade_symbol": "D",
      "grade_word": "ناجح بتميّز",
      "justification": "مبرر مختصر يوضح لماذا تم منح هذا التقدير بناءً على أدلة من النص — جملة أو جملتان"
    },
    {
      "criterion_id": 2,
      "criterion_name": "المعرفة والفهم - (ب) تطبيق المعرفة والفهم",
      "grade_symbol": "M",
      "grade_word": "ناجح بتفوّق",
      "justification": "مبرر مختصر يوضح لماذا تم منح هذا التقدير"
    },
    {
      "criterion_id": 3,
      "criterion_name": "المهارات المعرفية - (أ) التفكير الناقد",
      "grade_symbol": "GP",
      "grade_word": "ناجح بتقدير جيّد",
      "justification": "مبرر مختصر يوضح لماذا تم منح هذا التقدير"
    },
    {
      "criterion_id": 4,
      "criterion_name": "المهارات المعرفية - (ب) التأمل الناقد للممارسة المهنية",
      "grade_symbol": "M",
      "grade_word": "ناجح بتفوّق",
      "justification": "مبرر مختصر يوضح لماذا تم منح هذا التقدير"
    },
    {
      "criterion_id": 5,
      "criterion_name": "المهارات المهنية وأصول التدريس - (أ) المبادرة وحل المشكلات",
      "grade_symbol": "P",
      "grade_word": "ناجح",
      "justification": "مبرر مختصر يوضح لماذا تم منح هذا التقدير"
    },
    {
      "criterion_id": 6,
      "criterion_name": "المهارات المهنية وأصول التدريس - (ب) فنون التدريس",
      "grade_symbol": "M",
      "grade_word": "ناجح بتفوّق",
      "justification": "مبرر مختصر يوضح لماذا تم منح هذا التقدير"
    },
    {
      "criterion_id": 7,
      "criterion_name": "المهارات المهنية وأصول التدريس - (ج) الاستقصاء المبني على الأدلة",
      "grade_symbol": "GP",
      "grade_word": "ناجح بتقدير جيّد",
      "justification": "مبرر مختصر يوضح لماذا تم منح هذا التقدير"
    },
    {
      "criterion_id": 8,
      "criterion_name": "مهارات العرض - العرض والتواصل",
      "grade_symbol": "M",
      "grade_word": "ناجح بتفوّق",
      "justification": "مبرر مختصر يوضح لماذا تم منح هذا التقدير"
    }
  ],
  "feedback_report": {
    "strengths": [
      "وصف نقطة قوة مستمد من سياق وصف مستويات الأداء المرتفعة التي تحققت، مع أدلة من النص — بلغة محايدة جنسياً",
      "وصف نقطة قوة أخرى مستمدة من وصف مستوى الأداء المحقق — بلغة محايدة جنسياً"
    ],
    "areas_for_development": [
      "وصف مجال تطوير مستمد من سياق وصف مستويات الأداء المنخفضة، مع توصيات — بلغة محايدة جنسياً",
      "وصف مجال تطوير آخر مستمد من وصف مستوى الأداء — بلغة محايدة جنسياً"
    ],
    "general_notes": "فقرة شاملة تلخص الأداء العام وتربط بين نقاط القوة والتطوير — بلغة محايدة جنسياً"
  }
}

ملاحظات حول التقديرات المسموحة:
- D = ناجح بتميّز (4, أ+)
- M = ناجح بتفوّق (3.75, أ-)
- GP = ناجح بتقدير جيّد (3.5, ب+)
- P = ناجح (3, ب)
- F = راسب (1.75, ج-)

ملاحظة مهمة حول منح التقدير الكلي:
- ليس من الضروري تلبية الوصف في جميع المعايير بالكامل
- قد يكون هناك درجة من التعويض بين المجالات الأربعة
- مثال: إذا كان الطالب M في المهارات المعرفية والمهنية وP في المعرفة والعرض → التقدير الكلي GP

ملاحظات حول نقاط القوة والتطوير:
- يوجد 8 معايير للتقييم إجمالاً. يجب عليك استخراج وكتابة 8 نقاط بالضبط (نقطة واحدة مخصصة لكل معيار).
- لتصنيف المعيار: يتم مقارنة علامة كل معيار مع "التقدير الكلي" لتحديد ما إذا كان يمثل "نقطة قوة" في هذا البحث أم "مجال تطوير".
- اكتب جملة واحدة لكل معيار (إما في قسم نقاط القوة أو التطوير). مجموع عدد عناصر نقاط القوة ونقاط التطوير معاً يجب أن يساوي 8 دائماً، لكن أعدادهم النسبية تختلف حسب جودة المقال وعلامته.
- لا تذكر اسم المعيار في نقاط القوة أو التطوير
- استمد صياغة كل نقطة من سياق وصف مستويات الأداء في الروبريك للمعيار المعني
- مثلاً: بدلاً من "المعرفة والفهم: أظهر مستوى جيد جدا..." اكتب "تم إظهار مستوى جيد جدا من المعرفة والفهم..."
- استخدم لغة محايدة جنسياً: استخدم "تم إظهار/تم تقديم/تمت ملاحظة" بدلاً من "أظهر/قدم/أظهرت"
- اجعل النقاط وصفية مباشرة دون تصنيفها تحت اسم معيار
STRUCTURE;
    }
}

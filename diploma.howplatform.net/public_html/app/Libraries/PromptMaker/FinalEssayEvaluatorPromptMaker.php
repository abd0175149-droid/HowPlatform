<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

/**
 * FinalEssayEvaluatorPromptMaker: بناء توجيهات تقييم المقالة التأملية الختامية
 * (مساق الخبرة المدرسية) — منطق مطابق لمقيّم البحث الاستقصائي:
 * تقييم وفق 8 معايير (Rubric) وإنتاج تقرير تغذية راجعة + جدول روبريك.
 *
 * المدخل: essay_text (نصّ المقالة المستخرَج خادمياً من ملف .docx)
 *         + student_name + student_id (الرقم الوزاري) + teacher_supervisor.
 * المخرج: JSON بهيكل rubric_grades / overall_evaluation / feedback_report.
 */
class FinalEssayEvaluatorPromptMaker extends BasePromptMaker
{
    /**
     * البنية المرجعية لأجزاء المقالة التأملية وعدد كلماتها (وفق الدليل الإرشادي)
     */
    private const ESSAY_STRUCTURE = [
        ['label' => 'المقدمة (طرح الحجة/الدور)',                    'words' => 20],
        ['label' => 'وصف الموقف ووصف المشاعر',                      'words' => 200],
        ['label' => 'تقييم وتحليل الموقف (مع الربط بالأدب التربوي)', 'words' => 500],
        ['label' => 'الاستنتاجات',                                   'words' => 200],
        ['label' => 'خطة العمل',                                     'words' => 100],
        ['label' => 'أثر مساقات الدبلوم على تطوير الممارسة',         'words' => 350],
        ['label' => 'خطة عمل لتطوير الممارسات وتخطّي التحدّيات',     'words' => 150],
        ['label' => 'الخاتمة (ربط الحجة بالرحلة المهنية)',           'words' => 30],
    ];

    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        // إرجاع System Prompt من قاعدة البيانات كما هو (مرآة InquiryReportEvaluatorPromptMaker)
        return trim($basePrompt);
    }

    public function buildUserPrompt(array $payload): string
    {
        $val = fn($s): string => $this->cleanValue(is_scalar($s) ? (string)$s : '');

        // ====== بيانات المعلم الطالب ======
        $studentName       = $val($payload['student_name'] ?? '');
        $studentId         = $val($payload['student_id'] ?? '');
        $teacherSupervisor = $val($payload['teacher_supervisor'] ?? '');

        // essay_text يبقى خاماً (verbatim) — لا تنظيف، للحفاظ على فقرات المقالة كما هي
        $essayText = (string)($payload['essay_text'] ?? '');

        $totalWords    = $this->countArabicWords($essayText);
        $structureInfo = $this->buildStructureReference();
        $expectedTotal = array_sum(array_column(self::ESSAY_STRUCTURE, 'words'));

        $user = <<<USR
IMPORTANT: Output MUST be JSON only with no extra text outside JSON. Start with { and end with }.

=== الجزء الأول: بيانات المعلم الطالب ===

- اسم المعلم الطالب (ثلاثياً): {$studentName}
- الرقم الوزاري: {$studentId}
- مدرس المعلمين: {$teacherSupervisor}

=== الجزء الثاني: البنية المرجعية للمقالة وعدد الكلمات ===

المقالة التأملية الختامية نصّ مترابط يُكتب بفقرات متكاملة دون عناوين فرعية، وتُبنى وفق مراحل نموذج جيبس (الوصف → المشاعر → التقييم → التحليل → الاستنتاج → خطة العمل). أجزاؤها المرجعية وحدودها:

{$structureInfo}

- المجموع الكلي المتوقع: ~{$expectedTotal} كلمة
- عدد كلمات المقالة المُدخلة فعلياً: {$totalWords} كلمة

بما أن المقالة بلا عناوين فرعية، حدِّد أنت أجزاءها من سياق النص وقيّم مدى التزامها بالبنية وحدود الكلمات ضمن معيار العرض والتواصل.

=== الجزء الثالث: نصّ المقالة التأملية الختامية (كاملاً) ===

=== ESSAY START ===
{$essayText}
=== ESSAY END ===

=== الجزء الرابع: هيكل JSON المطلوب (التزم به حرفياً) ===

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
     * بناء جدول البنية المرجعية للمقالة
     */
    private function buildStructureReference(): string
    {
        $lines = [];
        foreach (self::ESSAY_STRUCTURE as $i => $part) {
            $n = $i + 1;
            $lines[] = "{$n}) {$part['label']} — ~{$part['words']} كلمة";
        }
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
     * (أسماء المعايير مطابقة حرفياً لجدول الروبريك المعتمد — لا تغيّرها)
     */
    private function buildJsonStructure(): string
    {
        return <<<STRUCTURE
{
  "student_info": {
    "student_name": "اسم المعلم الطالب المُستخرج من النص أو المُدخل",
    "student_id": "الرقم الوزاري",
    "course_name": "الخبرة المدرسية",
    "task_title": "المقالة التأملية الختامية"
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
      "justification": "مبرر مختصر يوضح لماذا تم منح هذا التقدير بناءً على أدلة من نص المقالة — جملة أو جملتان"
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

⛔ استبعاد معيار الاستقصاء: معيار «(ج) الاستقصاء المبني على الأدلة» (criterion_id = 7) مُستبعَد تماماً من المقالة التأملية الختامية (لا يوجد فيها استقصاء بحثي). لا تُدرجه إطلاقاً في مصفوفة rubric_grades، ولا تحتسبه في التقدير الكلي. المعرّفات المستخدمة في rubric_grades هي 1، 2، 3، 4، 5، 6، 8 فقط (سبعة معايير، بدون 7).

ملاحظات حول نقاط القوة والتطوير:
- يوجد 7 معايير للتقييم إجمالاً (بعد استبعاد معيار الاستقصاء). يجب عليك استخراج وكتابة 7 نقاط بالضبط (نقطة واحدة مخصصة لكل معيار مُقيَّم).
- لتصنيف المعيار: يتم مقارنة علامة كل معيار مع "التقدير الكلي" لتحديد ما إذا كان يمثل "نقطة قوة" في هذه المقالة أم "مجال تطوير".
- اكتب جملة واحدة لكل معيار (إما في قسم نقاط القوة أو التطوير). مجموع عدد عناصر نقاط القوة ونقاط التطوير معاً يجب أن يساوي 7 دائماً، لكن أعدادهم النسبية تختلف حسب جودة المقالة وعلامتها.
- لا تذكر اسم المعيار في نقاط القوة أو التطوير
- استمد صياغة كل نقطة من سياق وصف مستويات الأداء في الروبريك للمعيار المعني
- مثلاً: بدلاً من "المعرفة والفهم: أظهر مستوى جيد جدا..." اكتب "تم إظهار مستوى جيد جدا من المعرفة والفهم..."
- استخدم لغة محايدة جنسياً: استخدم "تم إظهار/تم تقديم/تمت ملاحظة" بدلاً من "أظهر/قدم/أظهرت"
- اجعل النقاط وصفية مباشرة دون تصنيفها تحت اسم معيار
STRUCTURE;
    }
}

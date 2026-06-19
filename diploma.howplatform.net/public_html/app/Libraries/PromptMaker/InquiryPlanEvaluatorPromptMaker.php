<?php

declare(strict_types=1);

namespace App\Libraries\PromptMaker;

/**
 * InquiryPlanEvaluatorPromptMaker
 * تقييم مخطط البحث الاستقصائي (Action Research Plan) الذي أعده الطالب-المعلم
 * تُقيَّم الأقسام الستة مع الملاحق وتُعاد تغذية راجعة تفصيلية بصيغة JSON.
 */
class InquiryPlanEvaluatorPromptMaker extends BasePromptMaker
{
    // ─── حدود الكلمات المعتمدة لكل قسم ───────────────────────────────────────
    private const WORD_LIMITS = [
        'section_1' => ['min' => 150, 'max' => 200, 'label' => 'القسم الأول: المقدمة'],
        'section_2' => ['min' => 300, 'max' => 400, 'label' => 'القسم الثاني: مراجعة الأدب التربوي'],
        'section_3' => ['min' => 200, 'max' => 280, 'label' => 'القسم الثالث: نظرة عامة على عملية الاستقصاء'],
        'section_4' => ['min' => 150, 'max' => 200, 'label' => 'القسم الرابع: الاعتبارات الأخلاقية'],
        'section_5' => ['min' => 200, 'max' => 280, 'label' => 'القسم الخامس: النتائج'],
        'section_6' => ['min' => 180, 'max' => 250, 'label' => 'القسم السادس: النقاش التأملي'],
    ];

    // ─── المراجع الإلزامية الأربعة ──────────────────────────────────────────
    private const REQUIRED_REFS = [
        'قراءة الاستقصاء كنهج وممارسة (Reading Inquiry as an Approach and Practice)',
        'دليل المعلم – صورة الدرس (Teacher\'s Guide – Lesson Portrait)',
        'الإطار العام للمنهاج الخاص بمادة المعلم',
        'معايير المعلمين — أكاديمية الملكة رانيا لتدريب المعلمين (2024)',
    ];

    /**
     * System Prompt — يُستخدم ما في قاعدة البيانات إن وُجد، وإلا الافتراضي أدناه.
     */
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        if (trim($basePrompt) !== '') {
            return trim($basePrompt);
        }

        return <<<SYS
أنت مقيّم أكاديمي خبير في برنامج الدبلوم التربوي، ومتخصص في تقييم مخططات البحث الاستقصائي (Action Research Plans).
مهمتك تقديم تغذية راجعة تفصيلية ومهنية لمخطط البحث الذي أعده الطالب-المعلم، وفق معايير البرنامج المعتمدة.

قواعد عامة:
- اكتب بأسلوب أكاديمي رصين، ودقيق، ومباشر.
- أعد JSON صالحاً فقط؛ لا تكتب أي شيء خارج JSON.
- لا تستخدم markdown code fences.
- كن صريحاً في نقاط الضعف مع تقديم توجيهات تنفيذية واضحة.
SYS;
    }

    /**
     * User Prompt — يبني التوجيه الكامل لتقييم المخطط.
     */
    public function buildUserPrompt(array $payload): string
    {
        $val  = fn(?string $s): string => $this->cleanValue($s);
        $join = fn($arr): string       => $this->arrayToText($arr);

        // ── بيانات الطالب والبحث ──
        $teacherName      = $val($payload['teacher_name']      ?? '');
        $studentNumber    = $val($payload['student_number']    ?? '');
        $teacherSupervisor= $val($payload['teacher_supervisor'] ?? '');
        $researchQuestion = $val($payload['research_question'] ?? '');
        $subjectName      = $val($payload['subject_name']      ?? '');

        // ── معلومات الالتزام (مُدخلة يدوياً) ──
        $titleCompliance   = (int)($payload['title_compliance']   ?? 0);  // 1=نعم 0=لا
        $refCountCheck     = (int)($payload['ref_count_check']    ?? 0);  // عدد المراجع المُدخَل
        $tableRowsCount    = (int)($payload['table_rows_count']   ?? 0);  // عدد صفوف الجدول

        // ── نصوص الأقسام ──
        $section1 = $val($payload['section_1'] ?? '');
        $section2 = $val($payload['section_2'] ?? '');
        $section3 = $val($payload['section_3'] ?? '');
        $section4 = $val($payload['section_4'] ?? '');
        $section5 = $val($payload['section_5'] ?? '');
        $section6 = $val($payload['section_6'] ?? '');

        // ── عدد الكلمات (محسوب من الواجهة) ──
        $wc = $payload['word_counts'] ?? [];
        $wc1 = (int)($wc['section_1'] ?? 0);
        $wc2 = (int)($wc['section_2'] ?? 0);
        $wc3 = (int)($wc['section_3'] ?? 0);
        $wc4 = (int)($wc['section_4'] ?? 0);
        $wc5 = (int)($wc['section_5'] ?? 0);
        $wc6 = (int)($wc['section_6'] ?? 0);

        // ── المراجع ──
        $referencesRaw = $payload['references'] ?? [];
        $referencesText = $this->buildReferencesList($referencesRaw);

        // ── الملاحق ──
        $appendicesRaw  = $payload['appendices'] ?? [];
        $appendicesText = $this->buildAppendicesList($appendicesRaw);

        // ── جدول الحصص ──
        $tableRaw  = $payload['section_3_table'] ?? [];
        $tableText = $this->buildTableText($tableRaw);

        // ── بناء ملاحظة الالتزام بالعنوان ──
        $titleComplianceLabel = $titleCompliance === 1 ? 'نعم (المخطط ملتزم بسؤال البحث كما هو)' : 'لا (يوجد انحراف عن سؤال البحث المُدخَل)';

        // ── بناء ملخص عدد الكلمات مع المقارنة بالحدود المطلوبة ──
        $wordCountSummary = $this->buildWordCountSummary([
            'section_1' => $wc1,
            'section_2' => $wc2,
            'section_3' => $wc3,
            'section_4' => $wc4,
            'section_5' => $wc5,
            'section_6' => $wc6,
        ]);

        // ── قائمة المراجع الإلزامية للتحقق ──
        $requiredRefsText = implode("\n", array_map(
            fn($r, $i) => ($i + 1) . '. ' . $r,
            self::REQUIRED_REFS,
            array_keys(self::REQUIRED_REFS)
        ));

        // ── هيكل JSON المطلوب ──
        $jsonStructure = $this->buildJsonStructure();

        $prompt = <<<USR
IMPORTANT: Output MUST be valid JSON only — no text outside JSON, no markdown fences. Start with { and end with }.

=== الجزء الأول: بيانات الطالب-المعلم والبحث ===

- اسم الطالب-المعلم: {$teacherName}
- الرقم الأكاديمي: {$studentNumber}
- المشرف / مدرس المعلمين: {$teacherSupervisor}
- المادة الدراسية: {$subjectName}
- سؤال البحث الاستقصائي المُدخَل: {$researchQuestion}

=== الجزء الثاني: بيانات الالتزام المُدخلة من المُقيِّم ===

- الالتزام بسؤال البحث في جميع أقسام المخطط: {$titleComplianceLabel}
- عدد المراجع الكلي في المخطط: {$refCountCheck} مرجع
- عدد صفوف الجدول (الحصص الدراسية): {$tableRowsCount} صف

=== الجزء الثالث: عدد الكلمات الفعلي لكل قسم (محسوب آلياً من النص) ===

{$wordCountSummary}

=== الجزء الرابع: نصوص أقسام المخطط ===

【القسم الأول: المقدمة】(عدد الكلمات: {$wc1} | المطلوب: 150-200 كلمة)
{$section1}

【القسم الثاني: مراجعة الأدب التربوي】(عدد الكلمات: {$wc2} | المطلوب: 300-400 كلمة)
{$section2}

【القسم الثالث: نظرة عامة على عملية الاستقصاء】(عدد الكلمات: {$wc3} | المطلوب: 200-280 كلمة)
{$section3}

【جدول الحصص الدراسية】
{$tableText}

【القسم الرابع: الاعتبارات الأخلاقية】(عدد الكلمات: {$wc4} | المطلوب: 150-200 كلمة)
{$section4}

【القسم الخامس: النتائج】(عدد الكلمات: {$wc5} | المطلوب: 200-280 كلمة)
{$section5}

【القسم السادس: النقاش التأملي】(عدد الكلمات: {$wc6} | المطلوب: 180-250 كلمة)
{$section6}

=== الجزء الخامس: قائمة المراجع ===

{$referencesText}

=== الجزء السادس: الملاحق ===

{$appendicesText}

=== الجزء السابع: قائمة المراجع الإلزامية الأربعة (للتحقق من وجودها) ===

يجب التحقق من وجود المراجع التالية بالضرورة في قائمة مراجع الطالب:
{$requiredRefsText}

=== الجزء الثامن: قواعد التقييم الإلزامية ===

التزم بهذه القواعد عند التقييم:

1. **سؤال البحث**: إذا ظهر أن المخطط يعيد صياغة سؤال البحث أو يحيد عنه في أي قسم — اذكر ذلك صراحة في feedback القسم المعني.

2. **عدد الكلمات**: لكل قسم، ابدأ الـ section_feedback بجملة واضحة تذكر: عدد الكلمات الفعلي، والمدى المطلوب، والحكم (ضمن المدى / أقل من الحد / أعلى من الحد). لا تستنتج عدد الكلمات بنفسك — اعتمد على word_counts المُرسلة فقط.

3. **المراجع الإلزامية**: تحقق من وجود المراجع الأربعة الإلزامية في قائمة المراجع المُرسلة. إذا غاب أي منها — اذكره بالاسم في references_feedback.

4. **المراجع الزمنية**: لا يجوز الاستشهاد بمراجع قبل عام 2018. إذا وجدت مرجعاً سابقاً لذلك — أشر إليه.

5. **الجدول (القسم الثالث)**: يجب أن يحتوي على 2-3 صفوف فعلية (حصص دراسية). يُمنع منعاً باتاً ذكر: الاختبار القبلي/البعدي، الاستبانات، المقابلات الشخصية. إذا ورد أي منها — اذكره صراحة.

6. **الملاحق**: يجب أن تشمل: دليل المعلم/الكتاب المدرسي + خطط الدروس على الأقل.

7. **الأسلوب الأكاديمي**: تحقق من أن الكتابة بأسلوب أكاديمي رصين، وصوت المعلم الكاتب للمخطط (المضارع/المستقبل)، وليس بأسلوب وصفي خارجي.

8. **مستوى التغذية الراجعة (هام جداً)**:
   - بالنسبة لـ (السؤال، القسم الأول: المقدمة، القسم الثاني: الأدب التربوي، القسم الرابع: الاعتبارات الأخلاقية): قدّم تغذية راجعة "بشكل عام".
   - بالنسبة لـ (القسم الثالث: نظرة عامة لعملية الاستقصاء وإجراءاته، القسم الخامس: النتائج، القسم السادس: النقاش التأملي): قدّم تغذية راجعة "داعمة ومركزة".

9. **التقييم الإجمالي**: أعطِ تقييماً إجمالياً من أربع درجات: ممتاز / جيد جداً / جيد / بحاجة إلى مراجعة جوهرية.

=== الجزء التاسع: هيكل JSON المطلوب (التزم به حرفياً) ===

{$jsonStructure}

تذكير نهائي صارم:
1) JSON فقط، ابدأ مباشرة بـ { ولا تضع أي نص قبله
2) انتهِ مباشرة بـ } ولا تضع أي نص بعده
3) لا تستخدم markdown code blocks (```json أو ```)
USR;

        return $prompt;
    }

    // ─── دوال مساعدة خاصة ────────────────────────────────────────────────────

    /**
     * بناء ملخص عدد الكلمات مع مقارنة بالحدود المطلوبة
     */
    private function buildWordCountSummary(array $counts): string
    {
        $lines = [];
        foreach (self::WORD_LIMITS as $key => $limit) {
            $actual = $counts[$key] ?? 0;
            $min    = $limit['min'];
            $max    = $limit['max'];
            $label  = $limit['label'];

            if ($actual < $min) {
                $status = "أقل من الحد الأدنى (ناقص " . ($min - $actual) . " كلمة)";
            } elseif ($actual > $max) {
                $status = "أعلى من الحد الأقصى (زائد " . ($actual - $max) . " كلمة)";
            } else {
                $status = "ضمن المدى المطلوب ✓";
            }

            $lines[] = "- {$label}: {$actual} كلمة (المطلوب: {$min}–{$max}) — {$status}";
        }
        return implode("\n", $lines);
    }

    /**
     * بناء قائمة المراجع
     */
    private function buildReferencesList($refs): string
    {
        if (!is_array($refs) || empty($refs)) {
            return '(لم تُدخَل مراجع)';
        }
        $lines = [];
        foreach ($refs as $i => $ref) {
            $text = is_array($ref) ? ($ref['text'] ?? json_encode($ref, JSON_UNESCAPED_UNICODE)) : (string)$ref;
            $lines[] = ($i + 1) . '. ' . trim($text);
        }
        return implode("\n", $lines);
    }

    /**
     * بناء نص الملاحق
     */
    private function buildAppendicesList($appendices): string
    {
        if (!is_array($appendices) || empty($appendices)) {
            return '(لم تُدخَل ملاحق)';
        }
        $lines = [];
        foreach ($appendices as $i => $app) {
            $text = is_array($app) ? ($app['text'] ?? json_encode($app, JSON_UNESCAPED_UNICODE)) : (string)$app;
            $lines[] = 'الملحق ' . ($i + 1) . ': ' . trim($text);
        }
        return implode("\n", $lines);
    }

    /**
     * بناء نص جدول الحصص
     */
    private function buildTableText($table): string
    {
        if (!is_array($table) || empty($table)) {
            return '(لم يُدخَل جدول الحصص)';
        }
        $lines = [];
        foreach ($table as $i => $row) {
            $lesson   = $row['lesson_title']        ?? '—';
            $outcomes = $row['learning_outcomes']    ?? '—';
            $strategy = $row['teaching_strategy']    ?? '—';
            $tools    = $row['data_collection_tools']?? '—';
            $assess   = $row['assessment_strategy']  ?? '—';
            $resources= $row['learning_resources']   ?? '—';

            $lines[] = "الحصة " . ($i + 1) . ":";
            $lines[] = "  - العنوان: {$lesson}";
            $lines[] = "  - النتاجات: {$outcomes}";
            $lines[] = "  - الاستراتيجية: {$strategy}";
            $lines[] = "  - أدوات جمع البيانات: {$tools}";
            $lines[] = "  - التقويم: {$assess}";
            $lines[] = "  - المصادر: {$resources}";
        }
        return implode("\n", $lines);
    }

    /**
     * هيكل JSON المطلوب من AI
     */
    private function buildJsonStructure(): string
    {
        return <<<'JSON'
{
  "general_feedback": "تغذية راجعة شاملة على المخطط ككل — فقرة واحدة متماسكة تذكر: مدى الالتزام بسؤال البحث، الانسجام بين الأقسام، جودة الأسلوب الأكاديمي، والأثر العام للمخطط.",

  "section_1_feedback": {
    "word_count_judgment": "عدد الكلمات الفعلي X — [ضمن المدى / أقل / أعلى]",
    "strengths": ["نقطة قوة 1", "نقطة قوة 2"],
    "improvements": ["نقطة تحسين 1 مع توجيه تنفيذي", "نقطة تحسين 2"],
    "detailed_feedback": "تغذية راجعة بشكل عام للمقدمة تشمل: وضوح سبب اختيار الموضوع، الربط بمساقَي الدبلوم، وصف السياق المدرسي والصف"
  },

  "section_2_feedback": {
    "word_count_judgment": "عدد الكلمات الفعلي X — [ضمن المدى / أقل / أعلى]",
    "strengths": ["نقطة قوة 1", "نقطة قوة 2"],
    "improvements": ["نقطة تحسين 1 مع توجيه تنفيذي"],
    "detailed_feedback": "تغذية راجعة بشكل عام لمراجعة الأدب: تقييم عمق المراجعة، الربط بسؤال البحث، التسلسل المنطقي للفقرتين"
  },

  "references_feedback": {
    "total_references": 0,
    "required_refs_present": {
      "inquiry_approach": true,
      "teacher_guide_portrait": true,
      "curriculum_framework": true,
      "teacher_standards_qrta": true
    },
    "missing_required_refs": ["اذكر المراجع الإلزامية الغائبة إن وجدت"],
    "outdated_refs": ["مراجع قبل 2018 إن وجدت"],
    "format_issues": ["مشاكل في صياغة MLA إن وجدت"],
    "overall_ref_judgment": "ملاحظة شاملة على جودة المراجع"
  },

  "section_3_feedback": {
    "word_count_judgment": "عدد الكلمات الفعلي X — [ضمن المدى / أقل / أعلى]",
    "table_rows_count": 0,
    "table_judgment": "تقييم جدول الحصص: عدد الصفوف، جودة الاستراتيجيات، أدوات التقويم الواقعي، غياب المحظورات",
    "forbidden_methods_found": ["اذكر أي اختبار قبلي/بعدي أو استبانة أو مقابلة إن وجدت"],
    "strengths": ["نقطة قوة 1"],
    "improvements": ["نقطة تحسين 1"],
    "detailed_feedback": "تغذية راجعة داعمة ومركزة للنص السردي لدورة الاستقصاء وإجراءاته"
  },

  "section_4_feedback": {
    "word_count_judgment": "عدد الكلمات الفعلي X — [ضمن المدى / أقل / أعلى]",
    "strengths": ["نقطة قوة 1"],
    "improvements": ["نقطة تحسين 1"],
    "detailed_feedback": "تغذية راجعة بشكل عام حول شمولية الاعتبارات الأخلاقية (السرية، الموافقات، الموضوعية، الفروق الفردية)"
  },

  "section_5_feedback": {
    "word_count_judgment": "عدد الكلمات الفعلي X — [ضمن المدى / أقل / أعلى]",
    "strengths": ["نقطة قوة 1"],
    "improvements": ["نقطة تحسين 1"],
    "detailed_feedback": "تغذية راجعة داعمة ومركزة حول وضوح خطة تقييم التعلم (النتائج)، مبررات اختيار الأدوات، منهجية تحليل البيانات، الربط بالأدب التربوي"
  },

  "section_6_feedback": {
    "word_count_judgment": "عدد الكلمات الفعلي X — [ضمن المدى / أقل / أعلى]",
    "strengths": ["نقطة قوة 1"],
    "improvements": ["نقطة تحسين 1"],
    "detailed_feedback": "تغذية راجعة داعمة ومركزة حول عمق التأمل في الفقرتين: إدراك أهمية الاستقصاء كموقف ثم كممارسة لتطوير الممارسة التعليمية"
  },

  "appendices_feedback": {
    "has_teacher_guide": true,
    "has_lesson_plans": true,
    "additional_appendices": ["الملاحق الإضافية المذكورة"],
    "overall_judgment": "ملاحظة على اكتمال الملاحق"
  },

  "evaluation_summary": {
    "overall_score": "ممتاز / جيد جداً / جيد / بحاجة إلى مراجعة جوهرية",
    "strengths": [
      "نقطة قوة إجمالية 1",
      "نقطة قوة إجمالية 2",
      "نقطة قوة إجمالية 3"
    ],
    "improvement_areas": [
      "أولوية تحسين 1 — توجيه تنفيذي واضح",
      "أولوية تحسين 2 — توجيه تنفيذي واضح",
      "أولوية تحسين 3 — توجيه تنفيذي واضح"
    ]
  },

  "general_guidance": "رسالة ختامية مشجعة وبنّاءة للطالب-المعلم تتضمن أبرز ما يحتاج تعزيزه قبل التقديم النهائي، بأسلوب محفّز وأكاديمي."
}
JSON;
    }
}

<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

/**
 * LessonPlanPromptMaker: بناء توجيهات خطة الحصة
 */
class LessonPlanPromptMaker extends BasePromptMaker
{
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        if (trim($basePrompt) === '') {
            return <<<SYS
تصرف كمعلّم خبير في تصميم خطط الدروس وفق التعلم البنائي، التعلم الدامج، وتمايز التعليم،
مع توظيف روتينيات التفكير (Project Zero – Harvard).
أعد خطة حصة واحدة قابلة للتنفيذ، وأرجِع المخرجات بصيغة JSON صالحة فقط دون أي شرح خارج JSON.
إن وُجد نقص في المدخلات اتّبع بروتوكول نقص المدخلات المشار إليه في رسالة المستخدم.
SYS;
        }
        return trim($basePrompt);
    }

    public function buildUserPrompt(array $payload): string
    {
        $val = fn(?string $s): string => $this->cleanValue($s);
        $join = fn($arr): string => $this->arrayToText($arr);

        $teacherName = $val($payload['teacher_name'] ?? '');
        $schoolName = $val($payload['school_name'] ?? '');
        $gradeLevel = $val($payload['grade_level'] ?? '');
        $subjectName = $val($payload['subject_name'] ?? '');
        $unitName = $val($payload['unit_name'] ?? '');
        $lessonTitle = $val($payload['lesson_title'] ?? '');
        $topicSummary = $val($payload['topic_summary'] ?? '');
        $outcomesCount = (int) ($payload['outcomes_count'] ?? 1);
        $classSize = (int) ($payload['class_size'] ?? 0);
        $duration = (int) ($payload['duration_minutes'] ?? 40);
        $focusAxis = $val($payload['focus_axis'] ?? '');
        $constraints = $val($payload['constraints'] ?? '');
        $learningAssets = $val($payload['learning_assets'] ?? '');
        $roughGoals = $val($payload['rough_goals_note'] ?? '');
        $senPresent = (bool) ($payload['sen_present'] ?? false);
        $senSupport = $val($payload['sen_support'] ?? '');
        $senOther = $val($payload['sen_support_other'] ?? '');
        $teachStrats = $join($payload['teaching_strategies'] ?? []);
        $assessMethods = $join($payload['assessment_methods'] ?? []);

        $senLine = $senPresent
            ? ('نعم — نوع الدعم: ' . ($senSupport !== '' ? $senSupport : 'غير محدد') .
               ($senOther !== '' ? (' (' . $senOther . ')') : ''))
            : 'لا';

        $user = <<<USR
IMPORTANT: Output MUST be JSON only with no extra text outside JSON.

الهدف: إعداد خطة حصة واحدة متكاملة قابلة للتنفيذ لحصة مدتها {$duration} دقيقة، تراعي الفروق الفردية وأنماط التعلم ودمج ذوي الاحتياجات، مع الالتزام بمحور التركيز: {$focusAxis}.

بيانات الدرس المدمجة:
- المعلم: {$teacherName}
- المدرسة: {$schoolName}
- الصف/المرحلة: {$gradeLevel}  ← (school_class)
- المبحث: {$subjectName} ← (general_subject)
- الوحدة: {$unitName}   ← (general_unit)
- عنوان الدرس: {$lessonTitle} ← (general_title)
- ملخص الموضوع: {$topicSummary}
- عدد النتاجات المطلوبة: {$outcomesCount}
- حجم الشعبة: {$classSize}
- القيود: {$constraints}
- موارد التعلّم المتاحة: {$learningAssets}
- استراتيجيات التدريس المفضّلة: {$teachStrats}
- أساليب/أدوات التقويم المفضّلة: {$assessMethods}
- يوجد طلبة بحاجة إلى دعم: {$senLine}
- ملاحظات أولية على الأهداف (إن وُجدت): {$roughGoals}

المهام خطوة بخطوة:
1) تحقّق من اكتمال المدخلات أعلاه. إن وُجد نقص فلا تبدأ الخطة وأعد JSON وفق "بروتوكول نقص المدخلات".
   حرّر الحقول المشتقة كما يلي:
   - school_class من "{$gradeLevel}"
   - general_subject من "{$subjectName}"
   - general_unit من "{$unitName}"
   - general_title من "{$lessonTitle}".
2) ابحث حصراً في المنهاج الأردني بعد 2022 (وثائق وزارة التربية الأردنية/مراجع موثوقة)
   لاستخراج: prior_knowledge، التكامل الرأسي (صفوف سابقة/لاحقة)، والتكامل الأفقي (مواد أخرى في الصف نفسه).
   اذكر مصادر الكترونية استخدمت في الدرس مثل تطبيقات كويزيز word wall وغيرها بما يتناسب وطبيعة الدرس وليس أمر الزامي تعود لطبيعة الدرس والاستراتيجية المستخدمة e_application.
   - إن تعذّر الوصول للويب، اكتب "مصادر عامة للمنهاج الأردني 2022" مع توصيف مختصر.
3) صُغ نواتج تعلم SMART بعدد {$outcomesCount} بأفعال مفردة قابلة للقياس، مع مستوى بلوم وزمن تحقق داخل الحصة؛
   تجنّب النتاج المركّب ولا تذكر مستوى المحك داخل نص النتاج.
4) خطّط مراحل الحصة:
   - تهيئة محفِّزة (2–4 دقائق)
   - تمهيد (≤5 دقائق)
   - عرض صلب: لكل ناتج استراتيجية تدريس مناسبة + أداة/استراتيجية تقويم مناسبة + أدوار المعلم/الطالب + زمن تحقيق
   - غلق تقويمي (3–5 دقائق)
5) احسب الزمن: مجموع دقائق المراحل + مجموع قيم o_dur ≈ {$duration}. عالج الفروق البسيطة بتعديل دقيق.
6) اختم بتأمل مهني ≤200 كلمة يتضمن نقاط قوة/تحسين وخطة إجراء مختصرة (على افتراض تنفيذ الحصة).

قيود الإخراج والأسلوب:
- لغة عربية فصحى مهنية؛ تجنّب الأفعال الغامضة.
- التزم بالمحور "{$focusAxis}"، ووفّر تمايزًا ودمجًا عمليين بما يتوافق مع حالة الدعم أعلاه.
- التزم بالقيود "{$constraints}" وبالاستراتيجيات/الأدوات المفضلة المذكورة.
- المخرجات JSON فقط بالمفاتيح التالية (بدون قيم فارغة):
  teacher_name,school_name, school_class, general_unit, general_subject, general_title,
  integration_vertical, integration_horizontal, prior_knowledge,
  knowledge_cognitive, knowledge_skills, knowledge_values,
  learning_sources, e_application,
  warmup_teacher, warmup_student, w_min,
  intro_teacher, intro_student, i_min,
  closure_teacher, closure_student, c_min,
  o_statement, o_assess_strategy, o_assess_tool, o_teach_strategy, o_teacher, o_student, o_dur,
  reflection, differentiation_content, differentiation_process, differentiation_product, digital_safety, w_teach_strategy, i_teach_strategy, c_assess_strategy, c_assess_tool.
- بنية حقول النواتج: استخدم مصفوفات بطول {$outcomesCount} للمفاتيح:
  o_statement, o_assess_strategy, o_assess_tool, o_teach_strategy, o_teacher, o_student, o_dur.
- استخدم تسميات مختصرة مألوفة صفياً لاستراتيجيات التدريس وأدوات/استراتيجيات التقويم (مثل: "فكر–زاوج–شارك"، "بطاقة خروج"، "التقييم الذاتي"...).

بروتوكول نقص المدخلات:
- إن كانت أي من المفاتيح الجوهرية مفقودة (teacher_name, grade_level, subject_name, lesson_title, outcomes_count, duration_minutes)، فأعد JSON حصراً بالهيئة:
  {
    "need_more_info": ["اسم_الحقل_1", "اسم_الحقل_2", ...],
    "message": "يرجى تزويدي بالقيم الناقصة لاستكمال الخطة."
  }
  ولا تُضمّن أي حقول أخرى من الخطة.

تذكير نهائي صارم: أعد JSON صالحاً فقط دون أي نص خارجي أو كود fence خارج JSON.
USR;

        return $user;
    }
}


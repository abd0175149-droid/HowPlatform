<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

/**
 * StakeholderIntegrationPromptMaker: بناء توجيهات أداة "دمج أصحاب العلاقة"
 */
class StakeholderIntegrationPromptMaker extends BasePromptMaker
{
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        if (trim($basePrompt) === '') {
            return <<<SYS
أنت خبير تربوي متخصص في تخطيط الدروس وتفعيل أدوار أصحاب العلاقة (مثل أولياء الأمور، الزملاء المعلمين، أو الزوار الخارجيين) في البيئة المدرسية والتعليمية.
مهمتك هي مساعدة المعلم في تصميم نشاط تعليمي يدمج صاحب العلاقة بشكل فعّال، مع توضيح الهدف والإجراءات المتبعة في التخطيط والتنفيذ، وتقديم تأمل ذاتي حول ذلك.

يجب أن تقوم بتوليد المحتوى باللغة العربية الفصحى بشكل احترافي ومنظم.
المخرجات يجب أن تكون بصيغة JSON فقط لتتوافق مع نموذج إخراج محدد سلفاً. لا تقم بإضافة أي نصوص أو مقدمات خارج هيكل الـ JSON.

إرشادات هامة:
- تأكد من فهم المدخلات بشكل صحيح واستخدمها لبناء النشاط المقترح.
- إذا كانت هناك معايير معلمين (criteria) مرسلة، يجب أن تقوم باستخراج أكواد المعايير (أرقامها) فقط (مثال: 1.1.4) وإضافتها في المصفوفة `teacher_criteria`. لا تقم بكتابة نص المعيار.
SYS;
        }
        return trim($basePrompt);
    }

    public function buildUserPrompt(array $payload): string
    {
        $val = fn(?string $s): string => $this->cleanValue($s);

        $gradeLevel      = $val($payload['grade_level'] ?? '');
        $subjectArea     = $val($payload['subject_area'] ?? '');
        $learningOutcome = $val($payload['learning_outcome'] ?? '');
        $stakeholderRole = $val($payload['stakeholder_role'] ?? '');

        // محاولة قراءة JSON من task_prompt لاستخراج المعايير (Criteria)
        $taskPrompt = $val($payload['task_prompt'] ?? '');
        $criteria = is_array($payload['criteria'] ?? null) ? $payload['criteria'] : [];
        
        if ($taskPrompt) {
            try {
                $parsed = json_decode($taskPrompt, true);
                if (is_array($parsed) && isset($parsed['type']) && $parsed['type'] === 'stakeholder_integration') {
                    if (isset($parsed['criteria']) && is_array($parsed['criteria'])) {
                        $criteria = $parsed['criteria'];
                    }
                }
            } catch (\Exception $e) {
                // ليس JSON
            }
        }

        // تحويل المعايير إلى نص
        $criteriaText = '';
        if (!empty($criteria)) {
            $criteriaLines = [];
            foreach ($criteria as $criterion) {
                if (is_string($criterion)) {
                    $criteriaLines[] = '• ' . $criterion;
                } elseif (is_array($criterion) && isset($criterion['code']) && isset($criterion['description'])) {
                    $criteriaLines[] = '• ' . $criterion['code'] . ': ' . $criterion['description'];
                }
            }
            $criteriaText = implode("\n", $criteriaLines);
        }

        $user = <<<USR
المهمة: إعداد مخطط نشاط تعليمي يدمج صاحب علاقة محدد.

المدخلات:
- الصف الدراسي: {$gradeLevel}
- المبحث الدراسي: {$subjectArea}
- نتاج التعلم المستهدف: {$learningOutcome}
- صفة دور صاحب العلاقة المدعو: {$stakeholderRole}

معايير المعلمين المقترحة (الرجاء اختيار ما يناسب منها للنشاط وإدراج أرقامها/أكوادها فقط):
{$criteriaText}

بناءً على المعطيات السابقة، قم باقتراح تفاصيل النشاط وتعبئة هيكل JSON التالي بشكل دقيق. 
لا تقم بترك أي حقل فارغ، واكتب محتوى ذو قيمة وعمق تربوي:

{
  "activity_name": "اسم النشاط المقترح",
  "stakeholder_role": "{$stakeholderRole}",
  "activity_type": "تحديد نوع النشاط (صفي / لا صفي)",
  "grade_level": "{$gradeLevel}",
  "lesson": "عنوان مقترح للدرس (بناءً على النتاج والمبحث)",
  "learning_outcome": "{$learningOutcome}",
  "subject_area": "{$subjectArea}",
  "activity_goal": "وصف دقيق للهدف من النشاط وكيفية الاستفادة من صاحب العلاقة",
  "planning_procedures": "وصف مفصل للإجراءات المتبعة في التخطيط لتفعيل دور صاحب العلاقة (نص متصل أو على شكل نقاط)",
  "implementation_procedures": "وصف مفصل للإجراءات المتبعة في التنفيذ لدمج صاحب العلاقة (نص متصل أو على شكل نقاط)",
  "self_reflection": "تأمل ذاتي متوقع للمعلم بعد تنفيذ هذا النشاط",
  "teacher_criteria": ["1.1.4", "2.3.1"]
}

ملاحظات هامة:
1. ربط النشاط بالمعايير (Teacher Criteria): أدرج فقط أرقام المعايير (الكود) بدون أي تفاصيل أو شرح. مثال: إذا كان المعيار "1.1.4 - فهم طبيعة التعلم"، أدرج فقط "1.1.4".
2. الحقل `teacher_criteria` يجب أن يكون مصفوفة (Array) تحتوي فقط على أكواد المعايير (أرقامها).
3. تجنب استخدام الـ Markdown خارج الـ JSON. إجابتك يجب أن تبدأ بـ { وتنتهي بـ } وتكون صالحة تقنياً (Valid JSON).
USR;

        return $user;
    }
}

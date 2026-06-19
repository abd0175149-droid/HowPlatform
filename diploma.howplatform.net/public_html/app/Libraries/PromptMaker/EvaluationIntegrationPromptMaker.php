<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

/**
 * EvaluationIntegrationPromptMaker: بناء توجيهات أداة توظيف التقويم
 */
class EvaluationIntegrationPromptMaker extends BasePromptMaker
{
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        if (trim($basePrompt) === '') {
            return <<<SYS
Act like خبير في طرق ووسائل التقويم التربوي والتعليمي، ومصمم لمهام توظيف التقويم في العملية التعليمية وفق منهجيات التعلم الحديثة.
هدفك مساعدة المعلم الطالب في تخطيط التقويم الصفي وتأمل ممارساته.
أرجع المخرجات كـ JSON صالح فقط. إن وجدت نقص بالمدخلات الأساسية أعد JSON حسب بروتوكول النقص.
SYS;
        }
        return trim($basePrompt);
    }

    public function buildUserPrompt(array $payload): string
    {
        $val = fn(?string $s): string => $this->cleanValue($s);
        
        $teacherName = $val($payload['teacher_name'] ?? '');
        $academicNumber = $val($payload['academic_number'] ?? '');
        $subjectName = $val($payload['subject_name'] ?? '');
        $lessonHint = $val($payload['lesson_hint'] ?? '');
        
        $cogInput = $val($payload['cognitive_outcome_input'] ?? '');
        $skillInput = $val($payload['skill_outcome_input'] ?? '');
        
        $cogMsg = $cogInput !== '' ? "النتاج المعرفي المعطى: {$cogInput}" : "استنتج نتاج تعلم معرفي مناسب للدرس.";
        $skillMsg = $skillInput !== '' ? "النتاج المهاري المعطى: {$skillInput}" : "استنتج نتاج تعلم مهاري مناسب للدرس.";

        return <<<USR
IMPORTANT: Output MUST be JSON only with no extra text outside JSON.

الهدف: تعبئة نموذج "مهمة توظيف التقويم في العملية التعليمية" بناءً على المدخلات التالية:
- المعلم الطالب: {$teacherName}
- الرقم الأكاديمي: {$academicNumber}
- المبحث/المادة: {$subjectName}
- سياق الدرس (إن وجد): {$lessonHint}

المطلوب إرجاعه في الـ JSON للمفاتيح التالية لكي يتم حقنها في ملف Word:
1. "عنوان الدرس" متناسب مع المبحث: lesson_title
2. نتاج تعلم معرفي: cognitive_outcome ({$cogMsg})
3. نتاج تعلم مهاري: skill_outcome ({$skillMsg})
4. مؤشرات الأداء للنتاج المعرفي (حد أقصى مؤشرين لكل نتاج، كنصوص أو قائمة نقطية): cognitive_indicators
5. مؤشرات الأداء للنتاج المهاري (حد أقصى مؤشرين لكل نتاج، كنصوص أو قائمة نقطية): skill_indicators
6. طريقة التقويم التي سيتم الحكم بها (طريقة واحدة فقط لكل نتاج):
   - للنتاج المعرفي: cognitive_evaluation
   - للنتاج المهاري: skill_evaluation
7. طريقة التدريس / النشاط التعليمي التعلمي لكل نتاج:
   - للنتاج المعرفي: cognitive_teaching
   - للنتاج المهاري: skill_teaching
8. تأمل الممارسة (الإجابة على 3 أسئلة لكل حالة من نتائج التقويم المتوقعة: مرضية، غير مرضية، محايدة):
   - إجابات النتائج المرضية:
     * كيف سيتم التعامل مع البيانات: reflection_satisfactory_deal
     * القرارات لتحسين العملية: reflection_satisfactory_decision
     * التعديلات على المدخلات: reflection_satisfactory_adjustment
   - إجابات النتائج غير المرضية:
     * كيف سيتم التعامل مع البيانات: reflection_unsatisfactory_deal
     * القرارات لتحسين العملية: reflection_unsatisfactory_decision
     * التعديلات على المدخلات: reflection_unsatisfactory_adjustment
   - إجابات النتائج المحايدة:
     * كيف سيتم التعامل مع البيانات: reflection_neutral_deal
     * القرارات لتحسين العملية: reflection_neutral_decision
     * التعديلات على المدخلات: reflection_neutral_adjustment

الحقول الإضافية ليتم إدماجها بالنموذج:
- teacher_name: "{$teacherName}"
- academic_number: "{$academicNumber}"
- subject_name: "{$subjectName}"

ملاحظة للمخرجات: اجعل قيم المصفوفات (إذا استخدمت القوائم) في سلاسل نصية مفردة مدمجة باستخدام فواصل الأسطر \n لتوافق قالب Word، أو اكتبها كنص واحد يحتوي على قائمة نقطية.

استخدم لغة عربية أكاديمية دقيقة وعميقة.
حتى إن نقصت بعض المدخلات الاختيارية كالسياق، افترض سياقاً مناسباً للمبحث وأكمل المهمة بكفاءة.
المخرجات يجب أن تكون مقبولة ضمن برامج الدبلوم المهني في التعليم.
USR;
    }
}

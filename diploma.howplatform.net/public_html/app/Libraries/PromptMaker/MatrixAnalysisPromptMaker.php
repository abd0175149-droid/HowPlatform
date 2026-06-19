<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

/**
 * MatrixAnalysisPromptMaker: بناء توجيهات مصفوفة تحليل المحتوى
 */
class MatrixAnalysisPromptMaker extends BasePromptMaker
{
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        if (trim($basePrompt) === '') {
            return <<<SYS
تصرف كمختص تربوي خبير في تحليل المحتوى الدراسي وفق النموذج الأردني للمناهج.
مهمتك: إنتاج مصفوفة تحليل محتوى تربوية وعميقة بناءً على المعلومات المقدمة.
أرجِع المخرجات بصيغة JSON صالحة فقط دون أي شرح أو نص خارج JSON.
التزم بالمناهج الأردنية الحالية (بعد 2022) حصراً.
SYS;
        }
        return trim($basePrompt);
    }

    public function buildUserPrompt(array $payload): string
    {
        $val = fn(?string $s): string => $this->cleanValue($s);

        $teacherName = $val($payload['teacher_name'] ?? '');
        $stageLevel = $val($payload['stage_level'] ?? '');
        $subjectName = $val($payload['subject_name'] ?? '');
        $unitTitle = $val($payload['unit_title'] ?? '');
        $curriculumPages = $val($payload['curriculum_pages'] ?? '');
        $lessonsCount = (string) max(1, min(50, (int) ($payload['lessons_count'] ?? 4)));

        $user = <<<USR
IMPORTANT: Output MUST be valid JSON only with no extra text before or after the JSON. No markdown code fences, no explanations, just pure JSON.

الهدف: تحليل محتوى الوحدة الدراسية وإنتاج مصفوفة تحليل محتوى تربوية متكاملة وفق المناهج الأردنية.

المدخلات:
- اسم المعلم الطالب: {$teacherName}
- المرحلة التي يدرسها: {$stageLevel}
- المبحث: {$subjectName}
- عنوان الوحدة / الدرس: {$unitTitle}
- عدد الحصص: {$lessonsCount}
- الصفحات من الكتاب المدرسي: {$curriculumPages}

المهام المطلوبة:

1) التحقق من اكتمال المدخلات:
   - إذا كانت أي من الحقول التالية فارغة (teacher_name, stage_level, subject_name, unit_title, curriculum_pages, lessons_count)، 
     فأرجِع JSON حصراً بالهيئة:
     {
       "need_more_info": ["اسم_الحقل_1", "اسم_الحقل_2", ...],
       "message": "يرجى تزويدي بالقيم الناقصة لإتمام التحليل."
     }
     ولا تُضمّن أي حقول أخرى.

2) البحث والتحليل:
   - استخدم معرفتك بالمناهج الأردنية الحالية (بعد 2022) كمصدر أساسي.
   - راجع الصفحات المحددة ({$curriculumPages}) من الكتاب المدرسي للمبحث ({$subjectName}) في المرحلة ({$stageLevel}).
   - اعتمد على الوثائق الرسمية لوزارة التربية والتعليم الأردنية.
   - استخدم معرفتك العامة بالمناهج الأردنية دون الحاجة للبحث في الإنترنت.

3) إنتاج تحليل تفصيلي لكل عمود من أعمدة المصفوفة:

   أ) المحتوى (matrix_col_content):
      - وصف شامل للمحتوى الدراسي في الوحدة.
      - العناوين الرئيسية والفرعية.
      - المواضيع الأساسية المغطاة.
      - الصياغة: تربوية، عملية، واضحة.

   ب) المفاهيم والمصطلحات (matrix_col_concepts):
      - استخراج المفاهيم الأساسية في الوحدة.
      - المصطلحات العلمية المهمة.
      - التعريفات المطلوبة للطلبة.
      - العلاقات بين المفاهيم.
      - الصياغة: علمية دقيقة وفق المصطلحات المستخدمة في المنهاج الأردني.

   ج) المبادئ والقوانين (matrix_col_principles):
      - المبادئ الأساسية المطبقة في الوحدة.
      - القوانين والنظريات (إن وُجدت).
      - القواعد والأنماط.
      - العلاقات السببية.
      - الصياغة: دقيقة وعلمية.

   د) الأنشطة والمهارات (matrix_col_activities):
      - الأنشطة التعليمية المناسبة للوحدة.
      - المهارات التي يجب أن يكتسبها الطلبة.
      - المهارات الحركية والمعرفية.
      - الأنشطة العملية والتجريبية.
      - الصياغة: عملية وقابلة للتنفيذ.

   هـ) القيم والاتجاهات (matrix_col_values):
      - القيم التربوية المستهدفة في الوحدة.
      - الاتجاهات الإيجابية المطلوب تعزيزها.
      - السلوكيات المرغوبة.
      - الصفات الشخصية المطورة.
      - الصياغة: تربوية هادفة.

   و) الرسومات والجداول والأشكال (matrix_col_visuals):
      - وصف للأشكال والرسوم التوضيحية في الوحدة.
      - الجداول والبيانات.
      - الصور والمخططات.
      - الرسوم البيانية والخرائط (إن وُجدت).
      - الصياغة: وصفية واضحة.

   ز) بنود أخرى (matrix_col_other_tasks):
      - أي مهام إضافية يقوم بها الطلبة.
      - التطبيقات العملية.
      - المشاريع أو البحوث المصغرة.
      - الواجبات المنزلية المقترحة.
      - الأنشطة الإثرائية أو العلاجية.
      - الصياغة: عملية وتفصيلية.

4) متطلبات الجودة:
   - المحتوى يجب أن يكون تربوياً وعميقاً، وليس وصفاً عاماً.
   - التزم بالمفاهيم والمصطلحات المستخدمة في المناهج الأردنية.
   - استخدم أمثلة واقعية من الكتب المدرسية الأردنية.
   - الصياغة: لغة عربية فصحى واضحة ومناسبة للمستوى التعليمي.

5) هيكل JSON المطلوب (يجب أن يتطابق 100% مع هذا الهيكل):

{
  "teacher_name": "{$teacherName}",
  "stage_level": "{$stageLevel}",
  "subject_name": "{$subjectName}",
  "unit_title": "{$unitTitle}",
  "curriculum_pages": "{$curriculumPages}",
  "lessons_count": "{$lessonsCount}",
  "matrix_col_content": "النص الكامل للمحتوى...",
  "matrix_col_concepts": "النص الكامل للمفاهيم والمصطلحات...",
  "matrix_col_principles": "النص الكامل للمبادئ والقوانين...",
  "matrix_col_activities": "النص الكامل للأنشطة والمهارات...",
  "matrix_col_values": "النص الكامل للقيم والاتجاهات...",
  "matrix_col_visuals": "النص الكامل للرسومات والجداول...",
  "matrix_col_other_tasks": "النص الكامل للبنود الأخرى..."
}

ملاحظات نهائية صارمة:
- أرجِع JSON صالحاً فقط دون أي نص قبل أو بعد JSON.
- لا تستخدم markdown code fences (```json أو ```).
- لا تكتب أي شرح أو تعليق.
- تأكد من أن جميع القيم النصية موجودة وغير فارغة (ما عدا في حالة نقص المدخلات).
- استخدم ترميز UTF-8 وتأكد من صحة الأحرف العربية.
- JSON يجب أن يكون مطابقاً 100% للبليس هولدر في قالب Word.
USR;

        return $user;
    }
}

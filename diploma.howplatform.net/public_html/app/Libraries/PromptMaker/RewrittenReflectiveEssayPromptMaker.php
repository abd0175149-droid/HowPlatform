<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

class RewrittenReflectiveEssayPromptMaker extends BasePromptMaker
{
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        return $basePrompt;
    }

    public function buildUserPrompt(array $payload): string
    {
        $val = fn(?string $s): string => $this->cleanValue($s);

        $teacherSpecialty = $val($payload['teacher_specialty'] ?? '');
        $gradeLevel       = $val($payload['grade_level'] ?? '');
        $teacherNameId    = $val($payload['teacher_name_id'] ?? '');
        $originalEssay    = $val($payload['original_essay'] ?? '');
        $feedback         = $val($payload['feedback'] ?? '');

        return <<<USR
IMPORTANT: Output MUST be JSON only with no extra text outside JSON.
Return exactly ONE valid JSON object with the following keys:
- "article_title": عنوان مناسب للمقال التأملي.
- "content": المقال الكامل المعاد صياغته كنص واحد (بفقرات مفصولة بأسطر جديدة دون عناوين فرعية).

البيانات المدخلة:
- تخصص المعلم: {$teacherSpecialty}
- الصف الذي يدرسه: {$gradeLevel}
- التغذية الراجعة من الموجه/المقيم: {$feedback}
(ملاحظة هامة جداً: يجب ألا يتضمن المقال التأملي النهائي اسم المعلم أو رقمه الوزاري "{$teacherNameId}" إطلاقاً.)

نص المقال الحالي (المطلوب تحسينه وإعادة صياغته):
```
{$originalEssay}
```

تعليمات إلزامية ومشددة لبناء محتوى "content":
1. [شرط صارم جداً]: يجب ألا يقل طول المقال بأي حال من الأحوال عن 1350 كلمة ولا يزيد عن 1650 كلمة. المخرجات القصيرة (مثل 1100 كلمة) مرفوضة تماماً. للوصول لهذا الطول، توسع جداً في السرد، التحليل، وتوضيح كيفية دمج التغذية الراجعة بالتفصيل.
2. عالج جميع نقاط الضعف والملاحظات المذكورة في "التغذية الراجعة" وادمجها بسلاسة في النسخة الجديدة.
3. حافظ على أسلوب السرد التأملي الأكاديمي، ولا تستخدم العناوين الفرعية أبداً، بل اكتب المقال كفقرات مترابطة.
4. قسّم الأفكار في السرد إلى جزأين أساسيين مدمجين:
   أ. رحلتي في الدبلوم.
   ب. أثر الدبلوم والتطوير المهني.
5. تأكد من إبراز التخصص ({$teacherSpecialty}) والمرحلة ({$gradeLevel}) في سياق المقال.
6. لا تقم أبداً بكتابة أو ذكر اسم المعلم أو رقمه الوزاري داخل النص.
7. في فقرة التحليل والاستنتاج، وظّف مرجعين من المراجع العربية الحديثة (ما بعد عام 2015) مع توثيقها بنظام (MLA).
8. وظّف معايير المعلمين المرفقة أدناه، مع ذكر رقم المعيار في أي مكان مناسب حسب السياق ضمن المقال.
9. أضف قائمة المراجع (باللغة العربية) في نهاية المقال، مع تضمين المرجع التالي إلزامياً: "أكاديمية الملكة رانيا لتدريب المعلمين. معايير المعلمين. 2024." بالإضافة إلى المرجعين العربيين الحديثين.
10. لا تُدرج أي JSON أو شيفرة داخل الحقل "content".

إليك معايير المعلمين للتوظيف:
{$this->getTeacherStandards()}

أخرج النتيجة ككائن JSON فقط وفق المفاتيح المطلوبة.
USR;
    }

}

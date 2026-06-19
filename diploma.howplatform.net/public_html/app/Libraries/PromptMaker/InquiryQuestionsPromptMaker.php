<?php

declare(strict_types=1);

namespace App\Libraries\PromptMaker;

class InquiryQuestionsPromptMaker extends BasePromptMaker
{
    /**
     * بناء الـ System Prompt
     */
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        if (trim($basePrompt) !== '') {
            return trim($basePrompt);
        }

        return "أنت خبير في البحث الإجرائي التربوي (Action Research) ومتخصص في مساعدة المعلمين على صياغة أسئلة استقصائية (إجرائية) دقيقة وفق أحدث المعايير التربوية.";
    }

    /**
     * بناء الـ User Prompt لأداة توليد أسئلة الاستقصاء
     *
     * @param array $payload البيانات المرسلة من المستخدم
     * @return string
     */
    public function buildUserPrompt(array $payload): string
    {
        $subject       = $payload['subject_name']     ?? '';
        $lesson        = $payload['lesson_title']     ?? '';
        $grade         = $payload['grade_level']      ?? '';
        $studentGender = $payload['student_gender']   ?? '';
        $challenge     = $payload['observed_challenge'] ?? '';

        // ترجمة جنس الطلبة
        $genderLabel = match ($studentGender) {
            'male'   => 'طلاب (ذكور)',
            'female' => 'طالبات (إناث)',
            'mixed'  => 'طلبة (مختلط)',
            default  => 'طلبة',
        };

        $prompt  = "أنا معلم وأريد مساعدتك في صياغة **3 أسئلة استقصائية (إجرائية)** لدراسة ممارستي التعليمية داخل الغرفة الصفية.\n\n";

        $prompt .= "## بياناتي الأساسية\n";
        $prompt .= "- **المادة الدراسية:** {$subject}\n";
        $prompt .= "- **الدرس:** {$lesson}\n";
        $prompt .= "- **الصف الدراسي:** {$grade}\n";
        $prompt .= "- **الفئة المستهدفة:** {$genderLabel}\n";

        if (!empty($challenge)) {
            $prompt .= "- **التحدي أو المشكلة الملاحظة:** {$challenge}\n";
        }

        $prompt .= "\n## تعليمات الصياغة (مهم جداً)\n";
        $prompt .= "1. اختر **استراتيجية تدريسية واحدة مناسبة** من قائمة مثل: التعلم التعاوني، الاستقصاء العلمي، الخرائط الذهنية، التعلم بالمشروعات، لعب الأدوار، التعلم المقلوب، الحوار السقراطي، العصف الذهني، التعلم بالمحطات، التعلم التنافسي، أو غيرها.\n";
        $prompt .= "2. اختر **أداة تدريسية أو أداة تقييم مناسبة** تتوافق مع الاستراتيجية.\n";
        $prompt .= "3. صُغ **3 أسئلة استقصائية** وفق البنية التالية:\n";
        $prompt .= "   `[أداة الاستفهام الاستكشافية] + [الاستراتيجية/التدخل] + [الأثر/العملية المراد تحسينها] + [الفئة المستهدفة]`\n";
        $prompt .= "   - **يجب أن تبدأ كل سؤال حصراً** بإحدى الصيغتين التاليتين فقط: (ما دور) أو (ما فاعلية)\n";
        $prompt .= "   - لا يُسمح باستخدام أي صيغة افتتاحية أخرى.\n";
        $prompt .= "4. يجب أن تكون الأسئلة مفتوحة النهاية ولا تجاب بـ (نعم/لا).\n";
        $prompt .= "5. يجب أن تركز على **الكيفية والعملية** وليس فقط النتيجة النهائية.\n";
        $prompt .= "6. يجب أن تكون قابلة للملاحظة والتأمل داخل الغرفة الصفية.\n\n";

        $prompt .= "## مخرجات مطلوبة (JSON فقط)\n";
        $prompt .= "أعد الإجابة حصراً بصيغة JSON صالحة دون أي نص خارجي:\n";
        $prompt .= "```json\n";
        $prompt .= "{\n";
        $prompt .= "  \"chosen_strategy\": \"اسم الاستراتيجية التي اخترتها\",\n";
        $prompt .= "  \"chosen_tool\": \"اسم الأداة التي اخترتها\",\n";
        $prompt .= "  \"strategy_justification\": \"جملة قصيرة تبرر اختيارك للاستراتيجية والأداة بناءً على المادة والصف\",\n";
        $prompt .= "  \"questions\": [\n";
        $prompt .= "    \"السؤال الاستقصائي الأول\",\n";
        $prompt .= "    \"السؤال الاستقصائي الثاني\",\n";
        $prompt .= "    \"السؤال الاستقصائي الثالث\"\n";
        $prompt .= "  ]\n";
        $prompt .= "}\n";
        $prompt .= "```\n";

        return $prompt;
    }
}

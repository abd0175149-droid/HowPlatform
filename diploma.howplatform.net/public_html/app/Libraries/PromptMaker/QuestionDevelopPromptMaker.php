<?php

namespace App\Libraries\PromptMaker;

class QuestionDevelopPromptMaker extends BasePromptMaker
{
    /**
     * بناء الـ Prompt العام
     */
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        if (trim($basePrompt) === '') {
            return "أنت خبير تربوي متخصص في بناء وتقييم وتطوير أسئلة الاختبارات وفق المعايير التربوية الحديثة.";
        }
        return trim($basePrompt);
    }

    /**
     * بناء الـ Prompt الخاص بأداة تطوير السؤال
     *
     * @param array $payload البيانات المرسلة من المستخدم
     * @return string
     */
    public function buildUserPrompt(array $payload): string
    {
        $originalQuestion = $payload['original_question'] ?? '';
        
        // بناء الـ Prompt
        $prompt = "أنت خبير تربوي متخصص في بناء وتقييم وتطوير أسئلة الاختبارات وفق المعايير التربوية الحديثة.\n\n";
        $prompt .= "المطلوب منك تحليل وتطوير السؤال التالي بناءً على المعايير الخمسة أدناه:\n";
        $prompt .= "السؤال الأصلي: \"{$originalQuestion}\"\n\n";

        $prompt .= "المعايير التي يجب أخذها بعين الاعتبار:\n";
        $prompt .= "1. مستوى التّعقيد (Complexity level).\n";
        $prompt .= "2. الارتباط بالكفايات الموصوفة في بداية الاختبار (Connection to specific competencies).\n";
        $prompt .= "3. الارتباط بواقع حياة الطّلبة (Connection to real life).\n";
        $prompt .= "4. الصّياغة من حيث الوضوح والتّحديد (Phrasing clarity and specificity).\n";
        $prompt .= "5. عدم التحيز بغض النظر عن الجنس أو الثقافة (Non-bias).\n\n";

        $prompt .= "الرجاء إعادة بناء السؤال بشكل احترافي، محققاً أكبر قدر ممكن من المعايير أعلاه، وتحديد الكفايات التي يقيسها السؤال.\n\n";

        $prompt .= "يجب أن يكون الرد حصراً بصيغة JSON متوافقة مع الهيكل التالي، بدون أي نص إضافي أو شروحات:\n";
        $prompt .= "```json\n";
        $prompt .= "{\n";
        $prompt .= "  \"competencies\": \"الكفايات التي يقيسها السؤال (نص مختصر)\",\n";
        $prompt .= "  \"original_question\": \"السؤال الأصلي كما تم إدخاله\",\n";
        $prompt .= "  \"modified_question\": \"صيغة السؤال الجديدة المطوّرة\",\n";
        $prompt .= "  \"criteria_used\": {\n";
        $prompt .= "    \"complexity\": true/false,\n";
        $prompt .= "    \"competency\": true/false,\n";
        $prompt .= "    \"real_life\": true/false,\n";
        $prompt .= "    \"phrasing\": true/false,\n";
        $prompt .= "    \"bias\": true/false\n";
        $prompt .= "  }\n";
        $prompt .= "}\n";
        $prompt .= "```\n\n";
        
        $prompt .= "تنبيه: في كائن `criteria_used` ضع `true` إذا تم استخدام هذا المعيار في التطوير، أو `false` إذا لم يكن له تأثير مباشر.\n";

        return $prompt;
    }
}

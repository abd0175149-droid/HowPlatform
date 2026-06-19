<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

/**
 * InquiryDataToolsPromptMaker: بناء توجيهات أداة تصميم أدوات جمع البيانات للبحث الاستقصائي
 */
class InquiryDataToolsPromptMaker extends BasePromptMaker
{
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        return $basePrompt;
    }

    public function buildUserPrompt(array $payload): string
    {
        $topic = $this->cleanValue($payload['topic'] ?? '');
        $toolType = $this->cleanValue($payload['tool_type'] ?? '');
        $goal = $this->cleanValue($payload['goal'] ?? '');
        $audience = $this->cleanValue($payload['audience'] ?? '');

        $lines = [];
        $lines[] = "الرجاء تصميم أداة جمع البيانات بناءً على المدخلات التالية:";
        if ($topic) $lines[] = "- موضوع الدرس/البحث: {$topic}";
        if ($toolType) $lines[] = "- نوع الأداة المطلوبة: {$toolType}";
        if ($goal) $lines[] = "- ماذا نريد أن نقيس؟ (الهدف): {$goal}";
        if ($audience) $lines[] = "- الفئة المستهدفة: {$audience}";

        return implode("\n", $lines);
    }
}

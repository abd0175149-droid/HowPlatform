<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

/**
 * InquiryAcademicWriterPromptMaker: بناء توجيهات أداة المساعد في الكتابة الأكاديمية
 */
class InquiryAcademicWriterPromptMaker extends BasePromptMaker
{
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        return $basePrompt;
    }

    public function buildUserPrompt(array $payload): string
    {
        $topic = $this->cleanValue($payload['topic'] ?? '');
        $mainQuestion = $this->cleanValue($payload['main_question'] ?? '');
        $sections = $this->arrayToText($payload['sections'] ?? []);
        $keywords = $this->cleanValue($payload['keywords'] ?? '');

        $lines = [];
        $lines[] = "الرجاء صياغة مسودة أكاديمية للأجزاء المحددة من البحث الاستقصائي بناءً على المدخلات التالية:";
        if ($topic) $lines[] = "- عنوان البحث أو موضوعه: {$topic}";
        if ($mainQuestion) $lines[] = "- السؤال الرئيسي للبحث أو المشكلة: {$mainQuestion}";
        if ($sections) $lines[] = "- الأجزاء المطلوب كتابتها: {$sections}";
        if ($keywords) $lines[] = "- كلمات مفتاحية ونقاط للتركيز عليها: {$keywords}";

        return implode("\n", $lines);
    }
}

<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

/**
 * InquiryTemplatesPromptMaker: بناء توجيهات مولد النماذج الإدارية للبحث الاستقصائي
 */
class InquiryTemplatesPromptMaker extends BasePromptMaker
{
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        return $basePrompt;
    }

    public function buildUserPrompt(array $payload): string
    {
        $templateType = $this->cleanValue($payload['template_type'] ?? '');
        $researchSummary = $this->cleanValue($payload['research_summary'] ?? '');
        $teacherName = $this->cleanValue($payload['teacher_name'] ?? '');
        $schoolGrade = $this->cleanValue($payload['school_grade'] ?? '');
        $activities = $this->arrayToText($payload['activities'] ?? []);

        $lines = [];
        $lines[] = "الرجاء صياغة خطاب رسمي/نموذج إداري بناءً على المدخلات التالية:";
        if ($templateType) $lines[] = "- نوع النموذج المطلوب: {$templateType}";
        if ($teacherName) $lines[] = "- اسم المعلم الباحث: {$teacherName}";
        if ($schoolGrade) $lines[] = "- المدرسة / الصف: {$schoolGrade}";
        if ($researchSummary) $lines[] = "- موضوع البحث بإيجاز: {$researchSummary}";
        if ($activities) $lines[] = "- أنشطة تتطلب الموافقة: {$activities}";

        return implode("\n", $lines);
    }
}

<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

/**
 * AnalyticalArticlePromptMaker: بناء prompts لأداة المقال التحليلي النقدي
 */
class AnalyticalArticlePromptMaker extends BasePromptMaker
{
    /**
     * بناء system prompt
     */
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        // إرجاع system prompt كما هو من قاعدة البيانات
        return trim($basePrompt);
    }

    /**
     * بناء user prompt من payload
     */
    public function buildUserPrompt(array $payload): string
    {
        $val = fn(?string $s): string => $this->cleanValue($s);
        
        // جلب البيانات من payload
        $phase = $val($payload['phase'] ?? '');
        $subject = $val($payload['subject'] ?? '');
        $teacherGender = $val($payload['teacher_gender'] ?? '');
        $studentsGender = $val($payload['students_gender'] ?? '');
        $problems = is_array($payload['problems'] ?? null) ? $payload['problems'] : [];
        $userPerspective = $val($payload['user_perspective'] ?? '');

        // التحقق من الحقول المطلوبة
        if ($phase === '' || $subject === '' || $teacherGender === '' || $studentsGender === '') {
            return json_encode([
                'need_more_info' => array_filter([
                    $phase === '' ? 'phase' : null,
                    $subject === '' ? 'subject' : null,
                    $teacherGender === '' ? 'teacher_gender' : null,
                    $studentsGender === '' ? 'students_gender' : null,
                ]),
                'message' => 'يرجى تزويدي بالبيانات الناقصة (المرحلة، المادة، جنس المعلم، جنس الطلبة).'
            ], JSON_UNESCAPED_UNICODE);
        }

        // التحقق من وجود مشكلة واحدة على الأقل
        if (empty($problems) || count($problems) === 0) {
            return json_encode([
                'need_more_info' => ['problems'],
                'message' => 'يرجى اختيار مشكلة واحدة على الأقل من المشكلات التعليمية.'
            ], JSON_UNESCAPED_UNICODE);
        }

        // بناء user prompt
        $prompt = "## المدخلات\n\n";
        $prompt .= "المرحلة الدراسية: {$phase}\n";
        $prompt .= "المادة الدراسية: {$subject}\n";
        $prompt .= "جنس المعلم: {$teacherGender}\n";
        $prompt .= "جنس الطلبة: {$studentsGender}\n\n";

        $prompt .= "المشكلات المحددة:\n";
        foreach ($problems as $index => $problem) {
            $num = $index + 1;
            $prompt .= "{$num}. {$problem}\n";
        }

        if ($userPerspective !== '') {
            $prompt .= "\nوجهة نظر المستخدم:\n{$userPerspective}\n";
        }

        $prompt .= "\n---\n";
        $prompt .= "يرجى إنتاج مقال تحليلي نقدي وفق التوجيهات المحددة.";

        return $prompt;
    }
}


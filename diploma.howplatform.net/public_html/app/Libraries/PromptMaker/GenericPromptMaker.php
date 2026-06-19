<?php

declare(strict_types=1);

namespace App\Libraries\PromptMaker;

/**
 * GenericPromptMaker: كلاس عام يعمل مع أي أداة جديدة
 *
 * لا يحتاج الأداة الجديدة لكلاس PHP خاص بها.
 * يقرأ system_prompt من قاعدة البيانات (عبر basePrompt)
 * ويبني user_prompt ديناميكياً من الـ payload.
 *
 * كيفية الاستخدام:
 * - عند إضافة أداة جديدة في ai_job_types، اتركي prompt_maker_class فارغاً
 *   أو ضعي App\Libraries\PromptMaker\GenericPromptMaker وسيعمل تلقائياً.
 */
class GenericPromptMaker extends BasePromptMaker
{
    /**
     * حقول يجب إخفاؤها من user prompt لأنها meta أو داخلية
     */
    private const SKIP_KEYS = [
        '__meta',
        '__task_prompt_mode',
        'task_prompt',   // task_prompt يُعالج بواسطة BasePromptMaker::buildMessages
        'task_title',    // يُضاف مباشرة لـ system prompt
    ];

    /**
     * يُعيد system_prompt كما هو من قاعدة البيانات
     * (basePrompt يحمل مسبقاً task_prompt المضاف من BasePromptMaker::buildMessages)
     */
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        return trim($basePrompt) !== '' ? trim($basePrompt) : $this->defaultSystemPrompt();
    }

    /**
     * يبني user_prompt ديناميكياً من جميع حقول الـ payload
     */
    public function buildUserPrompt(array $payload): string
    {
        $lines = ["IMPORTANT: Output MUST be valid JSON only with no extra text outside the JSON object.\n"];

        foreach ($payload as $key => $value) {
            if (in_array($key, self::SKIP_KEYS, true)) {
                continue;
            }

            $label = $this->humanLabel($key);
            $text  = is_array($value)
                ? $this->arrayToText($value)
                : $this->cleanValue((string)$value);

            if ($text === '') {
                continue;
            }

            $lines[] = "- {$label}: {$text}";
        }

        return implode("\n", $lines);
    }

    /**
     * تحويل مفتاح الحقل إلى تسمية مقروءة
     */
    private function humanLabel(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * system prompt افتراضي عند غياب الـ system_prompt من DB
     */
    private function defaultSystemPrompt(): string
    {
        return 'أنت مساعد ذكاء اصطناعي متخصص. أعِد إجابتك بصيغة JSON صالحة فقط دون أي نص خارجي.';
    }
}

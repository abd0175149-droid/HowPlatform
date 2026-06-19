<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

/**
 * SmartIngestionPromptMaker: صانع برومبت بسيط للتحليل الذكي
 * يمرر البرومبت والمرفقات كما هي
 */
class SmartIngestionPromptMaker extends BasePromptMaker
{
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        return $basePrompt;
    }

    public function buildUserPrompt(array $payload): string
    {
        return $payload['prompt'] ?? '';
    }
}

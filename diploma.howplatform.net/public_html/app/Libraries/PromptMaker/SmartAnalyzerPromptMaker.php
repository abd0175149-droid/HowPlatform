<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

/**
 * SmartAnalyzerPromptMaker: صانع برومبت للمحلل الذكي
 * يدمج برومبت المستخدم مع تعليمات الهيكلة الإلزامية (JSON)
 */
class SmartAnalyzerPromptMaker extends BasePromptMaker
{
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        // تعليمات النظام الأساسية: أنت محلل محتوى
        return "You are an expert document analyst and formatter. Follow user instructions precisely and return well-structured, accurate content.";
    }

    public function buildUserPrompt(array $payload): string
    {
        $userInstructions = $payload['prompt'] ?? 'Analyze and reformat this document with clear structure.';
        
        // تعليمات الهيكلة الإلزامية
        $structureInstructions = <<<STRUCT

IMPORTANT: You must format your output as a SINGLE VALID JSON object.

The structure must be:
{
  "content": "Your complete, formatted output here. Use \\n\\n for paragraphs, \\n for line breaks. Include headings with ## prefix."
}

OR if the user asks for sections/parts/units, use:
{
  "sections": [
    {
      "title": "Section title",
      "content": "Section content with proper formatting"
    }
  ]
}

USER INSTRUCTIONS:
{$userInstructions}

Follow the user instructions EXACTLY. If they ask for:
- "إعادة كتابة" or "تنسيق" or "ترتيب النص" → Use the "content" format with complete text
- "استخراج" or "وحدات" or "أقسام" → Use the "sections" format

Make sure the output is:
- Complete and accurate (no summarization unless asked)
- Well-formatted with clear structure
- Uses proper line breaks (\\n\\n between paragraphs)
- No tables or complex formatting, just clean text with headings

Do not include any text outside the JSON.
STRUCT;

        return $structureInstructions;
    }
}

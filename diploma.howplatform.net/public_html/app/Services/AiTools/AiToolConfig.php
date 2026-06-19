<?php
declare(strict_types=1);

namespace App\Services\AiTools;

/**
 * AiToolConfig: إعدادات أداة واحدة
 */
class AiToolConfig
{
    public function __construct(
        public readonly string $typeKey,
        public readonly string $title,
        public readonly string $description,
        public readonly string $provider,
        public readonly string $modelName,
        public readonly string $promptMakerClass,
        public readonly string $jsModulePath,
        public readonly ?string $templatePath = null,
        public readonly bool $isEnabled = true,
        public readonly array $uiConfig = [],
        // ─── إعدادات السلوك — مُدارة من قاعدة البيانات ───
        public readonly bool $needsTaskContext = true,
        public readonly bool $needsSpecialty = false,
        public readonly string $taskPromptMode = 'append',
    ) {}

    /**
     * إنشاء من سجل ai_job_types
     */
    public static function fromDbRow(array $row): self
    {
        $validModes = ['append', 'prepend', 'replace', 'ignore'];
        $taskPromptMode = (string) ($row['task_prompt_mode'] ?? 'append');
        if (!in_array($taskPromptMode, $validModes, true)) {
            $taskPromptMode = 'append';
        }

        return new self(
            typeKey: (string) $row['type_key'],
            title: (string) $row['title'],
            description: (string) ($row['description'] ?? ''),
            provider: (string) ($row['provider'] ?? 'gemini'),
            modelName: (string) ($row['model_name'] ?? 'gemini-2.0-flash'),
            promptMakerClass: (string) ($row['prompt_maker_class'] ?? ''),
            jsModulePath: (string) ($row['js_module_path'] ?? ''),
            templatePath: $row['template_path'] ? (string) $row['template_path'] : null,
            isEnabled: (bool) ($row['is_enabled'] ?? true),
            uiConfig: self::decodeUiConfig($row['ui_config_json'] ?? null),
            needsTaskContext: (bool) ($row['needs_task_context'] ?? true),
            needsSpecialty: (bool) ($row['needs_specialty'] ?? false),
            taskPromptMode: $taskPromptMode,
        );
    }

    /**
     * @return array<string,mixed>
     */
    protected static function decodeUiConfig(null|string $json): array
    {
        if (!$json) {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }
}


<?php
declare(strict_types=1);

namespace App\Services\AiTools;

use Config\Database;

/**
 * AiToolRegistry: سجل مركزي للأدوات المتاحة
 */
class AiToolRegistry
{
    private static ?array $tools = null;

    /**
     * جلب جميع الأدوات المفعلة
     */
    public static function getEnabledTools(): array
    {
        if (self::$tools === null) {
            self::loadTools();
        }
        return array_filter(self::$tools, fn($tool) => $tool->isEnabled);
    }

    /**
     * جلب أداة واحدة حسب type_key
     */
    public static function getTool(string $typeKey): ?AiToolConfig
    {
        if (self::$tools === null) {
            self::loadTools();
        }
        return self::$tools[$typeKey] ?? null;
    }

    /**
     * تحميل الأدوات من قاعدة البيانات
     */
    private static function loadTools(): void
    {
        $db = Database::connect();
        $rows = $db->table('ai_job_types')
            ->where('is_enabled', 1)
            ->get()
            ->getResultArray();

        self::$tools = [];
        foreach ($rows as $row) {
            try {
                $tool = AiToolConfig::fromDbRow($row);
                self::$tools[$tool->typeKey] = $tool;
            } catch (\Throwable $e) {
                log_message('error', 'Failed to load AI tool: {msg}', ['msg' => $e->getMessage()]);
            }
        }
    }

    /**
     * إعادة تحميل الأدوات (للاستخدام بعد تحديث قاعدة البيانات)
     */
    public static function reload(): void
    {
        self::$tools = null;
        self::loadTools();
    }
}


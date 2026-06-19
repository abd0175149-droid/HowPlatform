<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * موديل أنواع مهام الذكاء الاصطناعي
 */
class AiJobTypeModel extends Model
{
    protected $table            = 'ai_job_types';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'type_key', 'title', 'description', 'provider', 'model_name',
        'system_prompt', 'template_path', 'output_schema', 'is_enabled',
        'points_cost', 'needs_task_context', 'needs_specialty', 'task_prompt_mode',
        'created_at', 'updated_at',
    ];


    // جلب النوع عبر type_key ومتاح (is_enabled = 1)
    public function getEnabledByKey(string $typeKey): ?array
    {
        return $this->where('type_key', $typeKey)
                    ->where('is_enabled', 1)
                    ->first() ?: null;
    }
}

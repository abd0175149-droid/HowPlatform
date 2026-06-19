<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ToolStat extends Model
{
    // اسم الجدول مفتاحه الأساسي tool_id (بدون auto-increment)
    protected $table = 'tool_stats';
    protected $primaryKey = 'tool_id';
    public $incrementing = false;

    protected $fillable = [
        'tool_id',
        'uses_count',
        'unique_users_count',
        'rating_avg',
        'rating_count',
        'last_used_at',
    ];

    // لو جدولك فيه created_at/updated_at (نعم عندك)، خلّي timestamps مفعّلة (الإعداد الافتراضي true)
    // protected $timestamps = true; // غير لازم، الافتراضي مفعّل

    public function tool()
    {
        return $this->belongsTo(Tool::class, 'tool_id', 'id');
    }
}

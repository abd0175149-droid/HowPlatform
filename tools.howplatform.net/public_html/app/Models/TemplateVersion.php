<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateVersion extends Model
{
    protected $table = 'template_versions';
    protected $guarded = [];
    protected $casts = [
        'data_schema' => 'array',
        'ui_schema'   => 'array',
    ];

    public function template() { return $this->belongsTo(FormTemplate::class, 'form_template_id'); }
}

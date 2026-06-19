<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormSection extends Model
{
    protected $table = 'form_sections';
    protected $guarded = [];
    protected $casts = [
        'visible_if' => 'array',
    ];

    public function template() { return $this->belongsTo(FormTemplate::class, 'form_template_id'); }
    public function fields()   { return $this->hasMany(FormField::class, 'section_id'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormField extends Model
{
    protected $table = 'form_fields';
    protected $guarded = [];
    protected $casts = [
        'options'    => 'array',
        'validation' => 'array',
        'visibility' => 'array',
    ];

    public function template()    { return $this->belongsTo(FormTemplate::class, 'form_template_id'); }
    public function section()     { return $this->belongsTo(FormSection::class, 'section_id'); }
    public function fieldOptions(){ return $this->hasMany(FieldOption::class, 'form_field_id'); }
}

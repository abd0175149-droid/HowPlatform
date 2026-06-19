<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormTemplate extends Model
{
    protected $table = 'form_templates';
    protected $guarded = [];
    protected $casts = [
        'ui_schema'   => 'array',
        'data_schema' => 'array',
    ];

    public function sections()  { return $this->hasMany(FormSection::class); }
    public function fields()    { return $this->hasMany(FormField::class); }
    public function assignments(){ return $this->hasMany(TemplateAssignment::class); }
    public function versions()  { return $this->hasMany(TemplateVersion::class); }
    public function submissions(){ return $this->hasMany(FormSubmission::class); }
}

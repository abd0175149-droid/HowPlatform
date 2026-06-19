<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateAssignment extends Model
{
    protected $table = 'template_assignments';
    protected $guarded = [];

    public function template() { return $this->belongsTo(FormTemplate::class, 'form_template_id'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    protected $table = 'form_submissions';
    protected $guarded = [];
    protected $casts = [
        'values' => 'array',
    ];

    public function template() { return $this->belongsTo(FormTemplate::class, 'form_template_id'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldOption extends Model
{
    protected $table = 'field_options';
    protected $guarded = [];
    protected $casts = [
        'meta' => 'array',
    ];

    public function field() { return $this->belongsTo(FormField::class, 'form_field_id'); }
}

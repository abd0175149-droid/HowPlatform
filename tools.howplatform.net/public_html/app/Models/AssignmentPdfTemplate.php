<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentPdfTemplate extends Model
{
    protected $table = 'assignment_pdf_templates';
    protected $guarded = [];

    public function slots()    { return $this->hasMany(AssignmentPdfSlot::class, 'assignment_pdf_template_id'); }
    public function bindings() { return $this->hasMany(TemplatePdfBinding::class, 'assignment_pdf_template_id'); }
}

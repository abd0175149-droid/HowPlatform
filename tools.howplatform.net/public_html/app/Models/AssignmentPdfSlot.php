<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentPdfSlot extends Model
{
    protected $table = 'assignment_pdf_slots';
    protected $guarded = [];
    // يمكن إضافة $casts لاحقًا إن رغبت

    public function pdfTemplate() { return $this->belongsTo(AssignmentPdfTemplate::class, 'assignment_pdf_template_id'); }
}

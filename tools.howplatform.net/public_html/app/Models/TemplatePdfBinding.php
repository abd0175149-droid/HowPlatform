<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplatePdfBinding extends Model
{
    protected $table = 'template_pdf_bindings';
    protected $guarded = [];

    public function template()   { return $this->belongsTo(FormTemplate::class, 'form_template_id'); }
    public function pdfTemplate(){ return $this->belongsTo(AssignmentPdfTemplate::class, 'assignment_pdf_template_id'); }

    /**
     * اختيار أفضل ربط PDF لقالب معيّن بحسب الجامعة (إن وُجد)، وإلا الافتراضي (NULL).
     */
    public static function bestFor(int $templateId, array $ctx): ?self
    {
        $u = $ctx['university_id'] ?? null;

        return static::where('form_template_id', $templateId)
            ->when($u, fn($q) => $q->where(function($w) use ($u) {
                $w->where('university_id', $u)->orWhereNull('university_id');
            }))
            ->when(!$u, fn($q) => $q->whereNull('university_id'))
            ->orderByRaw('CASE WHEN university_id = ? THEN 2 WHEN university_id IS NULL THEN 1 ELSE 0 END DESC', [$u])
            ->first();
    }
}

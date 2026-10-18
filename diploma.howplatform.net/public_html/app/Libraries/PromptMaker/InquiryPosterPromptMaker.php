<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

/**
 * InquiryPosterPromptMaker
 * ------------------------------------------------------------------
 * أداة «بوستر البحث الاستقصائي» (inquiry_poster_v1).
 * المسار: إنشاء صورة إنفوجرافيك (لاند سكيب) بالذكاء الاصطناعي من البحث الاستقصائي الذي يرفعه الطالب.
 *
 * يعمل على مرحلتين:
 *  1) استخراج العناصر الستة نصّياً (JSON) من البحث الاستقصائي بنموذج نصّي  → buildSystemPrompt/buildUserPrompt
 *     (يمرّ عبر AiClient::generateJson المعتاد، والملف يُمرَّر كـ attachments أو source_text).
 *  2) توليد الصورة بنموذج صور من العناصر المُستخرَجة                 → buildImagePrompt()
 *     (يمرّ عبر AiClient::generateImage).
 *
 * العناصر الستة (مفتاح JSON => العنوان العربي):
 */
class InquiryPosterPromptMaker extends BasePromptMaker
{
    /** العناصر الستة المطلوبة في البوستر. */
    public const ELEMENTS = [
        'title'                 => 'عنوان البحث الاستقصائي',
        'objective'             => 'الهدف من البحث الاستقصائي',
        'procedures_summary'    => 'ملخص إجراءات البحث الاستقصائي (نظرة عامة حول الاستقصاء)',
        'context'               => 'السياق الذي تم إجراء البحث الاستقصائي فيه',
        'results'               => 'نتائج البحث الاستقصائي',
        'reflective_discussion' => 'لمحة عن النقاش التأملي',
    ];

    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        // نُعيد system_prompt من قاعدة البيانات كما هو (مرحلة الاستخراج النصّي).
        return trim($basePrompt);
    }

    /**
     * مرحلة الاستخراج: استخرج العناصر الستة حرفياً من البحث الاستقصائي المرفق/المضمّن.
     */
    public function buildUserPrompt(array $payload): string
    {
        $sourceText = trim((string)($payload['source_text'] ?? ''));
        $hasAttachment = !empty($payload['attachments']) && is_array($payload['attachments']);

        $elementsList = $this->buildElementsList();
        $jsonStruct   = $this->buildExtractionJsonStructure();

        if ($sourceText !== '') {
            $sourceBlock = "=== بداية البحث الاستقصائي (نصّياً) ===\n{$sourceText}\n=== نهاية البحث الاستقصائي ===";
        } elseif ($hasAttachment) {
            $sourceBlock = "البحث الاستقصائي مُرفَق بهذه الرسالة كملف (صورة/PDF). اقرأه بعناية واستخرج منه المحتوى.";
        } else {
            $sourceBlock = "(لم يُزوَّد محتوى — أعد العناصر فارغة قدر الإمكان دون اختلاق معلومات.)";
        }

        return <<<USR
IMPORTANT: Output MUST be a SINGLE valid JSON object only — no extra text, no markdown, no code blocks. Start with { and end with }.

مهمتك: أنت مساعد يقرأ «بحثاً استقصائياً» أنجزه طالب، ويستخرج منه ستة عناصر بدقّة وحرفية لاستخدامها في تصميم بوستر إنفوجرافيك.

{$sourceBlock}

=== العناصر الستة المطلوب استخراجها (بدقّة وحرفية من البحث) ===
{$elementsList}

قواعد الاستخراج:
- انسخ المحتوى من البحث بدقّة وأمانة دون اختلاق معلومات غير موجودة.
- «العنوان» جملة قصيرة واحدة. باقي العناصر: لخّصها في فقرة/نقاط موجزة واضحة مناسبة للعرض على بوستر (لا تُطل؛ المتن سيُعرض بحجم خط صغير).
- إن لم يَرِد عنصر صراحةً في البحث، استنتجه بحذر من سياق البحث، أو اتركه نصّاً فارغاً "" إن تعذّر.
- إن وُجدت في النتائج أرقام/نِسب/بيانات قابلة للتمثيل البياني، فأدرجها في حقل chart_data لرسمها لاحقاً.
- لغة عربية فصيحة سليمة.

=== هيكل JSON المطلوب (التزم به حرفياً) ===
{$jsonStruct}
USR;
    }

    /**
     * مرحلة توليد الصورة: يبني توجيه إنشاء صورة الإنفوجرافيك من العناصر المُستخرَجة.
     *
     * @param array $el العناصر المُستخرَجة (مفاتيح ELEMENTS) + اختياري chart_data
     */
    public function buildImagePrompt(array $el): string
    {
        $title        = $this->oneLine($el['title'] ?? '');
        $objective    = $this->block($el['objective'] ?? '');
        $procedures   = $this->block($el['procedures_summary'] ?? '');
        $context      = $this->block($el['context'] ?? '');
        $results      = $this->block($el['results'] ?? '');
        $reflective   = $this->block($el['reflective_discussion'] ?? '');

        $chartHint = '';
        if (!empty($el['chart_data'])) {
            $chartJson = json_encode($el['chart_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $chartHint = "\nبيانات قابلة للتمثيل البياني (ارسمها كمخطط أعمدة/دائري مناسب داخل قسم النتائج): {$chartJson}";
        }

        // توجيه ثنائي اللغة: الوصف التقني بالإنجليزية + النصوص العربية حرفياً ليرسمها النموذج كما هي.
        return <<<IMG
Create a single, polished LANDSCAPE infographic poster (wide 16:9 orientation) about a student inquiry research project. The poster is fully in ARABIC with right-to-left (RTL) reading direction.

TYPOGRAPHY:
- Use a clean, traditional Arabic serif typeface in the style of "Sakkal Majalla".
- Visual hierarchy: the research TITLE is the largest heading (about 44pt), each section sub-heading is medium (about 24pt), and body text is small and readable (about 18pt).
- Render ALL Arabic text ACCURATELY, LITERALLY and legibly — correct connected Arabic letterforms, no gibberish, no Latin placeholder text. Use the exact wording provided below.

LAYOUT — a header band with the title, then SIX clearly separated sections arranged across the landscape canvas (e.g. a multi-column grid with section cards). Each section has its Arabic sub-heading then its body content:
1) العنوان (Title, header band): {$title}
2) الهدف من البحث الاستقصائي: {$objective}
3) ملخص إجراءات البحث الاستقصائي (نظرة عامة حول الاستقصاء): {$procedures}
4) السياق الذي تم إجراء البحث فيه: {$context}
5) نتائج البحث الاستقصائي: {$results}
6) لمحة عن النقاش التأملي: {$reflective}

VISUALS:
- Express the RESULTS using a relevant chart, graph, or data table (bars / pie / simple table) plus supporting icons.{$chartHint}
- Add tasteful, content-related illustrations/icons (related to the research topic) for the other sections.
- Use a calm, harmonious, eye-friendly color palette suitable for an academic poster (soft, professional, good contrast).

REQUIREMENTS:
- Include ALL SIX sections accurately and completely. Do not omit any section.
- Keep the design uncluttered, balanced, and print-ready.
IMG;
    }

    /** قائمة العناصر الستة للعرض في توجيه الاستخراج. */
    private function buildElementsList(): string
    {
        $lines = [];
        $i = 1;
        foreach (self::ELEMENTS as $key => $label) {
            $lines[] = "{$i}) {$label}  [مفتاح JSON: {$key}]";
            $i++;
        }
        return implode("\n", $lines);
    }

    /** هيكل JSON لمرحلة الاستخراج. */
    private function buildExtractionJsonStructure(): string
    {
        return <<<'STRUCTURE'
{
  "title": "عنوان البحث الاستقصائي (جملة قصيرة)",
  "objective": "الهدف من البحث الاستقصائي (فقرة موجزة)",
  "procedures_summary": "ملخص إجراءات البحث ونظرة عامة حول الاستقصاء (فقرة موجزة أو نقاط مختصرة)",
  "context": "السياق الذي تم إجراء البحث فيه (فقرة موجزة)",
  "results": "نتائج البحث الاستقصائي (فقرة موجزة أو نقاط)",
  "reflective_discussion": "لمحة عن النقاش التأملي (فقرة موجزة)",
  "chart_data": { "labels": ["..."], "values": [0], "type": "bar" }
}
STRUCTURE;
    }

    /** سطر واحد مُنظَّف (للعنوان). */
    private function oneLine($v): string
    {
        return $this->cleanValue(is_scalar($v) ? (string)$v : $this->arrayToText($v));
    }

    /** كتلة نصّية مُنظَّفة (تستوعب المصفوفات أو النص). */
    private function block($v): string
    {
        if (is_array($v)) {
            return $this->arrayToText($v, '؛ ');
        }
        return trim((string)$v);
    }
}

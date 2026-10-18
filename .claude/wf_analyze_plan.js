export const meta = {
  name: 'analyze-formative-essay-tool-plan',
  description: 'Adversarially verify the formative_essay_evaluator_v1 implementation plan against the REAL diploma codebase; produce an Arabic analysis (soundness, collisions, blockers, corrections)',
  phases: [
    { title: 'Verify', detail: '6 parallel verifiers, each checks a cluster of plan claims vs actual code' },
    { title: 'Synthesize', detail: 'merge into one Arabic analysis: verdict, critical findings, per-phase assessment, required corrections' },
    { title: 'Critique', detail: 'adversarial check of the two critical findings (docx to model input, Word-annotation feasibility)' },
    { title: 'Finalize', detail: 'final authoritative Arabic analysis' },
  ],
}

const BASE = 'diploma.howplatform.net/public_html'

const SEED = [
  'VERIFIED SEED FACTS (from a prior scout - re-verify, do not blindly trust):',
  "- The plan proposes a NEW tool key 'formative_essay_evaluator_v1' (does NOT exist yet).",
  '- An EXISTING system already uses the name "formative essay": global $_formative_essay_payload is set in ' + BASE + '/app/Controllers/Cron/AiJobsRunner.php at lines 198-199 AND 498-499; file naming formative_essay_<user>_<task>_<ts>.docx into folder downloads/formative_essays/ at lines ~261-265 and ~562-566. The active tool is formative_reflective_essay_v1 (many done jobs); also reflective_essay_v1 exists. PromptMakers present: FormativeReflectiveEssayPromptMaker, RewrittenReflectiveEssayPromptMaker.',
  '- Word-COMMENT/annotation capability is ABSENT. DocxService only HIGHLIGHTS table cells (highlightRubricCells/domHighlightCell/xmlHighlightCellContaining via ZipArchive+DOM). No comments.xml / commentRangeStart anywhere.',
  '- PromptMakerFactory::$makers does NOT list formative/evaluator-essay keys (resolved from DB prompt_maker_class).',
].join('\n')

const PREAMBLE = [
  'You are verifying a written IMPLEMENTATION PLAN for a new AI tool (formative_essay_evaluator_v1) in a CodeIgniter 4 app (the "diploma" subdomain). App root: ' + BASE + '/. Use Read/Grep/Glob.',
  '',
  'The plan intent: a tool that takes ONE input (a Word .docx essay uploaded by the user) and produces TWO outputs: (1) the ORIGINAL essay .docx with >=15 Word margin COMMENTS (highlight + comment on quoted anchor text), and (2) a formative feedback REPORT .docx (4 domains: knowledge_understanding_application, cognitive_skills, professional_skills, presentation + overall_suggested_actions). It is ASYNC, copies inquiry_report_evaluator_v1 (human review status + approve + multi-file output), injects teacher specialty, and is RESTRICTED to specific users via an env allowlist (like isArticleEvaluatorAllowed). The model must return a SINGLE JSON object with shape report{...} and annotations[...].',
  '',
  'YOUR JOB: for the claims assigned to you, READ the real code and judge whether the plan is CORRECT, NEEDS-ADJUSTMENT, has an ERROR, or hits a BLOCKER (a claim that will not work as written). Quote real file:line, method names, and code. Be adversarial - actively try to find where the plan is wrong or naive. Token cost is not a constraint.',
  '',
  SEED,
].join('\n')

const SCHEMA = {
  type: 'object', additionalProperties: false,
  required: ['dimension', 'summary', 'claims_checked', 'findings', 'verdict', 'corrections', 'evidence'],
  properties: {
    dimension: { type: 'string' },
    summary: { type: 'string' },
    claims_checked: { type: 'array', items: { type: 'string' } },
    findings: { type: 'string', description: 'detailed markdown: what the plan claims vs what the code actually does; quote file:line' },
    verdict: { type: 'string', enum: ['sound', 'needs-adjustment', 'has-errors', 'blocker'] },
    corrections: { type: 'array', items: { type: 'string' }, description: 'concrete fixes the plan needs' },
    evidence: { type: 'array', items: { type: 'string' }, description: 'file:line references proving the findings' },
  },
}

const VERIFIERS = [
  { key: 'collision-cron', prompt: PREAMBLE + '\n\n' + [
    'CLUSTER: Collision with the existing "formative essay" system + cron binding/review/multi-file (plan phases 1, 7, 8).',
    'READ: ' + BASE + '/app/Controllers/Cron/AiJobsRunner.php (lines ~180-280 and ~480-580 - the existing formative_essay branches, $_formative_essay_payload, naming, folder; also how inquiry_report_evaluator_v1 sets status=review and builds multi files); ' + BASE + '/app/Libraries/PromptMaker/FormativeReflectiveEssayPromptMaker.php; ' + BASE + '/app/Libraries/PromptMaker/RewrittenReflectiveEssayPromptMaker.php; grep formative_reflective_essay_v1 and reflective_essay_v1 and formative_essay across app; buildMultiFileList in ' + BASE + '/app/Services/AiTools/AiToolService.php and the multi-file branch in ' + BASE + '/app/Controllers/Api/AiJobs.php (status).',
    'JUDGE: (a) What does the EXISTING formative_reflective_essay_v1 actually do, and does the existing $_formative_essay_payload global + formative_essay_* naming + downloads/formative_essays/ folder belong to it? (b) The plan Phase 7 step 4 says to reuse the global $_formative_essay_payload for the NEW evaluator - IS THAT A COLLISION with the existing tool? Must the new tool use a DISTINCT global + distinct naming/folder? (c) Are the cron naming branches really duplicated in TWO places (processOneJob ~507 and rebuildFileForJob ~207)? (d) Is the review/approve/multi-file pattern really implemented for inquiry_report_evaluator_v1 and copyable?',
  ].join('\n') },

  { key: 'access-env', prompt: PREAMBLE + '\n\n' + [
    'CLUSTER: Access restriction to env-listed users only (plan phase 9) - THE USER EXPLICITLY EMPHASIZES THIS: the tool must be available ONLY to users listed in an env var, not everyone.',
    'READ: ' + BASE + '/app/Controllers/Students/AiToolsController.php - find isArticleEvaluatorAllowed() (~line 1785) and read it FULLY: the $restrictions map, the env() default, and EVERY endpoint that calls it (index/request/jobsList/checkAccess/status/result/placements). Also ' + BASE + '/app/Services/AiTools/AiToolAccessService.php (does access gating happen there too?).',
    'JUDGE: (a) Exactly how to restrict the new tool to env-listed users - add formative_essay_evaluator_v1 => <ENV_KEY> to $restrictions and call the gate in WHICH endpoints? (b) The env-key decision: reusing ARTICLE_EVALUATOR_ALLOWED_USERS merges users with the article evaluators AND inherits its hardcoded default 974,500,501,1021; a NEW env key has NO default so forgetting to set it locks EVERYONE out. Which is correct for "only env users"? (c) Does placements() enforce it (button visibility) or only the run endpoints (403)? (d) Pitfalls: is the check applied consistently, and does it use effective user id (admin-preview)?',
  ].join('\n') },

  { key: 'docx-input', prompt: PREAMBLE + '\n\n' + [
    'CLUSTER (CRITICAL): How the uploaded .docx essay actually reaches the model (plan phase 4). The plan says: upload .docx, put it as Base64 in payload attachments [{mimeType,data}] and let AiClient buildParts send it as inlineData to Gemini.',
    'READ: ' + BASE + '/app/Libraries/AiClient.php - find buildParts() and how attachments / inlineData are built and which mimeTypes are sent to Gemini/Vertex; ' + BASE + '/app/Services/Rag/SmartIngestionService.php (how smart_analyzer ingests an uploaded file - does it EXTRACT TEXT from docx?); ' + BASE + '/app/Libraries/DocxService.php (is there any docx TEXT EXTRACTION, e.g. reading document.xml?); grep for extractText, getText, PhpWord, inlineData, attachments, mimeType, .docx usage; how article_evaluator_v1 / inquiry_report_evaluator_v1 (which also evaluate uploaded essays) receive the essay text today.',
    'JUDGE (be skeptical): (a) Does Gemini inlineData actually accept a .docx (application/vnd.openxmlformats-officedocument.wordprocessingml.document)? Or does it only accept PDF/images/plain-text? If .docx is NOT a supported inlineData type, the plan Phase 4 is a BLOCKER - the essay TEXT must be extracted server-side first. (b) How do the EXISTING evaluator tools get the essay content (text extraction vs attachment)? (c) What is the CORRECT input path for a .docx essay here? This is the highest-risk claim - verify hard.',
  ].join('\n') },

  { key: 'prompt-json', prompt: PREAMBLE + '\n\n' + [
    'CLUSTER: PromptMaker + JSON contract (plan phases 2, 3).',
    'READ: ' + BASE + '/app/Libraries/PromptMaker/BasePromptMaker.php; ' + BASE + '/app/Libraries/PromptMaker/InstantFeedbackPromptMaker.php (template to copy); ' + BASE + '/app/Libraries/PromptMaker/ReflectionPromptMaker.php (teacher_specialty injection pattern); ' + BASE + '/app/Libraries/PromptMaker/InquiryReportEvaluatorPromptMaker.php (closest analog); and how the cron parses the model JSON (AiJobsRunner: extractFirstJsonObject / json_decode).',
    'JUDGE: (a) Is the plan FormativeEssayEvaluatorPromptMaker design correct (extends BasePromptMaker, implements buildSystemPrompt/buildUserPrompt, reads teacher_specialty explicitly, NOT added to $makers)? (b) Does the proposed single JSON (report with 4 domains, annotations >=15) fit the cron single-top-level-object parsing? (c) Any issue with the 3-section merge (__task_prompt_mode, needs_task_context=1 -> task_prompt) causing duplication, given the essay text is the main user input? (d) Will needs_specialty=1 actually surface unless the maker reads payload teacher_specialty?',
  ].join('\n') },

  { key: 'report-docx', prompt: PREAMBLE + '\n\n' + [
    'CLUSTER: Output #2 - the formative feedback REPORT .docx via DocxService (plan phase 5).',
    'READ: ' + BASE + '/app/Libraries/DocxService.php - buildFromTemplate(), the GENERIC branch and its fixed key sets (baseKeys/matrixKeys/lessonPlanKeys), setValueSafely, cleanUnreplacedVariables, sanitizeForXml; the existing per-type branches (e.g. analytical_article_v1, inquiry_report_evaluator_v1); and the existing formative_essay docx build in AiJobsRunner (~lines 198-265) - does a formative report docx ALREADY get built today, and from what template?',
    'JUDGE: (a) Does the plan correctly require a NEW custom branch if(typeKey===...) in DocxService (because the report keys are outside the generic fixed set)? (b) Is there ALREADY a formative report template/branch that the new tool should reuse or that will collide? (c) sanitizeForXml mangling of angle-brackets and ampersand - any impact on the report text? (d) Is the template placeholder naming guidance correct (the dollar-brace field tokens)?',
  ].join('\n') },

  { key: 'word-annotations', prompt: PREAMBLE + '\n\n' + [
    'CLUSTER (CRITICAL / genuinely new): Output #1 - adding real Word COMMENTS to the original essay .docx (plan phase 6, new DocxAnnotationService).',
    'READ: ' + BASE + '/app/Libraries/DocxService.php - the existing ZIP/XML manipulation it ALREADY does for highlighting (highlightRubricCells ~2235, domHighlightCell ~2069, xmlHighlightCellContaining ~2197, how it opens/saves the docx with ZipArchive). Confirm there is NO existing Word-comment capability. grep for ZipArchive, DOMDocument, document.xml, comments.xml.',
    'JUDGE: (a) Confirm Word-comment injection does NOT exist today (only cell highlighting). (b) Assess FEASIBILITY of the plan approach (edit word/document.xml with commentRangeStart/End + commentReference, create word/comments.xml, register in document.xml.rels and [Content_Types].xml, possibly commentsExtended.xml/people.xml). What does the existing highlighting code give as a reusable foundation (ZipArchive open/replace/save, DOM over document.xml)? (c) The fuzzy anchor_text matching + unmatched list - sensible? (d) Risks: XML validity, RTL/Arabic text, Word refusing to open a malformed file. Rate effort/risk. (e) Does the input even arrive as a real .docx the service can open (depends on the docx-input cluster)?',
  ].join('\n') },
]

phase('Verify')
log('Verifying the plan across ' + VERIFIERS.length + ' dimensions against the real code...')
const reports = (await parallel(VERIFIERS.map(v => () => agent(v.prompt, { label: v.key, phase: 'Verify', schema: SCHEMA })))).filter(Boolean)
log(reports.length + '/' + VERIFIERS.length + ' verifier reports returned.')

const bundle = reports.map(r => (
  '# ' + r.dimension + '  [verdict: ' + r.verdict + ']\n' +
  'Summary: ' + r.summary + '\n\nClaims checked:\n- ' + (r.claims_checked || []).join('\n- ') + '\n\n' +
  'Findings:\n' + r.findings + '\n\nCorrections:\n- ' + (r.corrections || []).join('\n- ') + '\n\n' +
  'Evidence: ' + (r.evidence || []).join(', ')
)).join('\n\n========================================\n\n')

phase('Synthesize')
const synthInstr = [
  'You are the lead reviewer. Below are 6 adversarial verification reports about an implementation plan for the formative_essay_evaluator_v1 AI tool, checked against the real diploma codebase. Write ONE authoritative analysis IN ARABIC (identifiers/code in English). Structure:',
  '1. الحكم العام: هل الخطة سليمة وقابلة للتنفيذ كما هي؟ (نعم / بتعديلات / فيها أخطاء / فيها حواجب) - جملة حاسمة.',
  '2. أهم النتائج الحرجة مرتّبة بالخطورة - خاصة: (أ) التصادم مع نظام formative_essay القائم، (ب) هل إدخال ملف docx كـ inlineData لـ Gemini يعمل أم يجب استخراج النص أولاً (حاجب محتمل)، (ج) جدوى/جهد تعليقات Word الجديدة، (د) الوصول المقيّد عبر env. لكل نتيجة: ما تقوله الخطة، ما يقوله الكود فعلاً (file:line)، والأثر.',
  '3. تقييم كل مرحلة من مراحل الخطة (1..10): سليمة / تحتاج تعديل / خطأ / حاجب + التصحيح المطلوب باختصار.',
  '4. التعديلات الإلزامية قبل التنفيذ (قائمة مرقّمة قابلة للتنفيذ).',
  '5. تقدير الجهد والمخاطر (أي جزء هو الأصعب/الأخطر).',
  '6. التوصية النهائية.',
  'كن دقيقاً ومحدّداً واقتبس الأسماء الحقيقية. لا تجامل الخطة - إن كان جزء خاطئاً قُلْه صراحةً.',
  '',
  'التقارير:',
  '',
  bundle,
].join('\n')
const analysis = await agent(synthInstr, { label: 'synthesize', phase: 'Synthesize', effort: 'high' })

phase('Critique')
const critInstr = 'Adversarially stress-test the two CRITICAL claims in this analysis, re-reading code if needed: (1) whether a .docx can be sent to Gemini as inlineData or whether text must be extracted first (read ' + BASE + '/app/Libraries/AiClient.php buildParts + how existing evaluator tools ingest essays), and (2) the real effort/risk of injecting valid Word comments into an existing .docx given what ' + BASE + '/app/Libraries/DocxService.php already does. List any place the analysis is wrong, overconfident, or missing a blocker. Be specific with file:line.\n\nANALYSIS:\n' + analysis
const critique = await agent(critInstr, { label: 'critique', phase: 'Critique', effort: 'high' })

phase('Finalize')
const finInstr = 'Produce the FINAL authoritative Arabic analysis of the formative_essay_evaluator_v1 plan. Start from the draft, incorporate the critic corrections, keep all real identifiers and file:line refs. Output only the complete analysis document (no preamble).\n\n=== DRAFT ===\n' + analysis + '\n\n=== CRITIC ===\n' + critique + '\n\n=== RAW VERIFIER REPORTS ===\n' + bundle
const final = await agent(finInstr, { label: 'finalize', phase: 'Finalize', effort: 'high' })

return { analysis: final, verifiers: reports.length, verdicts: reports.map(r => r.dimension + ': ' + r.verdict) }

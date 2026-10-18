export const meta = {
  name: 'design-formative-essay-evaluator-plan',
  description: 'Design an authoritative, buildable Arabic implementation plan for formative_essay_evaluator_v1 (docx-in, annotated-docx + report-out, review/approve, env-restricted) - judge-panel on the Word-annotation tech, then synthesize the full plan',
  phases: [
    { title: 'Design', detail: 'parallel: 3 annotation-tech proposers + extraction advisor + 2 code-pattern scouts' },
    { title: 'Decide', detail: 'judge picks the annotation + extraction approach and infra implications' },
    { title: 'Plan', detail: 'synthesize the full detailed Arabic implementation plan' },
    { title: 'Critique', detail: 'adversarial check of feasibility + completeness' },
    { title: 'Finalize', detail: 'final authoritative plan' },
  ],
}

const BASE = 'diploma.howplatform.net/public_html'

const CTX = [
  'CONTEXT: CodeIgniter 4 app ("diploma" subdomain), app root ' + BASE + '/. Use Read/Grep/Glob.',
  '',
  'GOAL: design (NOT implement) a clear, detailed, BUILDABLE plan for a NEW async AI tool type_key=formative_essay_evaluator_v1.',
  'Requirement (fixed vision): ONE input = a Word .docx essay uploaded by the user. TWO outputs:',
  '  (1) the ORIGINAL essay .docx returned WITH >=15 real Word margin COMMENTS (highlight + comment) anchored to literally-quoted text spans, preserving the user original formatting;',
  '  (2) a formative feedback REPORT .docx (constructive, NO grades) over 4 domains (knowledge_understanding_application, cognitive_skills, professional_skills, presentation) + overall_suggested_actions, aligned to Gibbs reflective cycle.',
  'It is ASYNC (queued -> cron), uses human review status + approve (copy inquiry_report_evaluator_v1), multi-file output, injects teacher specialty, model returns a SINGLE JSON object {report:{...}, annotations:[...]}.',
  'ACCESS: available ONLY to users listed in an env allowlist (not everyone) - must be enforced including button visibility (placements).',
  '',
  'HARD CONSTRAINTS / verified facts (from prior deep analysis - treat as ground truth, re-verify if you touch them):',
  '- IGNORE / do NOT modify the existing old tool formative_reflective_essay_v1 (a generator) NOR reuse its global $_formative_essay_payload, its naming formative_essay_*, or its folder downloads/formative_essays/. The new tool MUST use a DISTINCT global ($_formative_essay_evaluator_payload), DISTINCT folder (e.g. downloads/formative_evaluations/), DISTINCT file naming.',
  '- BLOCKER from the old plan: sending a .docx as Gemini inlineData does NOT work (Gemini inlineData supports PDF/images/text, not OOXML/.docx) AND AiClient.buildParts reads attachment key "mime" not "mimeType" and is dropped on the safety fallback path. So the essay TEXT must be EXTRACTED server-side and placed in a payload field (e.g. essay_text); existing evaluators (article_evaluator_v1, inquiry_report_evaluator_v1) receive essay content as TEXT fields in JSON.',
  '- Word-COMMENT injection does NOT exist anywhere today; DocxService only highlights table cells via ZipArchive+DOM. PhpWord 1.4.0 supports comments but only by re-reading the file with a LOSSY reader (loses user formatting) - unacceptable for returning the user original file.',
  '- ai_job_types insert MUST use raw SQL (AiJobTypeModel allowedFields drops prompt_maker_class/ui_config_json/js_module_path). No version column (version = _vN suffix). PromptMakerFactory::$makers overrides DB - do NOT add the new maker there. AI_BUILD_DOCX=1 gate. Cron budget ~120s vs AI_HTTP_TIMEOUT 300s.',
  '',
  'INFRA / TECH FREEDOM: the diploma container is php:8.3-apache (docker/Dockerfile.ci4) with composer; PhpWord, ZipArchive, DOMDocument, intl/xsl/zip available. You MAY propose adding new composer libs, OR a new sidecar container (python/node) to /home/sysadmin/HowPlatform/docker-compose.yml if it gives materially better reliability - but weigh the infra cost (new runtime, IPC, the original-file round-trip, deployment) honestly.',
].join('\n')

const PROPOSAL_SCHEMA = {
  type: 'object', additionalProperties: false,
  required: ['approach_name', 'language_runtime', 'how_it_works', 'formatting_preservation', 'rtl_arabic_handling', 'word_opens_cleanly', 'infra_cost', 'effort', 'libraries', 'key_risks', 'recommendation_strength', 'notes'],
  properties: {
    approach_name: { type: 'string' },
    language_runtime: { type: 'string' },
    how_it_works: { type: 'string', description: 'concrete mechanism, step by step' },
    formatting_preservation: { type: 'string', enum: ['high', 'medium', 'low'] },
    rtl_arabic_handling: { type: 'string' },
    word_opens_cleanly: { type: 'string', enum: ['high', 'medium', 'low'], description: 'reliability that Word opens the result without a repair prompt' },
    infra_cost: { type: 'string', description: 'new container? new dep? deployment impact' },
    effort: { type: 'string', enum: ['low', 'medium', 'high', 'very-high'] },
    libraries: { type: 'array', items: { type: 'string' } },
    key_risks: { type: 'array', items: { type: 'string' } },
    recommendation_strength: { type: 'integer', description: '1 weak .. 5 strong' },
    notes: { type: 'string' },
  },
}

const SCOUT_SCHEMA = {
  type: 'object', additionalProperties: false,
  required: ['area', 'findings', 'patterns_to_copy', 'file_refs'],
  properties: {
    area: { type: 'string' },
    findings: { type: 'string', description: 'detailed markdown: exact signatures, how the pattern works, what to copy/change' },
    patterns_to_copy: { type: 'array', items: { type: 'string' } },
    file_refs: { type: 'array', items: { type: 'string' }, description: 'file:line references' },
  },
}

phase('Design')
const designs = await parallel([
  () => agent(CTX + '\n\nROLE: Make the STRONGEST honest case for approach A = PURE PHP OOXML surgery IN-PROCESS (no new container). Read ' + BASE + '/app/Libraries/DocxService.php to see the existing ZipArchive+DOM highlighting (domHighlightCell ~2069, xmlHighlightCellContaining ~2197, highlightRubricCells ~2235) as the reusable foundation. Detail EXACTLY how to inject real Word comments into the user .docx: edit word/document.xml (commentRangeStart/End as paragraph-level siblings + commentReference run + optional w:highlight), create word/comments.xml, register parts in word/_rels/document.xml.rels and [Content_Types].xml, optionally commentsExtended.xml/people.xml; run-splitting to anchor across multiple w:r/w:t; fuzzy Arabic/RTL matching (mb_*, normalize spaces/tatweel/diacritics); DOM validation guard before save; unique w:id; balanced ranges; htmlspecialchars(ENT_XML1). Be honest about effort/risk.', { label: 'annot:php-ooxml', phase: 'Design', schema: PROPOSAL_SCHEMA }),
  () => agent(CTX + '\n\nROLE: Make the STRONGEST honest case for approach B = a PYTHON SIDECAR microservice (new container in docker-compose) that receives the original .docx + annotations JSON and returns the annotated .docx. Evaluate real libraries for adding tracked/margin COMMENTS to an EXISTING docx while preserving formatting (e.g. python-docx + manual lxml on the comments part, python-docx-comments, or direct OOXML via python zipfile/lxml). Detail the HTTP contract diploma cron would call, deployment to the existing stack, and how formatting is preserved. Be honest about infra cost and whether any python lib really preserves the original file + adds valid comments Word opens cleanly.', { label: 'annot:python-sidecar', phase: 'Design', schema: PROPOSAL_SCHEMA }),
  () => agent(CTX + '\n\nROLE: Make the STRONGEST honest case for approach C = a NODE SIDECAR microservice (new container) using a JS library (e.g. docx, docxtemplater + comments module, or JSZip+xmldom direct OOXML) to add Word comments to the EXISTING essay .docx preserving formatting. Detail the HTTP contract, deployment, library maturity for COMMENTS-on-existing-docx specifically, and reliability that Word opens the result cleanly. Be honest about infra cost and library limitations (many JS docx libs only CREATE new docs, not annotate existing ones).', { label: 'annot:node-sidecar', phase: 'Design', schema: PROPOSAL_SCHEMA }),
  () => agent(CTX + '\n\nROLE: Recommend the best server-side approach to (1) EXTRACT the essay text from the uploaded .docx for the prompt, and (2) keep the text aligned with the original runs so anchor_text can be matched back for commenting. Read ' + BASE + '/app/Libraries/DocxService.php (PhpWord usage at top), ' + BASE + '/app/Services/Rag/SmartIngestionService.php, and how article_evaluator_v1 receives essay text (' + BASE + '/app/Libraries/PromptMaker/ArticleEvaluatorPromptMaker.php). Compare PhpWord text extraction vs unzip+strip <w:t> vs a lib. Recommend one and specify the payload field (e.g. essay_text) and how to also PERSIST the original .docx (since analyzeFile @unlink-deletes uploads).', { label: 'extraction', phase: 'Design', schema: PROPOSAL_SCHEMA }),
  () => agent(CTX + '\n\nROLE: Scout the EXACT reusable code patterns the plan must copy, with signatures + file:line. Read ' + BASE + '/app/Controllers/Cron/AiJobsRunner.php (the inquiry_report_evaluator_v1 branch that sets status=review + returns early; processOneJob structure ~460-570; the per-type naming ladder in processOneJob ~507 and rebuildFileForJob ~207); ' + BASE + '/app/Controllers/Students/AiToolsController.php approveEvaluation (~805-904), request() (~70-160), analyzeFile() (~112-238 - the multipart upload pattern to adapt, noting the @unlink to AVOID); ' + BASE + '/app/Services/AiTools/AiToolService.php createJob + buildMultiFileList (~316-344); the multi-file branches in ' + BASE + '/app/Controllers/Api/AiJobs.php (~134-141) and AiToolsController (~367-371); insertFileRowSmart / linkTaskFileSafe / makeTemplateDataForDocx. Output exact signatures + what to add/change for the new tool.', { label: 'scout:cron-approve', phase: 'Design', schema: SCOUT_SCHEMA }),
  () => agent(CTX + '\n\nROLE: Scout (a) a REAL ui_config_json example to copy (grep ai_job_types ui_config or read a PromptMaker/seeder that defines panel/form/result; or describe the structure ai_tool_factory.js consumes by reading ' + BASE + '/public/assets/js/students/sections/ai_tool_factory.js buildModal/collectPayload/applyState/renderResultModal); (b) the EXACT placements() code in ' + BASE + '/app/Controllers/Students/AiToolsController.php (~632-720) and isArticleEvaluatorAllowed (~1785-1800) so the plan can patch placements() to enforce the env allowlist (fetch userId + filter inside the foreach); (c) file storage: insertFileRowSmart, linkTaskFileSafe, the downloads/ folder convention, and how to persist + later serve the uploaded original .docx and the two output files. Output exact signatures + the precise patch points.', { label: 'scout:ui-access-files', phase: 'Design', schema: SCOUT_SCHEMA }),
])
const ok = designs.filter(Boolean)
log(ok.length + '/6 design reports returned.')

const annot = ok.slice(0, 3)
const extraction = ok[3]
const scouts = ok.slice(4)

const annotText = annot.map(p => p ? (
  '### ' + p.approach_name + ' (' + p.language_runtime + ')  [strength ' + p.recommendation_strength + '/5]\n' +
  'How: ' + p.how_it_works + '\nformatting_preservation=' + p.formatting_preservation + ' word_opens_cleanly=' + p.word_opens_cleanly + ' effort=' + p.effort + '\n' +
  'rtl: ' + p.rtl_arabic_handling + '\ninfra: ' + p.infra_cost + '\nlibs: ' + (p.libraries || []).join(', ') + '\nrisks: ' + (p.key_risks || []).join(' | ') + '\nnotes: ' + p.notes
) : '(missing)').join('\n\n')
const extractionText = extraction ? ('Extraction recommendation: ' + extraction.approach_name + ' (' + extraction.language_runtime + ')\n' + extraction.how_it_works + '\nrisks: ' + (extraction.key_risks || []).join(' | ') + '\nnotes: ' + extraction.notes) : '(missing)'
const scoutText = scouts.map(s => s ? ('## ' + s.area + '\n' + s.findings + '\nPatterns: ' + (s.patterns_to_copy || []).join('; ') + '\nRefs: ' + (s.file_refs || []).join(', ')) : '').join('\n\n========\n\n')

phase('Decide')
const decision = await agent('You are the technical lead. Choose the annotation-injection approach for formative_essay_evaluator_v1 and the text-extraction approach. Weigh formatting preservation (must return the user original file intact), reliability that Word opens the result with NO repair prompt, RTL/Arabic correctness, infra cost (a new container in the docker stack is a real cost), effort, and maintainability. Give a DECISIVE pick for annotation + extraction, with rationale, and state the infra impact (new container yes/no, new libs). Also flag the single biggest risk of your chosen approach and how to de-risk it (e.g. a PoC gate on a real Arabic RTL file).\n\n=== ANNOTATION PROPOSALS ===\n' + annotText + '\n\n=== EXTRACTION ===\n' + extractionText, { label: 'decide-tech', phase: 'Decide', effort: 'high' })

phase('Plan')
const planInstr = [
  'You are the lead engineer. Write the FINAL, authoritative, BUILDABLE implementation plan IN ARABIC (identifiers/code/paths in English) for the NEW tool formative_essay_evaluator_v1, per the fixed vision and the chosen tech below. This plan will be handed to a developer to implement directly.',
  'It must incorporate ALL corrections: distinct global/folder/naming (ignore the old tool), server-side text extraction (NOT docx-as-inlineData), persist the original uploaded .docx (do not delete), new DocxService report branch + new template (not F_FB_template), the chosen DocxAnnotationService approach, review/approve + multi-file in all required places, and env-only access ENFORCED in placements() too (not just run endpoints).',
  '',
  'Structure:',
  '0. ملخص + قرار التقنية المعتمد (حقن التعليقات + استخراج النص + الأثر على البنية التحتية: حاوية جديدة أم لا).',
  '1. المعمارية والتدفق الكامل (UI -> request/upload -> DB -> cron -> extraction -> Gemini -> report docx + annotated docx -> review -> approve -> done), مع sequence مرقّمة.',
  '2. مراحل التنفيذ المرتّبة (كل مرحلة: الهدف، الملفات، الخطوات الدقيقة، الأنماط المنسوخة بـ file:line، التحذيرات، ومعيار قبول قابل للاختبار). غطِّ: (أ) تعريف الأداة (raw SQL row + seeder + ui_config_json كامل)، (ب) system_prompt + عقد JSON، (ج) FormativeEssayEvaluatorPromptMaker، (د) endpoint رفع .docx + استخراج النص + تخزين الأصلي، (هـ) فرع cron + global/مجلد/تسمية مستقلّة + status=review، (و) تقرير DocxService (قالب جديد + فرع مخصّص)، (ز) DocxAnnotationService (بالتقنية المعتمدة) مع مطابقة المرسى وقائمة unmatched، (ح) approve + multi-file في كل المواضع، (ط) تقييد الوصول env-only في كل endpoint + داخل placements()، (ي) عرض النتيجة في ai_tool_factory.js.',
  '3. إن اختير سايدكار: تفاصيل خدمة الحاوية (docker-compose service, الصورة, العقد HTTP, كيف يستدعيها الكرون, النشر).',
  '4. مفاتيح env الجديدة المطلوبة (بما فيها مفتاح allowlist مخصّص) + ضبطها في .env/.env.example.',
  '5. اختبار E2E مفصّل (ملف عربي RTL حقيقي يُفتح في Word بلا رسالة إصلاح؛ >=15 تعليق؛ review->approve؛ rebuild-file؛ 403 لغير المصرّح).',
  '6. ترتيب البناء الموصى به لتقليل المخاطر (سلّم التقرير أولاً طرف-لطرف، ثم وحدة التعليقات مع بوابة PoC)، وقائمة تحقّق نهائية، وجدول مخاطر.',
  '',
  'كن دقيقاً وملموساً واذكر الأسماء/المسارات الحقيقية. هذه خطة تنفيذية لا مقالة.',
  '',
  '=== قرار التقنية ===\n' + decision,
  '\n=== أنماط الكود الواجب نسخها (scouts) ===\n' + scoutText,
  '\n=== تفاصيل مقاربة التعليقات المختارة (من المقترحات) ===\n' + annotText,
  '\n=== استخراج النص ===\n' + extractionText,
].join('\n')
const plan = await agent(planInstr, { label: 'synthesize-plan', phase: 'Plan', effort: 'high' })

phase('Critique')
const critique = await agent('Adversarially review this implementation plan for formative_essay_evaluator_v1. Find: missing steps a developer would get stuck on, any remaining reliance on the old tool global/folder, any place docx-as-inlineData sneaks back, whether the env-only access is truly enforced (including placements button visibility), whether the annotation approach is actually implementable as described, and whether the cron naming ladder is handled in BOTH places. List concrete gaps with the phase number. Be specific.\n\nPLAN:\n' + plan, { label: 'critique-plan', phase: 'Critique', effort: 'high' })

phase('Finalize')
const final = await agent('Produce the FINAL authoritative Arabic implementation plan for formative_essay_evaluator_v1. Start from the draft, fix every gap the critic raised, keep all real identifiers/paths/file:line refs and the chosen tech. Output ONLY the complete plan document (no preamble).\n\n=== DRAFT ===\n' + plan + '\n\n=== CRITIC GAPS (must fix) ===\n' + critique, { label: 'finalize-plan', phase: 'Finalize', effort: 'high' })

return { plan: final, tech_decision: decision }

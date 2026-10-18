<?php
declare(strict_types=1);

namespace App\Libraries;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Shared\Converter;

/**
 * FormativeEvaluatorDocx
 * ------------------------------------------------------------------
 * خدمة معزولة لأداة formative_essay_evaluator_v1:
 *   - extractEssayRuns(): استخراج نصّ المقالة + خريطة runs من .docx خادمياً (لا PhpWord reader اللوسي).
 *   - buildFormativeReport(): توليد تقرير DOCX للمجالات الأربعة (Gibbs) مباشرةً بـ PhpWord (بلا قالب).
 *   - annotateDocxWithComments(): حقن تعليقات Word حقيقية على نسخة من الأصل (جراحة OOXML) — مع حارس
 *     إلغاء-إلى-الأصل عند أي فشل صلاحية (لا نشحن ملفاً تالفاً أبداً).
 *
 * لا تمرّ أيّ من هذه الدوال عبر قارئ/كاتب PhpWord على ملف الطالب الأصلي (يفقد التنسيق).
 * PhpWord يُستخدم فقط لتوليد التقرير المنفصل من الصفر.
 */
class FormativeEvaluatorDocx
{
    /** namespace الأساسي لـ WordprocessingML. */
    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** اسم/أحرف كاتب التعليقات الظاهر في Word (قابل للتعديل عبر env COMMENT_AUTHOR). */
    private const COMMENT_AUTHOR   = 'عبدالرزاق الخطيب';
    private const COMMENT_INITIALS = 'AK';

    /** قيم ثابتة في ترويسة تقرير التغذية الراجعة (حسب طلب الإدارة). */
    private const DEFAULT_SUPERVISOR = 'عبدالرزاق الخطيب';
    private const DEFAULT_DATE       = '22/6/2026';

    /** مسار قالب التقرير (نسبي تحت FCPATH/public). */
    private const REPORT_TEMPLATE = 'templates/formative_evaluation_report.docx';

    /** حدّ أمان أعلى لعدد التعليقات (لا يُقصّ الحالات الواقعية؛ جميع التعليقات المطابِقة تُضاف لملف Word). */
    private const MAX_COMMENTS = 40;

    // ==================================================================
    //  1) استخراج النصّ + خريطة الـ runs
    // ==================================================================

    /**
     * يستخرج نصّ المقالة وخريطة الـ runs من word/document.xml فقط.
     * @return array{0:string,1:array} [essay_text, run_map]
     *   run_map: عناصر { char_start, char_len, paragraph_index, run_index } (تشخيصية).
     */
    public function extractEssayRuns(string $absPath): array
    {
        if (!is_file($absPath)) {
            return ['', []];
        }

        $zip = new \ZipArchive();
        if ($zip->open($absPath) !== true) {
            return ['', []];
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false || $xml === '') {
            return ['', []];
        }

        $prev = libxml_use_internal_errors(true);
        $dom  = new \DOMDocument();
        $loaded = $dom->loadXML($xml, LIBXML_NOENT | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$loaded) {
            return ['', []];
        }

        $paragraphs = $dom->getElementsByTagNameNS(self::W_NS, 'p');
        $textParts  = [];
        $runMap     = [];
        $cursor     = 0;     // إزاحة الحرف داخل essay_text المُجمّع
        $pIndex     = -1;

        foreach ($paragraphs as $p) {
            $pIndex++;
            // افصل الفقرات بسطر جديد (عدا الأولى)
            if ($pIndex > 0) {
                $textParts[] = "\n";
                $cursor += 1;
            }

            // اجمع <w:t> داخل runs هذه الفقرة بالترتيب
            $texts = $p->getElementsByTagNameNS(self::W_NS, 't');
            $rIndex = -1;
            foreach ($texts as $t) {
                $rIndex++;
                $chunk = $t->textContent;
                $len   = mb_strlen($chunk, 'UTF-8');
                if ($len === 0) {
                    continue;
                }
                $runMap[] = [
                    'char_start'      => $cursor,
                    'char_len'        => $len,
                    'paragraph_index' => $pIndex,
                    'run_index'       => $rIndex,
                ];
                $textParts[] = $chunk;
                $cursor += $len;
            }
        }

        $essayText = trim(implode('', $textParts));
        return [$essayText, $runMap];
    }

    // ==================================================================
    //  2) بناء تقرير التغذية الراجعة (PhpWord مباشرةً، بلا قالب)
    // ==================================================================

    /** المجالات الأربعة: مفتاح => عنوان عربي. */
    private const DOMAINS = [
        'knowledge_understanding_application' => 'المعرفة والفهم والتطبيق',
        'cognitive_skills'                   => 'المهارات المعرفية (التفكير الناقد والتأمّل)',
        'professional_skills'                => 'المهارات المهنية وأصول التدريس',
        'presentation'                       => 'العرض والتقديم',
    ];

    /** مراحل Gibbs الستّ: مفتاح => عنوان عربي. */
    private const STAGES = [
        'what_happened' => 'الوصف: ما الذي حدث',
        'feelings'      => 'المشاعر',
        'evaluation'    => 'التقييم',
        'analysis'      => 'التحليل',
        'conclusion'    => 'الاستنتاج',
        'action_plan'   => 'خطة العمل',
    ];

    /**
     * يولّد تقرير DOCX من بنية report.
     * @param array $report  report من الـ JSON (4 مجالات + overall_suggested_actions)
     * @param array $meta    ['student_name'=>..,'teacher_specialty'=>..,'date'=>..]
     * @param string $targetRel مسار نسبي تحت FCPATH (مثل downloads/formative_evaluations/xx.docx)
     * @return array{rel_path:string,bytes:int}
     */
    public function buildFormativeReport(array $report, array $meta, string $targetRel): array
    {
        $tplAbs    = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . self::REPORT_TEMPLATE;
        $targetAbs = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . ltrim($targetRel, '/\\');
        $dir = dirname($targetAbs);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_file($tplAbs)) {
            throw new \RuntimeException('قالب التقرير غير موجود: ' . self::REPORT_TEMPLATE);
        }

        $tp = new \PhpOffice\PhpWord\TemplateProcessor($tplAbs);

        // ── المجالات الأربعة → d_str{N}/d_imp{N} ──
        $domains = [
            'knowledge_understanding_application' => 1,
            'cognitive_skills'                   => 2,
            'professional_skills'                => 3,
            'presentation'                       => 4,
        ];
        foreach ($domains as $key => $i) {
            $d = (array) ($report[$key] ?? []);
            $tp->setValue('d_str' . $i, $this->tplText($d['strengths'] ?? ''));
            $tp->setValue('d_imp' . $i, $this->tplText($d['areas_for_improvement'] ?? ''));
        }

        // ── الإجراءات الإجماليّة المُقترحة ──
        $tp->setValue('overall_actions', $this->tplText($report['overall_suggested_actions'] ?? ''));

        // ── ترويسة التقرير: قيم المستخدم + ثوابت الإدارة ──
        $studentName = (string) ($meta['student_name'] ?? ($report['student_name'] ?? ''));
        $studentId   = (string) ($meta['student_id'] ?? '');
        $supervisor  = trim((string) ($meta['supervisor_name'] ?? '')) ?: self::DEFAULT_SUPERVISOR;
        $date        = trim((string) ($meta['date'] ?? '')) ?: self::DEFAULT_DATE;
        $tp->setValue('student_name', $this->tplText($studentName));
        $tp->setValue('student_id', $this->tplText($studentId));
        $tp->setValue('supervisor_name', $this->tplText($supervisor));
        $tp->setValue('date', $this->tplText($date));

        $tp->saveAs($targetAbs);

        return [
            'rel_path' => str_replace('\\', '/', $targetRel),
            'bytes'    => is_file($targetAbs) ? (int) filesize($targetAbs) : 0,
        ];
    }

    /** يحوّل قيمة الحقل إلى نص آمن لـ TemplateProcessor (XML صالح + دمج الأسطر). */
    private function tplText($v): string
    {
        if (is_array($v)) {
            $v = implode('؛ ', array_filter(array_map(static fn($x) => is_scalar($x) ? trim((string) $x) : '', $v)));
        }
        $v = trim((string) $v);
        if ($v === '') {
            return '';
        }
        $v = str_replace(['&', '<', '>'], ['＆', '﹤', '﹥'], $v);
        $v = (string) preg_replace('/\s*\R\s*/u', '؛ ', $v);
        return $v;
    }

    // ==================================================================
    //  2.5) تحويل DOCX → PDF عبر LibreOffice headless
    // ==================================================================

    /**
     * يحوّل ملف DOCX إلى PDF عبر LibreOffice headless.
     * @param string $relDocx     مسار نسبي تحت FCPATH.
     * @param bool   $withComments true ⇒ تصدير تعليقات Word في هامش الـ PDF (للمقالة المُعلَّقة).
     * @return string|null المسار النسبي للـ PDF أو null عند الفشل.
     */
    public function convertDocxToPdf(string $relDocx, bool $withComments = false): ?string
    {
        $absDocx = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . ltrim($relDocx, '/\\');
        if (!is_file($absDocx)) {
            return null;
        }
        $soffice = $this->findSoffice();
        if ($soffice === null) {
            log_message('error', 'convertDocxToPdf: LibreOffice (soffice) غير متوفّر');
            return null;
        }

        $dir      = dirname($absDocx);
        $tmp      = rtrim(sys_get_temp_dir(), '/\\');
        $profile  = $tmp . '/lo_profile_' . getmypid() . '_' . substr(md5($absDocx), 0, 6);
        $filter   = $withComments
            ? 'pdf:writer_pdf_Export:{"ExportNotesInMargin":{"type":"boolean","value":"true"}}'
            : 'pdf';
        $userInst = escapeshellarg('-env:UserInstallation=file://' . $profile);

        $cmd = sprintf(
            'export HOME=%s; %s --headless --norestore --nolockcheck %s --convert-to %s --outdir %s %s 2>&1',
            escapeshellarg($tmp),
            escapeshellarg($soffice),
            $userInst,
            escapeshellarg($filter),
            escapeshellarg($dir),
            escapeshellarg($absDocx)
        );

        $out  = [];
        $code = 0;
        @exec($cmd, $out, $code);

        $pdfAbs = preg_replace('/\.docx$/i', '.pdf', $absDocx);
        if (!is_file($pdfAbs)) {
            log_message('error', 'convertDocxToPdf فشل (code=' . $code . '): ' . implode(' | ', array_slice($out, -3)));
            return null;
        }
        return preg_replace('/\.docx$/i', '.pdf', str_replace('\\', '/', ltrim($relDocx, '/\\')));
    }

    /** يكتشف مسار LibreOffice (soffice). */
    private function findSoffice(): ?string
    {
        foreach (['/usr/bin/soffice', '/usr/bin/libreoffice', '/opt/libreoffice/program/soffice'] as $p) {
            if (is_file($p) && is_executable($p)) {
                return $p;
            }
        }
        $which = @shell_exec('command -v soffice 2>/dev/null') ?: @shell_exec('command -v libreoffice 2>/dev/null');
        $which = trim((string) $which);
        return $which !== '' ? $which : null;
    }

    // ==================================================================
    //  3) حقن تعليقات Word على نسخة من الأصل (جراحة OOXML)
    // ==================================================================

    /**
     * يحقن تعليقات Word حقيقية على نسخة من المقالة الأصلية.
     * مرساة على مستوى الفقرة (يضع نطاق التعليق حول الفقرة المطابِقة، والاقتباس مُضمَّن في نصّ التعليق)
     * — أمتن من شقّ الـ runs، ويُفتح في Word بلا رسالة إصلاح.
     * حارس صلب: عند أيّ فشل صلاحية يُعيد نسخة الأصل سليمة (aborted=true) ولا يشحن ملفاً تالفاً.
     *
     * @return array{rel_path:string,bytes:int,applied:int,unmatched:array,aborted:bool}
     */
    public function annotateDocxWithComments(string $srcAbs, array $annotations, string $targetRel): array
    {
        $targetAbs = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . ltrim($targetRel, '/\\');
        $dir = dirname($targetAbs);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $result = ['rel_path' => str_replace('\\', '/', $targetRel), 'bytes' => 0, 'applied' => 0, 'applied_annotations' => [], 'unmatched' => [], 'aborted' => false];

        // الضمان الأدنى: نسخة سليمة من الأصل
        if (!is_file($srcAbs) || !@copy($srcAbs, $targetAbs)) {
            $result['aborted'] = true;
            $result['rel_path'] = '';
            return $result;
        }
        $result['bytes'] = (int) @filesize($targetAbs);

        if (empty($annotations)) {
            return $result; // نسخة سليمة بلا تعليقات
        }

        // حدّ أقصى أمان لعدد التعليقات (لا يُقصّ الحالات الواقعية)
        if (count($annotations) > self::MAX_COMMENTS) {
            $annotations = array_slice(array_values($annotations), 0, self::MAX_COMMENTS);
        }

        $revertClean = function (array $r) use ($srcAbs, $targetAbs): array {
            @copy($srcAbs, $targetAbs);
            $r['aborted']             = true;
            $r['applied']             = 0;
            $r['applied_annotations'] = [];
            $r['bytes']               = (int) @filesize($targetAbs);
            return $r;
        };

        try {
            $zip = new \ZipArchive();
            if ($zip->open($targetAbs) !== true) {
                return $revertClean($result);
            }
            $docXml = $zip->getFromName('word/document.xml');
            if ($docXml === false || $docXml === '') {
                $zip->close();
                return $revertClean($result);
            }

            $prev = libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = true;
            $loaded = $dom->loadXML($docXml, LIBXML_NONET);
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            if (!$loaded) {
                $zip->close();
                return $revertClean($result);
            }

            $W = self::W_NS;
            $paras = $dom->getElementsByTagNameNS($W, 'p');

            // نصّ كامل مُطبّع + خريطة إزاحة لكل فقرة — لمطابقة المراسي حتى لو عبرت عدّة فقرات قصيرة
            // (بعض المستندات تُجزّئ الجملة الواحدة على عدّة <w:p>، فالمطابقة لكل فقرة على حدة تفشل).
            $fullText  = '';
            $offsetMap = []; // ['start'=>int,'end'=>int,'node'=>DOMElement]
            foreach ($paras as $p) {
                $t = $this->normalizeArabic($p->textContent);
                if ($t === '') {
                    continue;
                }
                $start = mb_strlen($fullText, 'UTF-8');
                $fullText .= $t;
                $offsetMap[] = ['start' => $start, 'end' => mb_strlen($fullText, 'UTF-8'), 'node' => $p];
                $fullText .= ' '; // فاصل يطابق \n المُحوّل إلى مسافة داخل المرساة بعد التطبيع
            }
            $fullLen = mb_strlen($fullText, 'UTF-8');
            $nodeAtOffset = static function (int $off) use ($offsetMap) {
                foreach ($offsetMap as $e) {
                    if ($off >= $e['start'] && $off < $e['end']) {
                        return $e['node'];
                    }
                }
                return null;
            };

            $comments = [];
            $cid = 0;
            foreach ($annotations as $ann) {
                $anchor  = trim((string) ($ann['anchor_text'] ?? ''));
                $cText   = trim((string) ($ann['comment'] ?? ''));
                if ($anchor === '' || $cText === '') {
                    $result['unmatched'][] = ['anchor_text' => $anchor, 'reason' => 'empty'];
                    continue;
                }
                $normAnchor = $this->normalizeArabic($anchor);
                if ($normAnchor === '') {
                    $result['unmatched'][] = ['anchor_text' => $anchor, 'reason' => 'empty_norm'];
                    continue;
                }
                // مطابقة بالنص الكامل (تستوعب المراسي العابرة لعدّة فقرات قصيرة)
                $pos      = $fullText !== '' ? mb_strpos($fullText, $normAnchor, 0, 'UTF-8') : false;
                $matchLen = mb_strlen($normAnchor, 'UTF-8');
                if ($pos === false) {
                    // احتياط: طابِق ببادئة المرساة (أوّل 30 محرفاً) لاستيعاب اختلاف بسيط في ذيل الاقتباس
                    $prefix = mb_substr($normAnchor, 0, 30, 'UTF-8');
                    if (mb_strlen($prefix, 'UTF-8') >= 10) {
                        $pos      = mb_strpos($fullText, $prefix, 0, 'UTF-8');
                        $matchLen = mb_strlen($prefix, 'UTF-8');
                    }
                }
                if ($pos === false) {
                    $result['unmatched'][] = ['anchor_text' => $anchor, 'reason' => 'not_found'];
                    continue;
                }

                $startNode = $nodeAtOffset($pos);
                $endOff    = min($fullLen - 1, $pos + max(1, $matchLen) - 1);
                $endNode   = $nodeAtOffset($endOff) ?? $startNode;
                if ($startNode === null) {
                    $result['unmatched'][] = ['anchor_text' => $anchor, 'reason' => 'no_node'];
                    continue;
                }

                $cid++;
                $idStr = (string) $cid;

                // commentRangeStart بعد pPr في فقرة البداية (أو كأوّل عنصر فيها)
                $crs = $dom->createElementNS($W, 'w:commentRangeStart');
                $crs->setAttributeNS($W, 'w:id', $idStr);
                $pPr = null;
                foreach ($startNode->childNodes as $ch) {
                    if ($ch->nodeType === XML_ELEMENT_NODE && $ch->localName === 'pPr') {
                        $pPr = $ch;
                        break;
                    }
                }
                if ($pPr !== null && $pPr->nextSibling !== null) {
                    $startNode->insertBefore($crs, $pPr->nextSibling);
                } elseif ($pPr !== null) {
                    $startNode->appendChild($crs);
                } elseif ($startNode->firstChild !== null) {
                    $startNode->insertBefore($crs, $startNode->firstChild);
                } else {
                    $startNode->appendChild($crs);
                }

                // commentRangeEnd + run المرجع في نهاية فقرة النهاية (قد تكون نفس فقرة البداية)
                $cre = $dom->createElementNS($W, 'w:commentRangeEnd');
                $cre->setAttributeNS($W, 'w:id', $idStr);
                $endNode->appendChild($cre);

                $refRun = $dom->createElementNS($W, 'w:r');
                $rPr    = $dom->createElementNS($W, 'w:rPr');
                $rStyle = $dom->createElementNS($W, 'w:rStyle');
                $rStyle->setAttributeNS($W, 'w:val', 'CommentReference');
                $rPr->appendChild($rStyle);
                $refRun->appendChild($rPr);
                $refEl = $dom->createElementNS($W, 'w:commentReference');
                $refEl->setAttributeNS($W, 'w:id', $idStr);
                $refRun->appendChild($refEl);
                $endNode->appendChild($refRun);

                $comments[$idStr] = ['text' => $cText, 'anchor' => $anchor];
                $result['applied']++;
                $result['applied_annotations'][] = $ann; // التعليقات المُطبَّقة فعلاً في ملف Word
            }

            if ($result['applied'] === 0) {
                // لا مطابقات: النسخة سليمة (الأصل) بلا تعليقات — ليست حالة فشل
                $zip->close();
                return $result;
            }

            // حفظ document.xml المعدّل + إعادة تحليل للتحقق
            $newDocXml = $dom->saveXML();
            if (!$this->isValidXml($newDocXml)) {
                $zip->close();
                return $revertClean($result);
            }

            $commentsXml = $this->buildCommentsXml($comments);
            if (!$this->isValidXml($commentsXml)) {
                $zip->close();
                return $revertClean($result);
            }

            $rels = $zip->getFromName('word/_rels/document.xml.rels');
            $relsNew = $this->registerCommentsRel($rels);
            $ct = $zip->getFromName('[Content_Types].xml');
            $ctNew = $this->registerCommentsContentType($ct);
            if ($relsNew === null || $ctNew === null) {
                $zip->close();
                return $revertClean($result);
            }

            $zip->deleteName('word/document.xml');
            $zip->addFromString('word/document.xml', $newDocXml);
            $zip->addFromString('word/comments.xml', $commentsXml);
            $zip->deleteName('word/_rels/document.xml.rels');
            $zip->addFromString('word/_rels/document.xml.rels', $relsNew);
            $zip->deleteName('[Content_Types].xml');
            $zip->addFromString('[Content_Types].xml', $ctNew);
            $zip->close();

            $result['bytes'] = (int) @filesize($targetAbs);
            return $result;
        } catch (\Throwable $e) {
            log_message('error', 'annotateDocxWithComments failed: ' . $e->getMessage());
            return $revertClean($result);
        }
    }

    /** تحقّق صلاحية XML بإعادة التحليل. */
    private function isValidXml(string $xml): bool
    {
        if ($xml === '') {
            return false;
        }
        $prev = libxml_use_internal_errors(true);
        $d = new \DOMDocument();
        $ok = $d->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return (bool) $ok;
    }

    /** تطبيع عربي للمطابقة فقط: إزالة tatweel/harakat، توحيد المسافات، NBSP/RLM/LRM → مسافة. */
    private function normalizeArabic(string $s): string
    {
        $s = (string) preg_replace('/\x{0640}/u', '', $s);                 // tatweel
        $s = (string) preg_replace('/[\x{064B}-\x{0652}\x{0670}]/u', '', $s); // harakat + dagger alif
        $s = str_replace(["\xC2\xA0", "\xE2\x80\x8F", "\xE2\x80\x8E"], ' ', $s); // nbsp, rlm, lrm
        $s = (string) preg_replace('/\s+/u', ' ', $s);
        return trim($s);
    }

    /** يبني word/comments.xml (الاقتباس مُضمَّن في نصّ التعليق، RTL). */
    private function buildCommentsXml(array $comments): string
    {
        $W = self::W_NS;
        $date = date('c');
        $author   = (string) (getenv('COMMENT_AUTHOR') ?: self::COMMENT_AUTHOR);
        $initials = (string) (getenv('COMMENT_INITIALS') ?: self::COMMENT_INITIALS);
        $authorEsc   = htmlspecialchars($author, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $initialsEsc = htmlspecialchars($initials, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $out  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $out .= '<w:comments xmlns:w="' . $W . '">';
        foreach ($comments as $id => $c) {
            // فقرة واحدة مترابطة (دون اقتباس أيّ جزء من الفقرة): ادمج أيّ أسطر/مسافات زائدة في مسافة واحدة.
            $body = trim((string) $c['text']);
            $body = (string) preg_replace('/\s*\R\s*/u', ' ', $body);
            $body = (string) preg_replace('/[ \t]{2,}/u', ' ', $body);
            $esc  = htmlspecialchars($body, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $out .= '<w:comment w:id="' . $id . '" w:author="' . $authorEsc . '" w:date="' . $date . '" w:initials="' . $initialsEsc . '">';
            $out .= '<w:p><w:pPr><w:bidi/></w:pPr><w:r><w:rPr><w:rtl/></w:rPr><w:t xml:space="preserve">' . $esc . '</w:t></w:r></w:p>';
            $out .= '</w:comment>';
        }
        $out .= '</w:comments>';
        return $out;
    }

    /** يسجّل علاقة comments.xml في word/_rels/document.xml.rels. */
    private function registerCommentsRel(?string $rels): ?string
    {
        if ($rels === null || $rels === '') {
            return null;
        }
        if (strpos($rels, '/comments') !== false && strpos($rels, 'comments.xml') !== false) {
            return $rels; // موجودة سلفاً
        }
        preg_match_all('/Id="rId(\d+)"/', $rels, $m);
        $max = 0;
        foreach ($m[1] as $n) {
            $max = max($max, (int) $n);
        }
        $newId = 'rId' . ($max + 1);
        $rel = '<Relationship Id="' . $newId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml"/>';
        $res = preg_replace('#</Relationships>#', $rel . '</Relationships>', $rels, 1);
        return $res ?: null;
    }

    /** يسجّل نوع المحتوى لـ comments.xml في [Content_Types].xml. */
    private function registerCommentsContentType(?string $ct): ?string
    {
        if ($ct === null || $ct === '') {
            return null;
        }
        if (strpos($ct, 'wordprocessingml.comments+xml') !== false) {
            return $ct;
        }
        $override = '<Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>';
        $res = preg_replace('#</Types>#', $override . '</Types>', $ct, 1);
        return $res ?: null;
    }
}

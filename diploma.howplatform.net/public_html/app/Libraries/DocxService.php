<?php
declare(strict_types=1);

namespace App\Libraries;

use PhpOffice\PhpWord\TemplateProcessor;

class DocxService
{
    /**
     * يبني ملف DOCX من قالب موجود ويعيد معلومات الحفظ.
     *
     * @param string $templateRel  مسار القالب النسبي من FCPATH (مثال: 'templates/lesson_plan_template.docx')
     * @param array  $data         JSON الناتج من الـ AI (كائن مصفوفة)
     * @param string $targetRel    مسار الحفظ النسبي (مثال: 'downloads/lesson_plans/file.docx')
     * @param array  $filenameMeta قيم للعرض فقط (اختياري)
     * @param string|null $typeKey نوع الأداة (matrix_analysis_v1, lesson_plan_v1, etc.) لتحديد المعالجة
     * @return array{rel_path:string,bytes:int}
     */
    public function buildFromTemplate(string $templateRel, array $data, string $targetRel, array $filenameMeta = [], ?string $typeKey = null): array
    {
        $templateAbs = rtrim(FCPATH, '/') . '/' . ltrim($templateRel, '/');
        if (!is_file($templateAbs)) {
            throw new \RuntimeException('Template not found: ' . $templateAbs);
        }

        $targetAbs = rtrim(FCPATH, '/') . '/' . ltrim($targetRel, '/');
        $targetDir = dirname($targetAbs);
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        $tp = new TemplateProcessor($templateAbs);
        $templateVariables = method_exists($tp, 'getVariables') ? $tp->getVariables() : [];
        
        // تنظيف المتغيرات من الرموز والمسافات الإضافية
        $cleanedTemplateVariables = [];
        $variableMapping = []; // خريطة من المتغير المنظف إلى الأصلي
        foreach ($templateVariables as $var) {
            // تنظيف المسافات من البداية والنهاية، ثم تنظيف $ من النهاية
            $cleaned = trim($var);
            $cleaned = rtrim($cleaned, '$');
            $cleanedTemplateVariables[] = $cleaned;
            if ($cleaned !== $var) {
                $variableMapping[$cleaned] = $var;
            }
        }
        
        // تسجيل المتغيرات المكتشفة للتصحيح
        if ($typeKey === 'article_evaluator_v1') {
            log_message('debug', 'Template variables found: ' . implode(', ', $templateVariables));
            log_message('debug', 'Cleaned template variables: ' . implode(', ', $cleanedTemplateVariables));
        }

        // تحديد الحقول المطلوبة حسب نوع الأداة
        $isMatrixAnalysis = ($typeKey === 'matrix_analysis_v1');
        $isAnalyticalArticle = ($typeKey === 'analytical_article_v1');
        $isCaseStudy = ($typeKey === 'case_study_v1');
        $isCaseIntegration = ($typeKey === 'case_integration_v1');
        $isArticleEvaluator = ($typeKey === 'article_evaluator_v1');
        $isEvalIntegration = ($typeKey === 'evaluation_integration_v1');
        $isAssessmentPosition = ($typeKey === 'assessment_position_v1');
        $isQuestionDevelop    = ($typeKey === 'question_develop_v1');
        $isInquiryResearch   = ($typeKey === 'inquiry_research_v1');
        $isInquiryTools      = in_array($typeKey, ['inquiry_data_tools_v1', 'inquiry_academic_writer_v1', 'inquiry_templates_v1']);
        $isBilingualLessonPlan = ($typeKey === 'bilingual_lesson_plan_v1');
        $isStakeholderIntegration = ($typeKey === 'stakeholder_integration_v1');
        $isFormativeReflectiveEssay = ($typeKey === 'formative_reflective_essay_v1' || $typeKey === 'rewritten_reflective_essay_v1');

        // ✅ لأداة التخطيط ثنائية اللغة: الحقول الخاصة بها
        if ($isBilingualLessonPlan) {
            $blpKeys = [
                'subject', 'teacher_gender', 'student_gender', 'grade_level', 
                'unit_title', 'lesson_title', 'classes_count', 'previous_learning', 
                'main_outcomes', 'general_description'
            ];
            
            // الحقول النصية العادية
            foreach ($blpKeys as $k) {
                if (in_array($k, $templateVariables, true) || in_array($k, $cleanedTemplateVariables, true)) {
                    $val = $this->toText($data[$k] ?? '');
                    $this->setValueSafely($tp, $k, $val, $templateVariables, $variableMapping);
                }
            }

            // الاستراتيجيات (تحويل المصفوفة إلى قائمة منسدلة متعددة الأسطر)
            foreach (['teaching_strategies', 'evaluation_strategies'] as $k) {
                if (in_array($k, $templateVariables, true) || in_array($k, $cleanedTemplateVariables, true)) {
                    $val = $this->listToMultiline($data[$k] ?? '');
                    // استبدال أسطر PHP بأسطر Word
                    $val = str_replace(["\r\n", "\n\r", "\n", "\r"], '<w:br/>', $val);
                    $this->setValueSafely($tp, $k, $val, $templateVariables, $variableMapping);
                }
            }

            // جدول الحصص للمراحل (من 1 إلى 4) - نستخدم التعويض المباشر لكل خلية
            for ($i = 1; $i <= 4; $i++) {
                $stageKeys = ["time_$i", "learner_action_$i", "teacher_action_$i"];
                foreach ($stageKeys as $k) {
                    if (in_array($k, $templateVariables, true) || in_array($k, $cleanedTemplateVariables, true)) {
                        $val = $this->toText($data[$k] ?? '');
                        $this->setValueSafely($tp, $k, $val, $templateVariables, $variableMapping);
                    }
                }
            }

            $tp->saveAs($targetAbs);
            
            if (class_exists('ZipArchive')) {
                $this->cleanUnreplacedVariables($targetAbs);
            }

            if (!is_file($targetAbs)) {
                throw new \RuntimeException('Failed saving: ' . $targetAbs);
            }
            
            return [
                'rel_path' => ltrim($targetRel, '/'),
                'bytes'    => filesize($targetAbs) ?: 0,
            ];
        }

        // (Removed targetRel override to use the passed parameter)
        // ✅ لمقيم المقالات: معالجة خاصة
        if ($isArticleEvaluator) {
            try {
                // التحقق من المتغيرات المطلوبة في Template
                $requiredVariables = [
                    'teacher_name',
                    'student_number', 
                    'teacher_supervisor',
                    'general_guidance'
                ];
                
                foreach ($requiredVariables as $var) {
                    if (!in_array($var, $templateVariables, true) && !in_array($var, $cleanedTemplateVariables, true)) {
                        log_message('warning', "Template variable '{$var}' not found in template for article_evaluator. It may not be replaced correctly.");
                    }
                }
                
                // الحقول النصية
                // استبدال المتغيرات مع محاولة التعامل مع المسافات في الأسماء
                $this->setValueSafely($tp, 'teacher_name', $this->toText($data['teacher_name'] ?? ''), $templateVariables, $variableMapping);
                $this->setValueSafely($tp, 'student_number', $this->toText($data['student_number'] ?? ''), $templateVariables, $variableMapping);
                $this->setValueSafely($tp, 'teacher_supervisor', $this->toText($data['teacher_supervisor'] ?? ''), $templateVariables, $variableMapping);
                $this->setValueSafely($tp, 'general_guidance', $this->toText($data['general_guidance'] ?? ''), $templateVariables, $variableMapping);
                
                // إضافة formatting_compliance إذا كان موجوداً في Template
                if (in_array('formatting_compliance', $templateVariables, true) || in_array('formatting_compliance', $cleanedTemplateVariables, true)) {
                    $tp->setValue('formatting_compliance', $this->toText($data['formatting_compliance'] ?? ''));
                }
                
                // Loops - مع معالجة أخطاء
                $this->processLoop($tp, 'knowledge_strength_item', $data['knowledge_strengths'] ?? [], $templateVariables);
                $this->processLoop($tp, 'knowledge_improvement_item', $data['knowledge_improvements'] ?? [], $templateVariables);
                
                $this->processLoop($tp, 'cognitive_strength_item', $data['cognitive_strengths'] ?? [], $templateVariables);
                $this->processLoop($tp, 'cognitive_improvement_item', $data['cognitive_improvements'] ?? [], $templateVariables);
                
                $this->processLoop($tp, 'professional_strength_item', $data['professional_strengths'] ?? [], $templateVariables);
                $this->processLoop($tp, 'professional_improvement_item', $data['professional_improvements'] ?? [], $templateVariables);
                
                $this->processLoop($tp, 'presentation_strength_item', $data['presentation_strengths'] ?? [], $templateVariables);
                $this->processLoop($tp, 'presentation_improvement_item', $data['presentation_improvements'] ?? [], $templateVariables);
                
                $tp->saveAs($targetAbs);
                
                // تنظيف أي متغيرات template غير مستبدلة بعد الحفظ
                // يتم التنظيف بعد الحفظ مباشرة للتأكد من أن الملف جاهز
                if (class_exists('ZipArchive')) {
                    $this->cleanUnreplacedVariables($targetAbs);
                }
            } catch (\Exception $e) {
                log_message('error', "Failed to build DOCX for article_evaluator: " . $e->getMessage());
                log_message('error', "Stack trace: " . $e->getTraceAsString());
                throw new \RuntimeException('Failed to build DOCX file: ' . $e->getMessage(), 0, $e);
            }
            
            if (!is_file($targetAbs)) {
                throw new \RuntimeException('Failed saving: ' . $targetAbs);
            }
            
            return ['rel_path' => $targetRel, 'bytes' => filesize($targetAbs)];
        }
        
        // ✅ لدراسة الحالة: الحقول الخاصة بها فقط
        if ($isCaseStudy) {
            // الحقول العادية (Single Values)
            $singleKeys = [
                'teacher_name',
                'grade_level',
                'case_title',
                'analysis_context',
                'analysis_main_dilemma',
                'analysis_case_identification',
                'analysis_case_description',
                'analysis_case_evaluation',
            ];
            
            // استخدام الحقول العادية
            foreach ($singleKeys as $k) {
                if (in_array($k, $templateVariables, true)) {
                    $tp->setValue($k, $this->toText($data[$k] ?? ''));
                }
            }
            
            // معالجة الشخصيات (analysis_characters) - Loop
            if (in_array('character_item', $templateVariables, true)) {
                $characters = $data['analysis_characters'] ?? [];
                $characters = $this->arrify($characters);
                
                if (count($characters) > 0) {
                    $characterRows = [];
                    foreach ($characters as $character) {
                        $characterRows[] = ['character_item' => $this->toText($character)];
                    }
                    $tp->cloneRowAndSetValues('character_item', $characterRows);
                } else {
                    // صف واحد فارغ
                    $tp->cloneRow('character_item', 1);
                    $tp->setValue('character_item#1', '');
                }
            }
            
            // معالجة نقاط القوة (analysis_strengths) - Loop
            if (in_array('strength_item', $templateVariables, true)) {
                $strengths = $data['analysis_strengths'] ?? [];
                $strengths = $this->arrify($strengths);
                
                if (count($strengths) > 0) {
                    $strengthRows = [];
                    foreach ($strengths as $strength) {
                        $strengthRows[] = ['strength_item' => $this->toText($strength)];
                    }
                    $tp->cloneRowAndSetValues('strength_item', $strengthRows);
                } else {
                    // صف واحد فارغ
                    $tp->cloneRow('strength_item', 1);
                    $tp->setValue('strength_item#1', '');
                }
            }
            
            // معالجة جوانب التطوير (analysis_improvement_aspects) - Loop
            if (in_array('improvement_aspect_item', $templateVariables, true)) {
                $improvements = $data['analysis_improvement_aspects'] ?? [];
                $improvements = $this->arrify($improvements);
                
                if (count($improvements) > 0) {
                    $improvementRows = [];
                    foreach ($improvements as $improvement) {
                        $improvementRows[] = ['improvement_aspect_item' => $this->toText($improvement)];
                    }
                    $tp->cloneRowAndSetValues('improvement_aspect_item', $improvementRows);
                } else {
                    // صف واحد فارغ
                    $tp->cloneRow('improvement_aspect_item', 1);
                    $tp->setValue('improvement_aspect_item#1', '');
                }
            }
            
            // معالجة البدائل المقترحة (analysis_proposed_alternatives) - Loop
            if (in_array('alternative_item', $templateVariables, true)) {
                $alternatives = $data['analysis_proposed_alternatives'] ?? [];
                $alternatives = $this->arrify($alternatives);
                
                if (count($alternatives) > 0) {
                    $alternativeRows = [];
                    foreach ($alternatives as $alternative) {
                        $alternativeRows[] = ['alternative_item' => $this->toText($alternative)];
                    }
                    $tp->cloneRowAndSetValues('alternative_item', $alternativeRows);
                } else {
                    // صف واحد فارغ
                    $tp->cloneRow('alternative_item', 1);
                    $tp->setValue('alternative_item#1', '');
                }
            }
            
            // معالجة المعايير (analysis_teacher_criteria) - Loop
            if (in_array('criterion_code', $templateVariables, true)) {
                $criteria = $data['analysis_teacher_criteria'] ?? [];
                $criteria = $this->arrify($criteria, true);
                
                if (count($criteria) > 0) {
                    $criteriaRows = [];
                    foreach ($criteria as $criterion) {
                        $criteriaRows[] = ['criterion_code' => $this->toText($criterion)];
                    }
                    $tp->cloneRowAndSetValues('criterion_code', $criteriaRows);
                } else {
                    // صف واحد فارغ
                    $tp->cloneRow('criterion_code', 1);
                    $tp->setValue('criterion_code#1', '');
                }
            }
            
            // الحفظ
            $tp->saveAs($targetAbs);
            
            if (!is_file($targetAbs)) {
                throw new \RuntimeException('Failed saving: ' . $targetAbs);
            }
            
            return [
                'rel_path' => ltrim($targetRel, '/'),
                'bytes'    => filesize($targetAbs) ?: 0,
            ];
        }
        
        // ✅ لدمج الحالة: الحقول الخاصة بها فقط
        if ($isCaseIntegration) {
            // الحقول العادية (Single Values)
            $singleKeys = [
                'teacher_name',
                'case',
                'case_description',
                'reason_for_integration',
                'environment',
            ];
            
            // استخدام الحقول العادية
            foreach ($singleKeys as $k) {
                // البحث في المتغيرات الأصلية والمنظفة
                if (in_array($k, $templateVariables, true)) {
                    $tp->setValue($k, $this->toText($data[$k] ?? ''));
                } elseif (in_array($k, $cleanedTemplateVariables, true)) {
                    // إذا كان المتغير موجوداً في القائمة المنظفة، استخدم الاسم الأصلي من الخريطة
                    $originalVar = $variableMapping[$k] ?? $k;
                    $tp->setValue($originalVar, $this->toText($data[$k] ?? ''));
                } else {
                    // محاولة البحث عن المتغير مع $ في النهاية (PhpWord قد لا يتعرف عليه)
                    $varWithDollar = $k . '$';
                    try {
                        $tp->setValue($varWithDollar, $this->toText($data[$k] ?? ''));
                    } catch (\Exception $e) {
                        // إذا فشل، تجاهل
                    }
                }
            }
            
            // معالجة الخطأ الإملائي الشائع: enviroment -> environment
            if (in_array('enviroment', $templateVariables, true) && !in_array('environment', $templateVariables, true)) {
                $tp->setValue('enviroment', $this->toText($data['environment'] ?? ''));
            } elseif (in_array('enviroment', $cleanedTemplateVariables, true)) {
                $originalVar = $variableMapping['enviroment'] ?? 'enviroment';
                $tp->setValue($originalVar, $this->toText($data['environment'] ?? ''));
            } else {
                // محاولة مع enviroment$
                try {
                    $tp->setValue('enviroment$', $this->toText($data['environment'] ?? ''));
                } catch (\Exception $e) {
                    // إذا فشل، تجاهل
                }
            }
            
            // معالجة أنشطة التعلم (activities) - Loop
            if (in_array('activity_item', $templateVariables, true)) {
                $activities = $data['activities'] ?? [];
                $activities = $this->arrify($activities);
                
                if (count($activities) > 0) {
                    $activityRows = [];
                    foreach ($activities as $activity) {
                        $activityRows[] = ['activity_item' => $this->toText($activity)];
                    }
                    $tp->cloneRowAndSetValues('activity_item', $activityRows);
                } else {
                    // صف واحد فارغ
                    $tp->cloneRow('activity_item', 1);
                    $tp->setValue('activity_item#1', '');
                }
            }
            
            // معالجة أصحاب العلاقة (Stakeholders) - Loop
            if (in_array('stakeholder_item', $templateVariables, true)) {
                $stakeholders = $data['Stakeholders'] ?? [];
                $stakeholders = $this->arrify($stakeholders);
                
                if (count($stakeholders) > 0) {
                    $stakeholderRows = [];
                    foreach ($stakeholders as $stakeholder) {
                        $stakeholderRows[] = ['stakeholder_item' => $this->toText($stakeholder)];
                    }
                    $tp->cloneRowAndSetValues('stakeholder_item', $stakeholderRows);
                } else {
                    // صف واحد فارغ
                    $tp->cloneRow('stakeholder_item', 1);
                    $tp->setValue('stakeholder_item#1', '');
                }
            }
            
            // معالجة المعايير (teacher_criteria) - Loop
            if (in_array('criterion_code', $templateVariables, true)) {
                $criteria = $data['teacher_criteria'] ?? [];
                $criteria = $this->arrify($criteria, true);
                
                if (count($criteria) > 0) {
                    $criteriaRows = [];
                    foreach ($criteria as $criterion) {
                        $criteriaRows[] = ['criterion_code' => $this->toText($criterion)];
                    }
                    $tp->cloneRowAndSetValues('criterion_code', $criteriaRows);
                } else {
                    // صف واحد فارغ
                    $tp->cloneRow('criterion_code', 1);
                    $tp->setValue('criterion_code#1', '');
                }
            }
            
            // الحفظ
            $tp->saveAs($targetAbs);
            
            if (!is_file($targetAbs)) {
                throw new \RuntimeException('Failed saving: ' . $targetAbs);
            }
            
            return [
                'rel_path' => ltrim($targetRel, '/'),
                'bytes'    => filesize($targetAbs) ?: 0,
            ];
        }
        
        // ✅ للمقال التحليلي: الحقول الخاصة به فقط
        if ($isAnalyticalArticle) {
            // الحقول الأساسية: كل فقرة في متغير مستقل
            $singleKeys = [
                'article_title',
                'introduction_paragraph_1',
                'introduction_paragraph_2',
                'body_paragraph_1',
                'body_paragraph_2',
                'body_paragraph_3',
                'conclusion_paragraph_1',
                'conclusion_paragraph_2',
            ];
            
            // استخدام الحقول الخاصة بالمقال التحليلي
            foreach ($singleKeys as $k) {
                if (in_array($k, $templateVariables, true)) {
                    $tp->setValue($k, $this->toText($data[$k] ?? ''));
                }
            }
            
            // المراجع: معالجة خاصة إذا كان موجوداً في Template
            if (in_array('references', $templateVariables, true)) {
                $references = $data['references'] ?? [];
                if (is_array($references) && !empty($references)) {
                    // تحويل array المراجع إلى نص (كل مرجع في سطر مستقل)
                    $refText = implode("\n", array_map(
                        fn($ref) => '• ' . (is_scalar($ref) ? (string)$ref : json_encode($ref, JSON_UNESCAPED_UNICODE)),
                        $references
                    ));
                    $tp->setValue('references', $refText);
                } else {
                    $tp->setValue('references', '');
                }
            }
            
            // لا نحتاج إلى cloneRow للمقال التحليلي
            // نحفظ الملف مباشرة
            $tp->saveAs($targetAbs);
            
            if (!is_file($targetAbs)) {
                throw new \RuntimeException('Failed saving: ' . $targetAbs);
            }
            
            return [
                'rel_path' => ltrim($targetRel, '/'),
                'bytes'    => filesize($targetAbs) ?: 0,
            ];
        }

        // ✅ لدمج التقويم: الحقول الخاصة به فقط
        if ($isEvalIntegration) {
            $evalKeys = [
                'teacher_name', 'academic_number', 'subject_name', 'lesson_title',
                'cognitive_outcome', 'skill_outcome', 'cognitive_evaluation', 'skill_evaluation',
                'cognitive_teaching', 'skill_teaching',
                'reflection_satisfactory_deal', 'reflection_satisfactory_decision', 'reflection_satisfactory_adjustment',
                'reflection_unsatisfactory_deal', 'reflection_unsatisfactory_decision', 'reflection_unsatisfactory_adjustment',
                'reflection_neutral_deal', 'reflection_neutral_decision', 'reflection_neutral_adjustment'
            ];
            
            foreach ($evalKeys as $k) {
                if (in_array($k, $templateVariables, true)) {
                    $tp->setValue($k, $this->toText($data[$k] ?? ''));
                }
            }

            // المؤشرات تحتوي على فواصل أسطر تم تجهيزها في Runner، لذا لا نمررها بـ toText حتى لا تحذف
            foreach (['cognitive_indicators', 'skill_indicators'] as $k) {
                if (in_array($k, $templateVariables, true)) {
                    $tp->setValue($k, (string)($data[$k] ?? ''));
                }
            }

            $tp->saveAs($targetAbs);
            
            if (!is_file($targetAbs)) {
                throw new \RuntimeException('Failed saving: ' . $targetAbs);
            }
            
            return [
                'rel_path' => ltrim($targetRel, '/'),
                'bytes'    => filesize($targetAbs) ?: 0,
            ];
        }

        // ✅ لأداة تطوير السؤال: الحقول الخاصة بها
        if ($isQuestionDevelop) {
            $basicKeys = [
                'competencies',
                'original_question',
                'modified_question'
            ];
            
            foreach ($basicKeys as $k) {
                if (in_array($k, $templateVariables, true) || in_array($k, $cleanedTemplateVariables, true)) {
                    $val = $this->toText($data[$k] ?? '');
                    // تعويض آمن
                    $this->setValueSafely($tp, $k, $val, $templateVariables, $variableMapping);
                }
            }

            // علامات الصح للـ criteria
            $criteria = $data['criteria_used'] ?? [];
            $critKeys = [
                'complexity' => 'crit_complexity',
                'competency' => 'crit_competency',
                'real_life'  => 'crit_real_life',
                'phrasing'   => 'crit_phrasing',
                'bias'       => 'crit_bias'
            ];

            foreach ($critKeys as $jsonKey => $placeholder) {
                if (in_array($placeholder, $templateVariables, true) || in_array($placeholder, $cleanedTemplateVariables, true)) {
                    $isUsed = !empty($criteria[$jsonKey]) && $criteria[$jsonKey] === true;
                    $mark = $isUsed ? '☑' : '☐';
                    $this->setValueSafely($tp, $placeholder, $mark, $templateVariables, $variableMapping);
                }
            }

            $tp->saveAs($targetAbs);
            
            if (class_exists('ZipArchive')) {
                $this->cleanUnreplacedVariables($targetAbs);
            }

            if (!is_file($targetAbs)) {
                throw new \RuntimeException('Failed saving: ' . $targetAbs);
            }
            
            return [
                'rel_path' => ltrim($targetRel, '/'),
                'bytes'    => filesize($targetAbs) ?: 0,
            ];
        }

        // ✅ للبحث الاستقصائي: الأقسام النصية + جدول الحصص + المراجع + الملاحق
        if ($isInquiryResearch) {
            // ① الحقول النصية الأساسية (مفتاح واحد = متغير واحد في القالب)
            $irTextKeys = [
                'research_question',
                'section_1_intro',
                'section_2_literature',
                'section_3_overview',
                'section_4_ethics',
                'section_5_results',
                'section_6_reflection',
            ];
            foreach ($irTextKeys as $k) {
                if (in_array($k, $templateVariables, true)) {
                    // ✅ sanitizeForXml داخل toText() يضمن سلامة XML
                    $tp->setValue($k, $this->toText($data[$k] ?? ''));
                }
            }

            // ② المراجع: قائمة مرقّمة (مع تنظيف XML لكل مرجع)
            if (in_array('section_2_references', $templateVariables, true)) {
                $refs = $data['section_2_references'] ?? [];
                if (is_array($refs) && !empty($refs)) {
                    $lines = [];
                    $idx = 1;
                    foreach ($refs as $ref) {
                        $text = is_scalar($ref) ? (string)$ref : ($ref['text'] ?? json_encode($ref, JSON_UNESCAPED_UNICODE));
                        $text = $this->sanitizeForXml(trim($text));
                        if ($text !== '') {
                            $lines[] = $idx . '. ' . $text;
                            $idx++;
                        }
                    }
                    $tp->setValue('section_2_references', implode('<w:br/>', $lines));
                } else {
                    $tp->setValue('section_2_references', $this->toText($refs));
                }
            }

            // ③ جدول الحصص (section_3_table) — cloneRowAndSetValues
            if (in_array('ir_lesson_title', $templateVariables, true)) {
                $tableRows = $data['section_3_table'] ?? [];
                if (!is_array($tableRows) || empty($tableRows)) {
                    $tableRows = [[]];
                }
                $rowData = [];
                foreach ($tableRows as $row) {
                    if (!is_array($row)) { $row = []; }
                    $rowData[] = [
                        'ir_lesson_title'       => $this->toText($row['lesson_title']          ?? ''),
                        'ir_learning_outcomes'  => $this->toText($row['learning_outcomes']     ?? ''),
                        'ir_teaching_strategy'  => $this->toText($row['teaching_strategy']     ?? ''),
                        'ir_data_tools'         => $this->toText($row['data_collection_tools'] ?? ''),
                        'ir_assessment'         => $this->toText($row['assessment_strategy']   ?? ''),
                        'ir_resources'          => $this->toText($row['learning_resources']    ?? ''),
                    ];
                }
                $tp->cloneRowAndSetValues('ir_lesson_title', $rowData);
            }

            // ④ الملاحق: قائمة مرقّمة (مع تنظيف XML)
            if (in_array('appendices', $templateVariables, true)) {
                $apps = $data['appendices'] ?? [];
                if (is_array($apps) && !empty($apps)) {
                    $lines = [];
                    $idx = 1;
                    foreach ($apps as $app) {
                        $text = is_scalar($app) ? (string)$app : ($app['text'] ?? json_encode($app, JSON_UNESCAPED_UNICODE));
                        $text = $this->sanitizeForXml(trim($text));
                        if ($text !== '') {
                            $lines[] = $idx . '. ' . $text;
                            $idx++;
                        }
                    }
                    $tp->setValue('appendices', implode('<w:br/>', $lines));
                } else {
                    $tp->setValue('appendices', $this->toText($apps));
                }
            }

            $tp->saveAs($targetAbs);
            // ✅ تنظيف أي متغيرات غير مستبدلة بعد الحفظ
            if (class_exists('ZipArchive')) {
                $this->cleanUnreplacedVariables($targetAbs);
            }
            if (!is_file($targetAbs)) {
                throw new \RuntimeException('Failed saving: ' . $targetAbs);
            }
            return [
                'rel_path' => ltrim($targetRel, '/'),
                'bytes'    => filesize($targetAbs) ?: 0,
            ];
        }

        // ✅ لأدوات البحث الاستقصائي الجديدة: الحقول الخاصة بها (تعتمد على قالب فارغ)
        if ($isInquiryTools) {
            $inquiryKeys = ['title', 'content', 'result'];
            foreach ($inquiryKeys as $k) {
                if (in_array($k, $templateVariables, true) || in_array($k, $cleanedTemplateVariables, true)) {
                    $val = $this->toMultilineText($data[$k] ?? '');
                    $this->setValueSafely($tp, $k, $val, $templateVariables, $variableMapping);
                }
            }

            $tp->saveAs($targetAbs);
            if (class_exists('ZipArchive')) {
                $this->cleanUnreplacedVariables($targetAbs);
            }
            if (!is_file($targetAbs)) {
                throw new \RuntimeException('Failed saving: ' . $targetAbs);
            }
            return [
                'rel_path' => ltrim($targetRel, '/'),
                'bytes'    => filesize($targetAbs) ?: 0,
            ];
        }

        // ✅ للمقال التأملي التكويني:
        if ($isFormativeReflectiveEssay) {
            $freKeys = ['article_title', 'content'];
            foreach ($freKeys as $k) {
                if (in_array($k, $templateVariables, true) || in_array($k, $cleanedTemplateVariables, true)) {
                    $val = $this->toMultilineText($data[$k] ?? '');
                    $this->setValueSafely($tp, $k, $val, $templateVariables, $variableMapping);
                }
            }

            $tp->saveAs($targetAbs);
            if (class_exists('ZipArchive')) {
                $this->cleanUnreplacedVariables($targetAbs);
            }
            if (!is_file($targetAbs)) {
                throw new \RuntimeException('Failed saving: ' . $targetAbs);
            }
            return [
                'rel_path' => ltrim($targetRel, '/'),
                'bytes'    => filesize($targetAbs) ?: 0,
            ];
        }

        // ✅ للموقف التقييمي: الحقول الخاصة به فقط
        if ($isAssessmentPosition) {
            $apKeys = [
                'teacher_name', 'school_name', 'school_class', 'general_subject', 'general_unit', 'general_title',
                'cognitive_outcome', 'skill_outcome', 'value_outcome', 'outcomes_time',
                'learning_strategies', 'situation_description', 'assessment_task', 'learning_sources',
                'grasps_goal', 'grasps_role', 'grasps_audience', 'grasps_situation', 'grasps_performance', 'grasps_standards',
                'assessment_strategy', 'assessment_tool', 'assessment_model'
            ];
            
            foreach ($apKeys as $k) {
                if (in_array($k, $templateVariables, true)) {
                    if ($k === 'assessment_model') {
                        $val = (string)($data[$k] ?? '');
                        // إزالة خطوط فصل جداول Markdown
                        $val = preg_replace('/\|[\-\:]+/', '|', $val);
                        $val = str_replace('|---|', '|', $val);
                        // ✅ تنظيف رموز XML الخطرة قبل تحويل الأسطر
                        $val = $this->sanitizeForXml($val);
                        // تحويل الأسطر إلى فاصل Word
                        $val = str_replace(["\r\n", "\n\r", "\n", "\r"], '<w:br/>', $val);
                        $tp->setValue($k, $val);
                    } else {
                        // ✅ toText() يستدعي sanitizeForXml() داخلياً
                        $tp->setValue($k, $this->toText($data[$k] ?? ''));
                    }
                }
            }

            $tp->saveAs($targetAbs);
            // ✅ تنظيف المتغيرات غير المستبدلة
            if (class_exists('ZipArchive')) {
                $this->cleanUnreplacedVariables($targetAbs);
            }
            if (!is_file($targetAbs)) {
                throw new \RuntimeException('Failed saving: ' . $targetAbs);
            }
            
            return [
                'rel_path' => ltrim($targetRel, '/'),
                'bytes'    => filesize($targetAbs) ?: 0,
            ];
        }
        
        // ✅ لمقيم المقالات: معالجة خاصة
        if ($isArticleEvaluator) {
            $aeKeys = [
                'teacher_name', 'student_number', 'teacher_supervisor',
                'article_title', 'turnitin_percentage', 'references_match', 'formatting_compliance',
                'general_feedback', 'general_guidance',
                'introduction_section_feedback', 'body_section_feedback', 'conclusion_section_feedback'
            ];
            
            foreach ($aeKeys as $k) {
                if (in_array($k, $templateVariables, true)) {
                    $tp->setValue($k, $this->toText($data[$k] ?? ''));
                }
            }
            
            // القوائم النقطية (strengths & improvements)
            $listKeys = [
                'knowledge_strengths', 'knowledge_improvements',
                'cognitive_strengths', 'cognitive_improvements',
                'professional_strengths', 'professional_improvements',
                'presentation_strengths', 'presentation_improvements',
                'introduction_feedbacks', 'body_feedbacks', 'conclusion_feedbacks'
            ];
            
            foreach ($listKeys as $k) {
                if (in_array($k, $templateVariables, true)) {
                    $arr = $data[$k] ?? [];
                    if (is_array($arr) && !empty($arr)) {
                        $lines = [];
                        foreach ($arr as $item) {
                            $text = is_array($item) ? ($item['text'] ?? '') : (string)$item;
                            if (trim($text) !== '') {
                                $lines[] = '• ' . str_replace("\n", ' | ', $text);
                            }
                        }
                        $tp->setValue($k, implode('<w:br/>', $lines));
                    } else {
                        $tp->setValue($k, '');
                    }
                }
            }

            $tp->saveAs($targetAbs);
            
            if (!is_file($targetAbs)) {
                throw new \RuntimeException('Failed saving: ' . $targetAbs);
            }
            
            return [
                'rel_path' => ltrim($targetRel, '/'),
                'bytes'    => filesize($targetAbs) ?: 0,
            ];
        }

        // ✅ لدمج أصحاب العلاقة: الحقول الخاصة بها
        if ($isStakeholderIntegration) {
            $siKeys = [
                'activity_name',
                'stakeholder_role',
                'activity_type',
                'grade_level',
                'lesson',
                'learning_outcome',
                'subject_area',
                'activity_goal',
                'planning_procedures',
                'implementation_procedures',
                'self_reflection',
            ];

            foreach ($siKeys as $k) {
                if (in_array($k, $templateVariables, true) || in_array($k, $cleanedTemplateVariables, true)) {
                    $val = $this->toText($data[$k] ?? '');
                    $this->setValueSafely($tp, $k, $val, $templateVariables, $variableMapping);
                }
            }

            // معالجة معايير المعلمين (teacher_criteria) - Loop
            if (in_array('criterion_code', $templateVariables, true) || in_array('criterion_code', $cleanedTemplateVariables, true)) {
                $criteria = $data['teacher_criteria'] ?? [];
                $criteria = $this->arrify($criteria, true);

                if (count($criteria) > 0) {
                    $criteriaRows = [];
                    foreach ($criteria as $criterion) {
                        $criteriaRows[] = ['criterion_code' => $this->toText($criterion)];
                    }
                    $tp->cloneRowAndSetValues('criterion_code', $criteriaRows);
                } else {
                    $tp->cloneRow('criterion_code', 1);
                    $tp->setValue('criterion_code#1', '');
                }
            }

            $tp->saveAs($targetAbs);

            if (class_exists('ZipArchive')) {
                $this->cleanUnreplacedVariables($targetAbs);
            }

            if (!is_file($targetAbs)) {
                throw new \RuntimeException('Failed saving: ' . $targetAbs);
            }

            return [
                'rel_path' => ltrim($targetRel, '/'),
                'bytes'    => filesize($targetAbs) ?: 0,
            ];
        }

        // ✅ لتقرير التغذية الراجعة (مقيّم البحث الاستقصائي)
        $isInquiryReportEvaluator = ($typeKey === 'inquiry_report_evaluator_v1');
        if ($isInquiryReportEvaluator) {
            // الحقول النصية
            $textKeys = [
                'student_name', 'student_id', 'teacher_supervisor', 'date',
                'overall_grade_symbol', 'overall_grade_word', 'general_notes',
            ];
            foreach ($textKeys as $k) {
                if (in_array($k, $templateVariables, true) || in_array($k, $cleanedTemplateVariables, true)) {
                    $val = $this->toText($data[$k] ?? '');
                    $this->setValueSafely($tp, $k, $val, $templateVariables, $variableMapping);
                }
            }

            // نقاط القوة (Loop)
            $this->processLoop($tp, 'strength_item', $data['strengths'] ?? [], $templateVariables);

            // مجالات التطوير (Loop)
            $this->processLoop($tp, 'development_item', $data['areas_for_development'] ?? [], $templateVariables);

            $tp->saveAs($targetAbs);

            if (class_exists('ZipArchive')) {
                $this->cleanUnreplacedVariables($targetAbs);
            }

            if (!is_file($targetAbs)) {
                throw new \RuntimeException('Failed saving: ' . $targetAbs);
            }

            return [
                'rel_path' => ltrim($targetRel, '/'),
                'bytes'    => filesize($targetAbs) ?: 0,
            ];
        }

        // ✅ لجدول الروبريك (مقيّم البحث الاستقصائي) مع تظليل أصفر
        $isInquiryRubric = ($typeKey === 'inquiry_report_rubric_v1');
        if ($isInquiryRubric) {
            // الحقول النصية الأساسية
            $textKeys = [
                'student_name', 'student_id', 'date',
                'overall_grade_symbol', 'overall_grade_word',
            ];
            foreach ($textKeys as $k) {
                if (in_array($k, $templateVariables, true) || in_array($k, $cleanedTemplateVariables, true)) {
                    $val = $this->toText($data[$k] ?? '');
                    $this->setValueSafely($tp, $k, $val, $templateVariables, $variableMapping);
                }
            }

            // تعبئة خلايا الروبريك (8 معايير × 5 مستويات)
            for ($idx = 1; $idx <= 8; $idx++) {
                foreach (['D','M','GP','P','F'] as $level) {
                    $key = "criterion_{$idx}_{$level}";
                    if (in_array($key, $templateVariables, true) || in_array($key, $cleanedTemplateVariables, true)) {
                        $val = $this->toText($data[$key] ?? '');
                        $this->setValueSafely($tp, $key, $val, $templateVariables, $variableMapping);
                    }
                }
                // اسم المعيار وتقديره (اختياري — حسب القالب)
                foreach (['_name', '_symbol', '_word'] as $suffix) {
                    $key = "criterion_{$idx}{$suffix}";
                    if (in_array($key, $templateVariables, true) || in_array($key, $cleanedTemplateVariables, true)) {
                        $val = $this->toText($data[$key] ?? '');
                        $this->setValueSafely($tp, $key, $val, $templateVariables, $variableMapping);
                    }
                }
            }

            $tp->saveAs($targetAbs);

            // تظليل الخلايا المطابقة بلون أصفر عبر XML
            if (class_exists('ZipArchive')) {
                $this->highlightRubricCells($targetAbs, $data);
                $this->cleanUnreplacedVariables($targetAbs);
            }

            if (!is_file($targetAbs)) {
                throw new \RuntimeException('Failed saving: ' . $targetAbs);
            }

            return [
                'rel_path' => ltrim($targetRel, '/'),
                'bytes'    => filesize($targetAbs) ?: 0,
            ];
        }

        // الحقول الأساسية المشتركة
        $baseKeys = [
            'teacher_name',
            'stage_level',
            'subject_name',
            'unit_title',
            'curriculum_pages',
            'lessons_count',
        ];
        
        // الحقول الخاصة بمصفوفة التحليل
        $matrixKeys = [
            'matrix_col_content',
            'matrix_col_concepts',
            'matrix_col_principles',
            'matrix_col_activities',
            'matrix_col_values',
            'matrix_col_visuals',
            'matrix_col_other_tasks',
        ];
        
        // الحقول الخاصة بخطة الحصة
        $lessonPlanKeys = [
            'school_name',
            'general_subject',
            'subject_brief',
            'school_class',
            'general_title',
            'general_unit',
            'summary',
            'additional_notes',
            'analysis_terms',
            'analysis_knowledge',
            'analysis_values',
            'analysis_skills',
            'analysis_activities',
            'analysis_visuals',
            'analysis_notes',
            'integration_vertical',
            'integration_horizontal',
            'prior_knowledge',
            'knowledge_cognitive',
            'knowledge_skills',
            'knowledge_values',
            'learning_sources',
            'e_application',
            'warmup_teacher',
            'warmup_student',
            'w_min',
            'intro_teacher',
            'intro_student',
            'i_min',
            'closure_teacher',
            'closure_student',
            'c_min',
            'reflection',
            'differentiation_content',
            'differentiation_process',
            'differentiation_product',
            'digital_safety',
            'w_teach_strategy',
            'i_teach_strategy',
            'c_assess_strategy',
            'c_assess_tool',
        ];

        // بناء قائمة الحقول حسب نوع الأداة
        if ($isMatrixAnalysis) {
            $singleKeys = array_merge($baseKeys, $matrixKeys);
        } else {
            // لخطة الحصة أو أدوات أخرى: جميع الحقول
            $singleKeys = array_merge($baseKeys, $matrixKeys, $lessonPlanKeys);
        }

        // ✅ استخدام فقط الحقول الموجودة في القالب
        foreach ($singleKeys as $k) {
            // التحقق من وجود الحقل في القالب قبل تعيينه
            if (in_array($k, $templateVariables, true)) {
                $tp->setValue($k, $this->toText($data[$k] ?? ''));
            }
        }

        // 2) صف النتائج المتكرر: ${o_statement} ... ${o_duration}
        // ✅ التحقق من وجود o_statement في القالب قبل محاولة cloneRow
        $hasOutcomeRows = in_array('o_statement', $templateVariables, true);
        if ($hasOutcomeRows) {
            $rows = $this->buildOutcomeRows($data);
            if (count($rows) > 0) {
                // ينسخ الصف الذي يحتوي ${o_statement} بعدد عناصر $rows ويملأ كل عمود
                $tp->cloneRowAndSetValues('o_statement', $rows);
            } else {
                // على الأقل صف واحد فارغ (اختياري)
                $tp->cloneRow('o_statement', 1);
                $tp->setValue('o_statement#1', '');
                $tp->setValue('o_assess_strategy#1', '');
                $tp->setValue('o_assess_tool#1', '');
                $tp->setValue('o_teach_strategy#1', '');
                $tp->setValue('o_teacher#1', '');
                $tp->setValue('o_student#1', '');
                $tp->setValue('o_dur#1', '');
            }
        }
        // إذا لم يكن o_statement موجوداً في القالب (مثل matrix_analysis_v1)، نتجاهل cloneRow

        // 3) الحفظ
        $tp->saveAs($targetAbs);

        if (!is_file($targetAbs)) {
            throw new \RuntimeException('Failed saving: ' . $targetAbs);
        }

        return [
            'rel_path' => ltrim($targetRel, '/'),
            'bytes'    => filesize($targetAbs) ?: 0,
        ];
    }

    /**
     * يبني مصفوفة صفوف متجانسة لـ cloneRowAndSetValues
     * كل صف يحتوي المفاتيح التالية بالضبط:
     * o_statement, o_assess_strategy, o_assess_tool, o_teach_strategy, o_teacher, o_student, o_duration
     */
    protected function buildOutcomeRows(array $data): array
    {
        // اجلب الحقول كما هي من JSON (قد تكون نص/مصفوفة)
        $S   = $data['o_statement']       ?? [];
        $AS  = $data['o_assess_strategy'] ?? [];
        $AT  = $data['o_assess_tool']     ?? [];
        $TS  = $data['o_teach_strategy']  ?? [];
        $T   = $data['o_teacher']         ?? [];
        $ST  = $data['o_student']         ?? [];
        $DUR = $data['o_dur']        ?? [];

        // حوّل أي قيمة لنموذج مصفوفة نصوص (ونسطّح العناصر المركبة)
        $S   = $this->arrify($S);
        $AS  = $this->arrify($AS);
        $AT  = $this->arrify($AT);
        $TS  = $this->arrify($TS);
        $T   = $this->arrify($T);
        $ST  = $this->arrify($ST);
        $DUR = $this->arrify($DUR, true); // مدّة: نحتفظ بالأرقام كنص

        // أقصى طول موجود بين الحقول — هو عدد الصفوف
        $n = max(
            count($S), count($AS), count($AT),
            count($TS), count($T), count($ST), count($DUR)
        );

        $rows = [];
        for ($i = 0; $i < $n; $i++) {
            $rows[] = [
                'o_statement'       => $this->atOrEmpty($S, $i),
                'o_assess_strategy' => $this->atOrEmpty($AS, $i),
                'o_assess_tool'     => $this->atOrEmpty($AT, $i),
                'o_teach_strategy'  => $this->atOrEmpty($TS, $i),
                'o_teacher'         => $this->atOrEmpty($T, $i),
                'o_student'         => $this->atOrEmpty($ST, $i),
                'o_dur'        => $this->atOrEmpty($DUR, $i),
            ];
        }

        return $rows;
    }

    /**
     * تحويل المصفوفات إلى نص متعدد الأسطر برموز نقطية
     */
    protected function listToMultiline($value): string
    {
        if (!is_array($value)) {
            return $this->toText($value);
        }

        $items = array_filter(array_map(function ($item) {
            if (is_scalar($item)) {
                return '• ' . $this->cleanPlaceholder((string) $item);
            }
            if (is_array($item)) {
                return '• ' . $this->cleanPlaceholder($this->toText($item));
            }
            return null;
        }, $value));

        return implode("\n", $items);
    }

    /**
     * يحوّل أي قيمة إلى نص (وإذا كانت مصفوفة يدمجها بنقاط وسطية).
     * يُطبّق sanitizeForXml() لضمان سلامة XML الناتج.
     */
    protected function toText($val): string
    {
        if ($val === null || $val === '') {
            return '';
        }
        
        if (is_array($val)) {
            // تصفية العناصر الفارغة
            $flat = [];
            array_walk_recursive($val, function ($v) use (&$flat) {
                if ($v !== null && $v !== '') {
                    $flat[] = is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
                }
            });
            if (empty($flat)) {
                return '';
            }
            return implode(' • ', array_map([$this, 'sanitizeForXml'], $flat));
        }
        return $this->sanitizeForXml((string) $val);
    }

    /**
     * تطبيع إلى مصفوفة نصوص (إذا دخل نص واحد نجعله [النص]).
     * إذا كان العنصر نفسه مصفوفة (مثلاً أكثر من أداة تقييم لصف واحد) ندمجها بـ " • ".
     * $keepNumeric: عند true لا نحوّل الأرقام إلى تمثيل خاص، فقط نص عادي.
     */
    protected function arrify($v, bool $keepNumeric = false): array
    {
        if (!is_array($v)) {
            return [$this->toText($v)];
        }
        // عنصر مصفوفة قد يحتوي مصفوفات/أرقام/نصوص
        $out = [];
        foreach ($v as $item) {
            if (is_array($item)) {
                $flat = [];
                array_walk_recursive($item, function ($x) use (&$flat) {
                    $flat[] = is_scalar($x) ? (string) $x : json_encode($x, JSON_UNESCAPED_UNICODE);
                });
                $out[] = implode(' • ', array_map([$this, 'cleanPlaceholder'], $flat));
            } else {
                $out[] = $keepNumeric ? $this->cleanPlaceholder((string) $item)
                                      : $this->toText($item);
            }
        }
        return $out;
    }

    /**
     * جلب العنصر $i من مصفوفة أو إرجاع نص فارغ.
     */
    protected function atOrEmpty(array $arr, int $i): string
    {
        return isset($arr[$i]) ? $this->cleanPlaceholder((string) $arr[$i]) : '';
    }

    /**
     * معالجة loop variable في Template
     * @param TemplateProcessor $tp
     * @param string $variableName اسم المتغير (مثل: knowledge_strength_item)
     * @param array $items المصفوفة التي تحتوي على العناصر
     * @param array $templateVariables قائمة المتغيرات الموجودة في Template
     */
    protected function processLoop($tp, string $variableName, array $items, array $templateVariables): void
    {
        // التحقق من وجود المتغير في Template
        if (!in_array($variableName, $templateVariables, true)) {
            return;
        }

        // تصفية العناصر الفارغة وتجهيز البيانات
        $validItems = [];
        foreach ($items as $item) {
            $text = '';
            if (is_array($item) && isset($item['text'])) {
                $text = trim($this->toText($item['text']));
            } elseif (is_scalar($item)) {
                $text = trim($this->toText($item));
            }
            
            if (!empty($text)) {
                $validItems[] = $text;
            }
        }

        // التأكد من تنظيف البيانات من XML special characters
        $validItems = array_map(function($item) {
            // تنظيف من XML special characters التي قد تسبب مشاكل
            $item = str_replace(['&', '<', '>', '"', "'"], ['&amp;', '&lt;', '&gt;', '&quot;', '&apos;'], $item);
            // إضافة علامة الاتجاه (Right-to-Left Mark) لإجبار Word على تنسيق النص كعربي من اليمين
            return "\xE2\x80\x8F" . $item;
        }, $validItems);

        if (count($validItems) > 0) {
            try {
                // استخدام cloneRow يدوياً لكل عنصر للتأكد من الإغلاق الصحيح
                // أولاً، نسخ الصف الأول
                $tp->cloneRow($variableName, count($validItems));
                
                // ثم تعيين القيم
                foreach ($validItems as $index => $text) {
                    $rowNumber = $index + 1;
                    $macro = $variableName . '#' . $rowNumber;
                    try {
                        $tp->setValue($macro, $text);
                    } catch (\Exception $e) {
                        log_message('warning', "Failed to set value for {$macro}: " . $e->getMessage());
                        // محاولة البديل
                        try {
                            $tp->setValue($variableName . '#' . ($rowNumber), '-');
                        } catch (\Exception $e2) {
                            // تجاهل
                        }
                    }
                }
            } catch (\Exception $e) {
                // تسجيل الخطأ
                log_message('error', "Failed to process loop {$variableName}: " . $e->getMessage());
                log_message('error', "Stack trace: " . $e->getTraceAsString());
                // محاولة إنشاء صف واحد فارغ
                try {
                    $tp->cloneRow($variableName, 1);
                    $tp->setValue($variableName . '#1', '-');
                } catch (\Exception $e2) {
                    log_message('error', "Failed to create fallback row for {$variableName}: " . $e2->getMessage());
                    // إذا فشل كل شيء، نترك المتغير كما هو (سيتم تنظيفه لاحقاً)
                }
            }
        } else {
            // إنشاء صف واحد فارغ فقط إذا لم توجد بيانات صحيحة
            try {
                $tp->cloneRow($variableName, 1);
                $tp->setValue($variableName . '#1', '-');
            } catch (\Exception $e) {
                log_message('warning', "Failed to create empty row for {$variableName}: " . $e->getMessage());
                // تجاهل إذا فشل - سيتم تنظيف المتغير لاحقاً
            }
        }
    }

    /**
     * تنظيف طفيف حتى لا تنكسر placeholders أو يمرّ تنسيق مخفي.
     */
    protected function cleanPlaceholder(string $s): string
    {
        // حذف أسطر، واستبدال مسافات غريبة بمسافة عادية
        $s = str_replace(["\r\n", "\r", "\n"], ' ', $s);
        // إزالة محارف RTL/LTR المخفية الشائعة إن وُجدت
        $s = str_replace(["\xE2\x80\x8F", "\xE2\x80\x8E", "\xC2\xA0"], ' ', $s); // RLM, LRM, NBSP
        return trim($s);
    }

    /**
     * تنظيف شامل لأي قيمة قبل إدراجها في قالب DOCX لمنع كسر XML.
     * يُزيل:
     *  - رموز XML الخاصة التي تُكسر بنية الملف
     *  - رموز Markdown التي قد يُعيدها الـ AI
     *  - المحارف غير المرئية والرموز الغريبة
     */
    protected function sanitizeForXml(string $s): string
    {
        // 1) إزالة محارف التحكم غير المرئية (عدا المسافة والسطر والتبويب)
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s ?? '');

        // 2) تنظيف رموز Markdown الشائعة من مخرجات الـ AI
        // Headers: ### text → text
        $s = preg_replace('/^#{1,6}\s*/mu', '', $s);
        // Bold/Italic: **text** → text, *text* → text, __text__ → text
        $s = preg_replace('/\*{1,3}([^*]+)\*{1,3}/u', '$1', $s);
        $s = preg_replace('/_{1,2}([^_]+)_{1,2}/u', '$1', $s);
        // Backticks: `code` → code
        $s = preg_replace('/`([^`]+)`/u', '$1', $s);
        // Links: [text](url) → text
        $s = preg_replace('/\[([^\]]+)\]\([^)]+\)/u', '$1', $s);
        // Markdown bullets at line start: - item or * item → item
        $s = preg_replace('/^[\-\*\+]\s+/mu', '', $s);
        // Markdown horizontal rules
        $s = preg_replace('/^[-_*]{3,}$/mu', '', $s);

        // 3) تحويل أسطر متعددة إلى مسافة واحدة (للنصوص في خلايا جداول)
        $s = str_replace(["\r\n", "\r", "\n"], ' ', $s);

        // 4) إزالة محارف RTL/LTR المخفية
        $s = str_replace(["\xE2\x80\x8F", "\xE2\x80\x8E", "\xC2\xA0"], ' ', $s);

        // 5) تنظيف المسافات المتكررة
        $s = preg_replace('/\s{2,}/u', ' ', $s);

        // 6) تشفير رموز XML الخاصة التي تكسر بنية DOCX
        //    PhpWord يتولى htmlspecialchars داخلياً لكن القيم الغريبة
        //    من AI قد تتجاوز ذلك، لذا نُزيل الرموز الخام الخطيرة
        $s = str_replace(['<', '>', '&', '"', "'"], ['＜', '＞', '＆', '＂', '＇'], $s);
        // ملاحظة: نستبدل بالرموز اليابانية المشابهة (fullwidth) بدلاً من
        // htmlentities لأن PhpWord يُشفّر HTML entities كنص حرفي داخل DOCX

        return trim($s);
    }

    /**
     * تنظيف مع الحفاظ على الأسطر الجديدة وعلامات Markdown الأساسية بشكل مناسب للوورد
     */
    protected function toMultilineText($val): string
    {
        if ($val === null || $val === '') {
            return '';
        }
        
        $s = is_scalar($val) ? (string) $val : json_encode($val, JSON_UNESCAPED_UNICODE);
        
        // 1) إزالة محارف التحكم غير المرئية (عدا المسافة والسطر والتبويب)
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);

        // 2) تنظيف رموز Markdown الشائعة
        // Headers: ### text → text
        $s = preg_replace('/^#{1,6}\s*/mu', '', $s);
        // Bold/Italic: **text** → text, *text* → text, __text__ → text
        $s = preg_replace('/\*{1,3}([^*]+)\*{1,3}/u', '$1', $s);
        $s = preg_replace('/_{1,2}([^_]+)_{1,2}/u', '$1', $s);
        // Backticks: `code` → code
        $s = preg_replace('/`([^`]+)`/u', '$1', $s);
        // Links: [text](url) → text
        $s = preg_replace('/\[([^\]]+)\]\([^)]+\)/u', '$1', $s);
        // Markdown bullets at line start: - item or * item → • item
        $s = preg_replace('/^[\-\*]\s+/mu', '• ', $s);
        // Markdown horizontal rules
        $s = preg_replace('/^[-_*]{3,}$/mu', '', $s);

        // 3) إزالة محارف RTL/LTR المخفية
        $s = str_replace(["\xE2\x80\x8F", "\xE2\x80\x8E", "\xC2\xA0"], ' ', $s);

        // 4) تشفير رموز XML الخاصة
        $s = str_replace(['<', '>', '&', '"', "'"], ['＜', '＞', '＆', '＂', '＇'], $s);

        // 5) تحويل الأسطر إلى فواصل أسطر خاصة بـ Word
        // تحويل أي نوع من الأسطر المتتالية إلى فاصل وورد
        $s = str_replace(["\r\n", "\n\r", "\n", "\r"], '<w:br/>', $s);

        // إزالة الفواصل المزدوجة المتعددة للحفاظ على التنسيق
        $s = preg_replace('/(<w:br\/>\s*){3,}/', '<w:br/><w:br/>', $s);

        return $s;
    }

    // ✅ لتصميم الاختبار (وثيقة تحليل المحتوى - الوورد الأول)
    public function buildTestDesignWordAnalysis(array $data, string $targetRel): array
    {
        $templateRel = 'templates/test_design_analysis_template.docx';
        $templateAbs = rtrim(FCPATH, '/') . '/' . ltrim($templateRel, '/');

        if (!is_file($templateAbs)) {
            throw new \RuntimeException('Template not found: ' . $templateAbs);
        }

        $targetAbs = rtrim(FCPATH, '/') . '/' . ltrim($targetRel, '/');
        $targetDir = dirname($targetAbs);
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        $tp = new TemplateProcessor($templateAbs);
        $templateVariables = method_exists($tp, 'getVariables') ? $tp->getVariables() : [];

        // الحقول الفردية
        $singleFields = [
            'teacher_name', 'school_name', 'school_class',
            'general_subject', 'general_unit', 'unit_pages',
            'lessons_count', 'sessions_count', 'outcomes_count',
            'semester', 'academic_year', 'date',
        ];

        foreach ($singleFields as $f) {
            if (in_array($f, $templateVariables, true)) {
                $tp->setValue($f, $this->toText($data[$f] ?? ''));
            }
        }

        // صفوف جدول تحليل المحتوى (متكرر)
        $analysisRows = $data['analysis_rows'] ?? [];
        if (!empty($analysisRows) && in_array('row_unit_number', $templateVariables, true)) {
            $rows = [];
            foreach ($analysisRows as $row) {
                $rows[] = [
                    'row_unit_number'  => $this->toText($row['unit_number']   ?? ''),
                    'row_unit_title'   => $this->toText($row['unit_title']    ?? ''),
                    'row_lesson_title' => $this->toText($row['lesson_title']  ?? ''),
                    'row_outcome'      => $this->toText($row['outcome']       ?? ''),
                    'row_higher_skills'=> $this->toText($row['higher_skills'] ?? ''),
                    'row_lower_skills' => $this->toText($row['lower_skills']  ?? ''),
                ];
            }
            $tp->cloneRowAndSetValues('row_unit_number', $rows);
        } else {
            // صف فارغ احتياطي
            if (in_array('row_unit_number', $templateVariables, true)) {
                $tp->cloneRow('row_unit_number', 1);
                foreach (['row_unit_number','row_unit_title','row_lesson_title','row_outcome','row_higher_skills','row_lower_skills'] as $v) {
                    $tp->setValue($v . '#1', '');
                }
            }
        }

        $tp->saveAs($targetAbs);

        if (!is_file($targetAbs)) {
            throw new \RuntimeException('Failed saving analysis DOCX: ' . $targetAbs);
        }

        return ['rel_path' => ltrim($targetRel, '/'), 'bytes' => filesize($targetAbs) ?: 0];
    }

    /**
     * ملء قالب Excel لجدول المواصفات
     * يفتح الملف القالب ويملأ خلايا الإدخال المحددة
     */
    public function fillExcelTemplate(array $data, string $targetRel): array
    {
        $templateRel = 'templates/test_design_specification_template.xlsx';
        $templateAbs = rtrim(FCPATH, '/') . '/' . ltrim($templateRel, '/');

        if (!is_file($templateAbs)) {
            throw new \RuntimeException('Excel template not found: ' . $templateAbs);
        }

        $targetAbs = rtrim(FCPATH, '/') . '/' . ltrim($targetRel, '/');
        $targetDir = dirname($targetAbs);
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        // التحقق من توفر PhpSpreadsheet
        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            throw new \RuntimeException('PhpSpreadsheet غير مثبتة. شغّل: composer require phpoffice/phpspreadsheet');
        }

        $ioFactoryClass = '\PhpOffice\PhpSpreadsheet\IOFactory';
        $spreadsheet = $ioFactoryClass::load($templateAbs);
        $sheet = $spreadsheet->getActiveSheet();

        // ملء خلايا الرأس (اضبط هذه الخلايا حسب ملف Excel الفعلي)
        // كل خلية بعد العنوان مباشرة في الجدول
        $sheet->setCellValue('C3', $data['teacher_name']   ?? '');
        $sheet->setCellValue('G3', $data['school_class']   ?? '');
        $sheet->setCellValue('C4', $data['semester']       ?? '');
        $sheet->setCellValue('G4', $data['academic_year']  ?? '');
        $sheet->setCellValue('C5', $data['general_subject']?? '');
        $sheet->setCellValue('C2', $data['school_name']    ?? '');

        // عدد أسئلة الاختبار الكلي (L10)
        $totalQ = (int)($data['total_exam_questions'] ?? 30);
        $sheet->setCellValue('L10', $totalQ);

        // صفوف جدول المواصفات (بدءاً من الصف 13)
        $specRows = $data['specification_rows'] ?? [];
        $startRow = 13;

        foreach ($specRows as $i => $row) {
            $r = $startRow + $i;
            $sheet->setCellValue('A' . $r, $row['lesson_title']    ?? '');
            $sheet->setCellValue('B' . $r, (int)($row['sessions_count']    ?? 0));
            $sheet->setCellValue('C' . $r, (int)($row['bloom_remember']    ?? 0));
            $sheet->setCellValue('D' . $r, (int)($row['bloom_understand']  ?? 0));
            $sheet->setCellValue('E' . $r, (int)($row['bloom_apply']       ?? 0));
            $sheet->setCellValue('F' . $r, (int)($row['bloom_create']      ?? 0));
            $sheet->setCellValue('G' . $r, (int)($row['bloom_analyze']     ?? 0));
            $sheet->setCellValue('H' . $r, (int)($row['bloom_evaluate']    ?? 0));
        }

        // حفظ الملف
        $ioFactoryClass = '\PhpOffice\PhpSpreadsheet\IOFactory';
        $writer = $ioFactoryClass::createWriter($spreadsheet, 'Xlsx');
        $writer->save($targetAbs);

        if (!is_file($targetAbs)) {
            throw new \RuntimeException('Failed saving Excel: ' . $targetAbs);
        }

        return ['rel_path' => ltrim($targetRel, '/'), 'bytes' => filesize($targetAbs) ?: 0];
    }

    /**
     * بناء وثيقة الاختبار (الوورد الثالث) باستخدام PhpWord مباشرة
     */
    public function buildExamDocx(array $data, string $targetRel): array
    {
        $targetAbs = rtrim(FCPATH, '/') . '/' . ltrim($targetRel, '/');
        $targetDir = dirname($targetAbs);
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        if (!class_exists('\PhpOffice\PhpWord\PhpWord')) {
            throw new \RuntimeException('PhpWord غير مثبتة.');
        }

        $phpWord = new \PhpOffice\PhpWord\PhpWord();

        // إعدادات RTL للقسم
        $sectionStyle = [
            'marginTop'    => 720, 'marginBottom' => 720,
            'marginLeft'   => 1080, 'marginRight' => 1080,
            'bidi'         => true,
        ];
        $section = $phpWord->addSection($sectionStyle);

        // أنماط الخط
        $phpWord->addFontStyle('title', ['name' => 'Traditional Arabic', 'size' => 14, 'bold' => true]);
        $phpWord->addFontStyle('header', ['name' => 'Traditional Arabic', 'size' => 12, 'bold' => true]);
        $phpWord->addFontStyle('body', ['name' => 'Traditional Arabic', 'size' => 11]);
        $phpWord->addFontStyle('bold', ['name' => 'Traditional Arabic', 'size' => 11, 'bold' => true]);

        $centerPar = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'rtl' => true];
        $rightPar  = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT, 'rtl' => true];

        // ===== رأس الصفحة =====
        $section->addText(
            'المملكة الأردنية الهاشمية - مديرية التربية والتعليم',
            'title', $centerPar
        );
        $section->addText(
            'اسم المعلم: ' . ($data['teacher_name'] ?? '') .
            '    المادة: ' . ($data['general_subject'] ?? '') .
            '    الصف: ' . ($data['school_class'] ?? ''),
            'header', $rightPar
        );
        $section->addText(
            'الوحدة: ' . ($data['general_unit'] ?? '') .
            '    الفصل: ' . ($data['semester'] ?? '') .
            '    التاريخ: ' . ($data['date'] ?? date('Y/m/d')),
            'header', $rightPar
        );

        // خط فاصل
        $section->addTextBreak(1);

        // ===== عنوان الاختبار =====
        $section->addText(
            'اختبار وحدة: ' . ($data['general_unit'] ?? ''),
            ['name' => 'Traditional Arabic', 'size' => 14, 'bold' => true],
            $centerPar
        );

        // الزمن والدرجة
        $section->addText(
            'الزمن: ' . ($data['exam_time'] ?? '') .
            '    الدرجة الكلية: ' . ($data['exam_total_marks'] ?? ''),
            'bold', $rightPar
        );
        $section->addTextBreak(1);

        // ===== التعليمات =====
        $section->addText('تعليمات الاختبار:', 'bold', $rightPar);
        $instructions = $data['exam_instructions'] ?? '';
        if (!empty($instructions)) {
            $section->addText($instructions, 'body', $rightPar);
        }
        $section->addTextBreak(1);

        // ===== أسئلة الاختبار =====
        $examSections = $data['exam_sections'] ?? [];
        foreach ($examSections as $sec) {
            // عنوان القسم
            $secTitle = ($sec['section_title'] ?? '') .
                (!empty($sec['section_marks']) ? '  (' . $sec['section_marks'] . ' درجة)' : '');
            $section->addText($secTitle, 'header', $rightPar);

            // تعليمات القسم
            if (!empty($sec['section_instruction'])) {
                $section->addText($sec['section_instruction'], 'body', $rightPar);
            }

            // الأسئلة
            $questions = $sec['questions'] ?? [];
            foreach ($questions as $q) {
                $qText = ($q['number'] ?? '') . '. ' . ($q['text'] ?? '');
                if (!empty($q['marks']) && $q['marks'] > 0) {
                    $qText .= '  (' . $q['marks'] . ' درجة)';
                }
                $section->addText($qText, 'body', $rightPar);

                // خيارات الاختيار من متعدد
                $options = $q['options'] ?? [];
                if (!empty($options)) {
                    foreach ($options as $opt) {
                        $section->addText('          ' . $this->toText($opt), 'body', $rightPar);
                    }
                }

                $section->addTextBreak(1);
            }

            $section->addTextBreak(1);
        }

        // حفظ الملف
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($targetAbs);

        if (!is_file($targetAbs)) {
            throw new \RuntimeException('Failed saving exam DOCX: ' . $targetAbs);
        }

        return ['rel_path' => ltrim($targetRel, '/'), 'bytes' => filesize($targetAbs) ?: 0];
    }

    /**
     * استبدال متغير template بشكل آمن مع التعامل مع المسافات في الأسماء
     * 
     * @param TemplateProcessor $tp
     * @param string $varName اسم المتغير (بدون مسافات)
     * @param string $value القيمة
     * @param array $templateVariables قائمة المتغيرات من template
     * @param array $variableMapping خريطة المتغيرات المنظفة إلى الأصلية
     */
    protected function setValueSafely($tp, string $varName, string $value, array $templateVariables, array $variableMapping): void
    {
        // محاولة 1: الاستبدال بالاسم الأصلي (مع مسافات إذا كان موجوداً)
        $originalVar = $variableMapping[$varName] ?? $varName;
        try {
            $tp->setValue($originalVar, $value);
            return;
        } catch (\Exception $e) {
            // إذا فشل، نحاول بالأسماء البديلة
        }

        // محاولة 2: البحث عن المتغير في القائمة الأصلية (قد يكون مع مسافات)
        foreach ($templateVariables as $templateVar) {
            $cleaned = trim(rtrim($templateVar, '$'));
            if ($cleaned === $varName) {
                // وجدنا المتغير في template مع مسافات، نستخدم الاسم الأصلي
                try {
                    $tp->setValue($templateVar, $value);
                    return;
                } catch (\Exception $e) {
                    // إذا فشل، نستمر للمحاولة التالية
                }
            }
        }

        // محاولة 3: الاستبدال بالاسم المنظف (بدون مسافات)
        try {
            $tp->setValue($varName, $value);
        } catch (\Exception $e) {
            // إذا فشل، نسجل تحذير
            log_message('warning', "Failed to set template variable '{$varName}': " . $e->getMessage());
        }
    }

    /**
     * ✅ بناء تقرير التغذية الراجعة من القالب مباشرة عبر XML
     * لا يحتاج placeholders — يستبدل النصوص الثابتة ويضيف نقاط القوة/التطوير
     *
     * @param array $data بيانات التقرير من makeTemplateDataForDocx
     * @param string $targetRel المسار النسبي للملف الناتج
     * @return array ['rel_path' => ..., 'bytes' => ...]
     */
    public function buildFeedbackReport(array $data, string $targetRel): array
    {
        $templateAbs = rtrim(FCPATH, '/') . '/templates/F_FB_template.docx';
        if (!is_file($templateAbs)) {
            throw new \RuntimeException('Feedback template not found: ' . $templateAbs);
        }

        $targetAbs = rtrim(FCPATH, '/') . '/' . ltrim($targetRel, '/');
        $targetDir = dirname($targetAbs);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        // نسخ القالب
        copy($templateAbs, $targetAbs);

        $zip = new \ZipArchive();
        if ($zip->open($targetAbs) !== true) {
            throw new \RuntimeException('Cannot open feedback DOCX: ' . $targetAbs);
        }

        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            $zip->close();
            throw new \RuntimeException('Cannot read document.xml from feedback template');
        }

        $esc = function($text) {
            return htmlspecialchars((string)$text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        };

        // === استبدال الحقول عبر DOM ===
        
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        libxml_use_internal_errors(true);
        $dom->loadXML($xml);
        libxml_clear_errors();
        $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

        $this->domReplaceTextInDocument($dom, $ns, [
            '${student_name}'         => ($data['student_name'] ?? ''),
            '${student_id}'           => ($data['student_id'] ?? ''),
            '${overall_grade_word}'   => ($data['overall_grade_word'] ?? ''),
            '${overall_grade_symbol}' => ($data['overall_grade_symbol'] ?? ''),
            '${teacher_supervisor}'   => ($data['teacher_supervisor'] ?? ''),
            '${date}'                 => ($data['date'] ?? date('Y-m-d')),
        ]);

        $xml = $dom->saveXML();
        $xml = preg_replace('/^<\?xml[^>]*>\n?/', '', $xml);

        // fallback legacy: لضمان التوافق مع القوالب القديمة التي لا تحتوي على المتغيرات
        $studentName = $esc($data['student_name'] ?? '');
        $xml = $this->xmlInsertAfterText($xml, 'ثلاثيا', ' ' . $studentName);

        $studentId = $esc($data['student_id'] ?? '');
        $xml = $this->xmlInsertAfterText($xml, 'رقم المعلم الطالب:', ' ' . $studentId);

        $gradeWord = $esc($data['overall_grade_word'] ?? '');
        $gradeSymbol = $esc($data['overall_grade_symbol'] ?? '');
        $xml = $this->xmlInsertAfterText($xml, ':  ', $gradeWord . ' (' . $gradeSymbol . ')', '(لفظًا ورمزًا)');

        $supervisor = $esc($data['teacher_supervisor'] ?? '');
        $xml = $this->xmlReplaceText($xml, 'عبدالرزاق', $supervisor);
        $xml = $this->xmlReplaceText($xml, ' الخطيب', '');

        $date = $esc($data['date'] ?? date('Y-m-d'));
        $xml = $this->xmlInsertAfterText($xml, 'التاريخ:', ' ' . $date);

        // 6. نقاط القوة — نبني فقرات bullet بعد "نقاط القوة"
        $strengths = $data['strengths'] ?? [];
        if (!empty($strengths)) {
            $bulletXml = '';
            foreach ($strengths as $item) {
                $text = is_array($item) ? ($item['text'] ?? '') : (string)$item;
                $text = $esc(trim($text));
                if (!empty($text)) {
                    $bulletXml .= $this->xmlBulletParagraph($text);
                }
            }
            // نبحث عن الفقرة التي تحتوي "نقاط القوة" ونضيف الفقرات بعدها
            $xml = $this->xmlInsertParagraphsAfter($xml, 'نقاط القوة', $bulletXml);
        }

        // 7. مجالات التطوير
        $developments = $data['areas_for_development'] ?? [];
        if (!empty($developments)) {
            $bulletXml = '';
            foreach ($developments as $item) {
                $text = is_array($item) ? ($item['text'] ?? '') : (string)$item;
                $text = $esc(trim($text));
                if (!empty($text)) {
                    $bulletXml .= $this->xmlBulletParagraph($text);
                }
            }
            $xml = $this->xmlInsertParagraphsAfter($xml, 'المجالات التي تحتاج الى تطوير', $bulletXml);
        }

        // 8. ملاحظات عامة — نستبدل "- " بالنص
        $generalNotes = $esc($data['general_notes'] ?? '');
        if (!empty($generalNotes)) {
            $xml = $this->xmlReplaceText($xml, '- ', $generalNotes);
        }

        // تنظيف أي متغيرات ${...} متبقية
        $xml = $this->cleanPlaceholders($xml);

        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();

        if (!is_file($targetAbs)) {
            throw new \RuntimeException('Failed saving feedback report: ' . $targetAbs);
        }

        return [
            'rel_path' => ltrim($targetRel, '/'),
            'bytes'    => filesize($targetAbs) ?: 0,
        ];
    }

    /**
     * ✅ بناء تقرير التغذية الراجعة الختامي للمقالة التأملية (final_essay_evaluator_v1)
     * القالب RE_FB_template.docx يحتوي placeholders كاملة (بما فيها ${general_notes})
     * — لا يحتاج الحقن النصي القديم (legacy fallbacks) المستخدم في قالب الاستقصاء.
     *
     * @param array $data بيانات التقرير (student_name, student_id, teacher_supervisor,
     *                    date, overall_grade_word/symbol, strengths, areas_for_development, general_notes)
     * @param string $targetRel المسار النسبي للملف الناتج
     * @return array ['rel_path' => ..., 'bytes' => ...]
     */
    public function buildFinalEssayFeedbackReport(array $data, string $targetRel): array
    {
        $templateAbs = rtrim(FCPATH, '/') . '/templates/RE_FB_template.docx';
        if (!is_file($templateAbs)) {
            throw new \RuntimeException('Final essay feedback template not found: ' . $templateAbs);
        }

        $targetAbs = rtrim(FCPATH, '/') . '/' . ltrim($targetRel, '/');
        $targetDir = dirname($targetAbs);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        copy($templateAbs, $targetAbs);

        $zip = new \ZipArchive();
        if ($zip->open($targetAbs) !== true) {
            throw new \RuntimeException('Cannot open final essay feedback DOCX: ' . $targetAbs);
        }

        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            $zip->close();
            throw new \RuntimeException('Cannot read document.xml from final essay feedback template');
        }

        $esc = function ($text) {
            return htmlspecialchars((string) $text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        };

        // === استبدال الحقول عبر DOM (يتعامل مع النص المُجزّأ بين عدة w:t) ===
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        libxml_use_internal_errors(true);
        $dom->loadXML($xml);
        libxml_clear_errors();
        $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

        $this->domReplaceTextInDocument($dom, $ns, [
            '${student_name}'         => ($data['student_name'] ?? ''),
            '${student_id}'           => ($data['student_id'] ?? ''),
            '${overall_grade_word}'   => ($data['overall_grade_word'] ?? ''),
            '${overall_grade_symbol}' => ($data['overall_grade_symbol'] ?? ''),
            '${teacher_supervisor}'   => ($data['teacher_supervisor'] ?? ''),
            '${date}'                 => ($data['date'] ?? date('Y-m-d')),
            '${general_notes}'        => ($data['general_notes'] ?? ''),
        ]);

        $xml = $dom->saveXML();
        $xml = preg_replace('/^<\?xml[^>]*>\n?/', '', $xml);

        // نقاط القوة — فقرات bullet بعد عنوان "نقاط القوة"
        $strengths = $data['strengths'] ?? [];
        if (!empty($strengths)) {
            $bulletXml = '';
            foreach ($strengths as $item) {
                $text = is_array($item) ? ($item['text'] ?? '') : (string) $item;
                $text = $esc(trim($text));
                if (!empty($text)) {
                    $bulletXml .= $this->xmlBulletParagraph($text);
                }
            }
            $xml = $this->xmlInsertParagraphsAfter($xml, 'نقاط القوة', $bulletXml);
        }

        // مجالات التطوير — فقرات bullet بعد عنوانها
        $developments = $data['areas_for_development'] ?? [];
        if (!empty($developments)) {
            $bulletXml = '';
            foreach ($developments as $item) {
                $text = is_array($item) ? ($item['text'] ?? '') : (string) $item;
                $text = $esc(trim($text));
                if (!empty($text)) {
                    $bulletXml .= $this->xmlBulletParagraph($text);
                }
            }
            $xml = $this->xmlInsertParagraphsAfter($xml, 'المجالات التي تحتاج الى تطوير', $bulletXml);
        }

        // تنظيف أي متغيرات ${...} متبقية
        $xml = $this->cleanPlaceholders($xml);

        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();

        if (!is_file($targetAbs)) {
            throw new \RuntimeException('Failed saving final essay feedback report: ' . $targetAbs);
        }

        return [
            'rel_path' => ltrim($targetRel, '/'),
            'bytes'    => filesize($targetAbs) ?: 0,
        ];
    }

    /**
     * ✅ بناء جدول الروبريك من القالب مباشرة عبر DOMDocument
     * يملأ الترويسة ويُظلل خلية المستوى المحقق بلون أصفر
     * يعتمد على موضع الخلية في الجدول (صف × عمود) وليس البحث النصي
     *
     * @param array $data بيانات الروبريك
     * @param string $targetRel المسار النسبي للملف الناتج
     * @param string $templateName اسم قالب الروبريك داخل templates/ (افتراضياً قالب الاستقصاء)
     * @return array ['rel_path' => ..., 'bytes' => ...]
     */
    public function buildRubricReport(array $data, string $targetRel, string $templateName = 'R_template.docx'): array
    {
        $templateAbs = rtrim(FCPATH, '/') . '/templates/' . $templateName;
        if (!is_file($templateAbs)) {
            throw new \RuntimeException('Rubric template not found: ' . $templateAbs);
        }

        $targetAbs = rtrim(FCPATH, '/') . '/' . ltrim($targetRel, '/');
        $targetDir = dirname($targetAbs);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        copy($templateAbs, $targetAbs);

        $zip = new \ZipArchive();
        if ($zip->open($targetAbs) !== true) {
            throw new \RuntimeException('Cannot open rubric DOCX: ' . $targetAbs);
        }

        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            $zip->close();
            throw new \RuntimeException('Cannot read document.xml from rubric template');
        }

        // === تحليل XML بـ DOMDocument ===
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        
        // كتم تحذيرات namespace
        libxml_use_internal_errors(true);
        $dom->loadXML($xml);
        libxml_clear_errors();

        $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

        // === 1. ملء حقول الترويسة ===
        $this->domReplaceTextInDocument($dom, $ns, [
            '${student_name}'         => ($data['student_name'] ?? ''),
            '${student_id}'           => ($data['student_id'] ?? ''),
            '${date}'                 => ($data['date'] ?? date('Y-m-d')),
            '${overall_grade_word}'   => ($data['overall_grade_word'] ?? ''),
            '${overall_grade_symbol}' => ($data['overall_grade_symbol'] ?? ''),
            '${teacher_supervisor}'   => ($data['teacher_supervisor'] ?? ''),
        ]);

        // === 2. تظليل خلايا الروبريك ===
        // خريطة: criterion_id => grade_symbol
        $rubricGrades = [];
        foreach (($data['rubric_grades_raw'] ?? []) as $grade) {
            $id = (int)($grade['criterion_id'] ?? 0);
            $symbol = $grade['grade_symbol'] ?? '';
            if ($id > 0 && !empty($symbol)) {
                $rubricGrades[$id] = $symbol;
            }
        }

        // ترتيب الأعمدة في الجدول: [المعيار] [P] [GP] [M] [D] [F]
        $levelToCol = ['P' => 1, 'GP' => 2, 'M' => 3, 'D' => 4, 'F' => 5];

        // إيجاد الجدول الرئيسي (الأول الذي يحتوي أكثر من 4 صفوف = جدول المعايير)
        $tables = $dom->getElementsByTagNameNS($ns, 'tbl');
        $mainTable = null;

        for ($t = 0; $t < $tables->length; $t++) {
            $tbl = $tables->item($t);
            $rows = [];
            foreach ($tbl->childNodes as $child) {
                if ($child->localName === 'tr') {
                    $rows[] = $child;
                }
            }
            // الجدول الرئيسي يحتوي أكثر من 8 صفوف (ترويسة + 4 مجالات رئيسية + 8 معايير فرعية)
            if (count($rows) > 5) {
                $mainTable = $tbl;
                break;
            }
        }

        if ($mainTable !== null) {
            // جمع كل صفوف الجدول
            $tableRows = [];
            foreach ($mainTable->childNodes as $child) {
                if ($child->localName === 'tr') {
                    $tableRows[] = $child;
                }
            }

            // خريطة المعايير إلى أرقام الصفوف في الجدول
            // نحتاج معرفة ترتيب الصفوف. نبحث عن صفوف تحتوي نصوص المعايير
            $criterionRowMap = $this->findCriterionRows($tableRows, $ns);

            // تظليل الخلايا المطابقة
            foreach ($rubricGrades as $criterionId => $gradeSymbol) {
                if (!isset($criterionRowMap[$criterionId]) || !isset($levelToCol[$gradeSymbol])) {
                    continue;
                }

                $rowIndex = $criterionRowMap[$criterionId];
                $colIndex = $levelToCol[$gradeSymbol];

                if ($rowIndex < count($tableRows)) {
                    $row = $tableRows[$rowIndex];
                    $cells = [];
                    foreach ($row->childNodes as $child) {
                        if ($child->localName === 'tc') {
                            $cells[] = $child;
                        }
                    }

                    if ($colIndex < count($cells)) {
                        $this->domHighlightCell($cells[$colIndex], $dom, $ns);
                    }
                }
            }
        }

        // حفظ XML المعدّل مع تنظيف المتغيرات
        $newXml = $dom->saveXML();
        $newXml = $this->cleanPlaceholders($newXml);
        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', $newXml);
        $zip->close();

        if (!is_file($targetAbs)) {
            throw new \RuntimeException('Failed saving rubric report: ' . $targetAbs);
        }

        return [
            'rel_path' => ltrim($targetRel, '/'),
            'bytes'    => filesize($targetAbs) ?: 0,
        ];
    }

    /**
     * استبدال نصوص في المستند عبر DOMDocument
     * يتعامل مع النص المقسم بين عدة <w:t>
     */
    protected function domReplaceTextInDocument(\DOMDocument $dom, string $ns, array $replacements): void
    {
        // جمع كل الفقرات
        $paragraphs = $dom->getElementsByTagNameNS($ns, 'p');

        foreach ($replacements as $search => $replace) {
            $found = false;
            for ($i = 0; $i < $paragraphs->length && !$found; $i++) {
                $para = $paragraphs->item($i);
                
                // جمع كل نصوص الفقرة
                $runs = [];
                $fullText = '';
                $textNodes = [];
                
                foreach ($para->childNodes as $child) {
                    if ($child->localName === 'r') {
                        foreach ($child->childNodes as $rc) {
                            if ($rc->localName === 't') {
                                $fullText .= $rc->textContent;
                                $textNodes[] = $rc;
                            }
                        }
                    }
                }

                // هل يحتوي النص الكامل على النص المطلوب؟
                if (mb_strpos($fullText, $search) !== false) {
                    // استبدال: نضع النص الجديد في أول <w:t> ونفرّغ الباقي
                    $newText = str_replace($search, $replace, $fullText);
                    
                    if (!empty($textNodes)) {
                        $textNodes[0]->textContent = $newText;
                        // فرّغ بقية الـ text nodes
                        for ($j = 1; $j < count($textNodes); $j++) {
                            $textNodes[$j]->textContent = '';
                        }
                    }
                    $found = true;
                }
            }
        }
    }

    /**
     * إيجاد أرقام صفوف المعايير الثمانية في الجدول
     */
    protected function findCriterionRows(array $rows, string $ns): array
    {
        $map = [];
        
        // نصوص مميزة لكل معيار (قصيرة وفريدة)
        $markers = [
            1 => 'المعرفة والفهم',        // (أ) أول ظهور
            2 => 'تطبيق المعرفة',          // (ب)
            3 => 'التفكير الناقد',          // (أ) تحت المهارات المعرفية  
            4 => 'التأمل الناقد',           // (ب)
            5 => 'المبادرة وحل المشكلات',   // (أ) تحت المهارات المهنية
            6 => 'فنون التدريس',           // (ب)
            7 => 'الاستقصاء المبني',        // (ج)
            8 => 'العرض والتواصل',          // مهارات العرض
        ];

        // لكل صف، نجمع نصه الكامل
        foreach ($rows as $rowIdx => $row) {
            $rowText = $this->domGetFullText($row, $ns);
            
            // نتحقق إذا هذا الصف يحتوي على 6 خلايا (معيار + 5 مستويات)
            $cellCount = 0;
            foreach ($row->childNodes as $ch) {
                if ($ch->localName === 'tc') $cellCount++;
            }
            
            // صفوف المعايير الفرعية تحتوي 6 خلايا (اسم المعيار + P + GP + M + D + F)
            if ($cellCount < 5) continue;
            
            foreach ($markers as $criterionId => $marker) {
                if (!isset($map[$criterionId]) && mb_strpos($rowText, $marker) !== false) {
                    // تحقق إضافي: تأكد أن هذا صف معيار وليس صف عنوان المجال
                    // صفوف عنوان المجال عادة تحتوي فقط على اسم المجال بدون وصف مستويات أداء
                    // صفوف المعايير الفرعية تحتوي أوصاف مستويات الأداء (نص أطول)
                    if (mb_strlen($rowText) > 100) { // صف معيار يحتوي أوصاف طويلة
                        $map[$criterionId] = $rowIdx;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * جمع كل النص من عنصر DOM
     */
    protected function domGetFullText(\DOMNode $node, string $ns): string
    {
        $text = '';
        if ($node->localName === 't' && $node->namespaceURI === $ns) {
            return $node->textContent;
        }
        foreach ($node->childNodes as $child) {
            $text .= $this->domGetFullText($child, $ns);
        }
        return $text;
    }

    /**
     * إضافة تظليل أصفر لخلية جدول عبر DOM
     */
    protected function domHighlightCell(\DOMNode $cell, \DOMDocument $dom, string $ns): void
    {
        // البحث عن <w:tcPr> أو إنشاؤه
        $tcPr = null;
        foreach ($cell->childNodes as $child) {
            if ($child->localName === 'tcPr') {
                $tcPr = $child;
                break;
            }
        }

        if ($tcPr === null) {
            $tcPr = $dom->createElementNS($ns, 'w:tcPr');
            $cell->insertBefore($tcPr, $cell->firstChild);
        }

        // حذف أي <w:shd> موجود
        $existingShd = [];
        foreach ($tcPr->childNodes as $child) {
            if ($child->localName === 'shd') {
                $existingShd[] = $child;
            }
        }
        foreach ($existingShd as $shd) {
            $tcPr->removeChild($shd);
        }

        // إضافة تظليل أصفر
        $shd = $dom->createElementNS($ns, 'w:shd');
        $shd->setAttribute('w:val', 'clear');
        $shd->setAttribute('w:color', 'auto');
        $shd->setAttribute('w:fill', 'FFFF00');
        $tcPr->appendChild($shd);
    }

    /**
     * تنظيف كل متغيرات ${...} المتبقية من XML
     * يتعامل مع حالتين:
     * 1. المتغير في <w:t> واحد: ${variable_name}
     * 2. المتغير مقسم بين عدة <w:t>: ${ في واحد و variable} في آخر
     */
    protected function cleanPlaceholders(string $xml): string
    {
        // الحالة 1: المتغير كامل داخل <w:t> واحد
        // مثال: <w:t>${student_name}</w:t> → <w:t></w:t>
        $xml = preg_replace('/\$\{[a-zA-Z0-9_]+\}/u', '', $xml);

        // الحالة 2: المتغير مقسم — $ في نهاية <w:t> و {name} في بداية <w:t> آخر
        // أو ${name مقسم بأي شكل
        // نحذف أي $ متبوعة بـ { حتى لو بينهما XML tags
        $xml = preg_replace('/\$\{[^}]*\}/u', '', $xml);

        return $xml;
    }

    // ──── XML Helper Methods ────

    /**
     * إدراج نص بعد نص معين في أول <w:t> يحتويه
     */
    protected function xmlInsertAfterText(string $xml, string $searchText, string $insertText, string $contextText = ''): string
    {
        $escaped = preg_quote($searchText, '/');
        
        if (!empty($contextText)) {
            // ابحث عن النص القريب من سياق معين
            $contextEsc = preg_quote($contextText, '/');
            $pos = mb_strpos($xml, $contextText);
            if ($pos !== false) {
                // ابحث بعد السياق
                $afterContext = mb_substr($xml, $pos);
                $pattern = '/(<w:t[^>]*>)(' . $escaped . ')(<\/w:t>)/u';
                if (preg_match($pattern, $afterContext, $m, PREG_OFFSET_CAPTURE)) {
                    $matchPos = $pos + $m[0][1];
                    $matchLen = strlen($m[0][0]);
                    $replacement = $m[1][0] . $m[2][0] . $insertText . $m[3][0];
                    $xml = substr_replace($xml, $replacement, $matchPos, $matchLen);
                    return $xml;
                }
            }
        }
        
        $pattern = '/(<w:t[^>]*>' . $escaped . ')(<\/w:t>)/u';
        $xml = preg_replace($pattern, '$1' . $insertText . '$2', $xml, 1);
        return $xml;
    }

    /**
     * استبدال نص في أول <w:t> يحتويه
     */
    protected function xmlReplaceText(string $xml, string $searchText, string $replacement): string
    {
        $escaped = preg_quote($searchText, '/');
        $pattern = '/(<w:t[^>]*>)([^<]*?)' . $escaped . '([^<]*?)(<\/w:t>)/u';
        $xml = preg_replace($pattern, '${1}${2}' . $replacement . '${3}${4}', $xml, 1);
        return $xml;
    }

    /**
     * إنشاء فقرة bullet في OOXML
     */
    protected function xmlBulletParagraph(string $text): string
    {
        return '<w:p><w:pPr><w:bidi/><w:jc w:val="right"/></w:pPr>'
             . '<w:r><w:rPr><w:rFonts w:cs="Traditional Arabic"/><w:sz w:val="24"/><w:szCs w:val="24"/><w:rtl/></w:rPr>'
             . '<w:t xml:space="preserve">• ' . $text . '</w:t>'
             . '</w:r></w:p>';
    }

    /**
     * إدراج فقرات XML بعد الفقرة التي تحتوي نص معين
     */
    protected function xmlInsertParagraphsAfter(string $xml, string $searchText, string $paragraphsXml): string
    {
        // البحث عن الفقرة <w:p>...</w:p> التي تحتوي searchText
        $pattern = '/(<w:p\b[^>]*>(?:(?!<w:p\b).)*?' . preg_quote($searchText, '/') . '.*?<\/w:p>)/su';
        
        if (preg_match($pattern, $xml, $m)) {
            $fullMatch = $m[0];
            $xml = str_replace($fullMatch, $fullMatch . $paragraphsXml, $xml);
        }
        
        return $xml;
    }

    /**
     * تظليل خلية جدول تحتوي نص معين بلون أصفر
     */
    protected function xmlHighlightCellContaining(string $xml, string $searchText): string
    {
        $escaped = preg_quote($searchText, '/');
        
        // البحث عن <w:tc>..searchText..</w:tc>
        $pattern = '/(<w:tc\b[^>]*>)(.*?' . $escaped . '.*?)(<\/w:tc>)/su';
        
        $xml = preg_replace_callback($pattern, function ($matches) {
            $tcOpen = $matches[1];
            $content = $matches[2];
            $tcClose = $matches[3];

            $shd = '<w:shd w:val="clear" w:color="auto" w:fill="FFFF00"/>';

            if (preg_match('/<w:tcPr>(.*?)<\/w:tcPr>/s', $content, $prMatch)) {
                if (strpos($prMatch[0], '<w:shd') !== false) {
                    $newTcPr = preg_replace('/<w:shd[^\/]*\/>/', $shd, $prMatch[0]);
                } else {
                    $newTcPr = str_replace('</w:tcPr>', $shd . '</w:tcPr>', $prMatch[0]);
                }
                $content = str_replace($prMatch[0], $newTcPr, $content);
            } else {
                $content = '<w:tcPr>' . $shd . '</w:tcPr>' . $content;
            }

            return $tcOpen . $content . $tcClose;
        }, $xml, 1); // أول تطابق فقط
        
        return $xml;
    }

    /**
     * تظليل خلايا الروبريك المطابقة بلون أصفر
     * تبحث عن خلايا تحتوي علامة ✓ وتُضيف خلفية صفراء لها عبر OOXML
     *
     * @param string $docxPath المسار الكامل لملف DOCX
     * @param array $data بيانات القالب (تحتوي criterion_N_LEVEL = ✓ أو فارغ)
     */
    protected function highlightRubricCells(string $docxPath, array $data): void
    {
        if (!is_file($docxPath)) {
            return;
        }

        try {
            $zip = new \ZipArchive();
            $result = $zip->open($docxPath);
            if ($result !== true) {
                log_message('warning', "Cannot open DOCX for rubric highlighting: {$docxPath}");
                return;
            }

            $documentXml = $zip->getFromName('word/document.xml');
            if ($documentXml === false) {
                $zip->close();
                return;
            }

            $modified = false;

            // البحث عن الخلايا التي تحتوي ✓ وإضافة تظليل أصفر
            // نمط: <w:tc>...<w:t>✓</w:t>...</w:tc>
            // نريد إضافة <w:shd w:val="clear" w:color="auto" w:fill="FFFF00"/> في <w:tcPr>

            // استخدام regex للعثور على <w:tc> التي تحتوي ✓
            $pattern = '/(<w:tc\b[^>]*>)(.*?<w:t[^>]*>✓<\/w:t>.*?)(<\/w:tc>)/su';

            $documentXml = preg_replace_callback($pattern, function ($matches) use (&$modified) {
                $tcOpen = $matches[1]; // <w:tc> أو <w:tc ...>
                $content = $matches[2];
                $tcClose = $matches[3];

                // التحقق من وجود <w:tcPr> مسبقاً
                if (preg_match('/<w:tcPr>(.*?)<\/w:tcPr>/s', $content, $prMatch)) {
                    // tcPr موجود — التحقق من وجود w:shd
                    if (strpos($prMatch[0], '<w:shd') !== false) {
                        // تحديث لون التظليل الموجود
                        $newTcPr = preg_replace(
                            '/<w:shd[^\/]*\/>/',
                            '<w:shd w:val="clear" w:color="auto" w:fill="FFFF00"/>',
                            $prMatch[0]
                        );
                    } else {
                        // إضافة w:shd داخل tcPr
                        $newTcPr = str_replace(
                            '</w:tcPr>',
                            '<w:shd w:val="clear" w:color="auto" w:fill="FFFF00"/></w:tcPr>',
                            $prMatch[0]
                        );
                    }
                    $content = str_replace($prMatch[0], $newTcPr, $content);
                } else {
                    // tcPr غير موجود — إضافته بعد <w:tc>
                    $content = '<w:tcPr><w:shd w:val="clear" w:color="auto" w:fill="FFFF00"/></w:tcPr>' . $content;
                }

                $modified = true;
                return $tcOpen . $content . $tcClose;
            }, $documentXml);

            if ($modified && $documentXml !== false) {
                $zip->deleteName('word/document.xml');
                $zip->addFromString('word/document.xml', $documentXml);
                log_message('info', "Applied yellow highlighting to rubric cells in: {$docxPath}");
            }

            $zip->close();
        } catch (\Exception $e) {
            log_message('error', "Error highlighting rubric cells in {$docxPath}: " . $e->getMessage());
        }
    }

    /**
     * تنظيف أي متغيرات template غير مستبدلة من ملف DOCX بعد الحفظ
     * 
     * @param string $docxPath المسار الكامل لملف DOCX
     */
    protected function cleanUnreplacedVariables(string $docxPath): void
    {
        if (!is_file($docxPath)) {
            return;
        }

        try {
            // فتح ملف DOCX كـ ZIP للقراءة والكتابة
            $zip = new \ZipArchive();
            // محاولة فتح الملف - إذا فشل، نغلق ونحاول مع CREATE
            $result = $zip->open($docxPath);
            if ($result !== true) {
                // محاولة أخرى مع CREATE للكتابة
                $result = $zip->open($docxPath, \ZipArchive::CREATE);
            }
            if ($result !== true) {
                log_message('warning', "Cannot open DOCX file for cleaning: {$docxPath} (error code: {$result})");
                return;
            }

            // قراءة word/document.xml
            $documentXml = $zip->getFromName('word/document.xml');
            if ($documentXml === false) {
                $zip->close();
                return;
            }

            $originalXml = $documentXml;
            $cleaned = false;

            // تنظيف الأنماط الشائعة للمتغيرات غير المستبدلة:
            // 1. }$ - علامة } متبوعة بـ $ (المشكلة الرئيسية المكتشفة)
            // نتعامل معها أولاً لأنها الأكثر وضوحاً
            if (strpos($documentXml, '}$') !== false) {
                // تنظيف }$ داخل <w:t>...</w:t> - الحالة المكتشفة: <w:t>supervisor</w:t><w:t>}$</w:t>
                // نمط 1: <w:t>text}$</w:t> - }$ في نفس العنصر
                $pattern1 = '/<w:t[^>]*>([^<]*?)\}[\s]*\$<\/w:t>/u';
                if (preg_match($pattern1, $documentXml)) {
                    $documentXml = preg_replace_callback($pattern1, function($matches) {
                        $before = trim($matches[1]);
                        // إذا كان النص قبل }$ فارغ، نترك <w:t> فارغاً
                        if (empty($before)) {
                            return '<w:t></w:t>';
                        }
                        // نزيل }$ ونبقي النص قبلها
                        return '<w:t>' . htmlspecialchars($before, ENT_XML1, 'UTF-8') . '</w:t>';
                    }, $documentXml);
                    $cleaned = true;
                }
                
                // تنظيف }$ في أي مكان آخر (خارج <w:t> tags)
                $documentXml = str_replace('}$', '', $documentXml);
                $cleaned = true;
            }

            // 1.5. تنظيف ${ variable_name} أو ${variable_name } أو ${ variable_name } (مع مسافات)
            $patternWithSpaces = '/\$\{\s*([^}]+?)\s*\}/u';
            if (preg_match($patternWithSpaces, $documentXml)) {
                // نزيل المتغيرات template غير المستبدلة (مع مسافات)
                $documentXml = preg_replace($patternWithSpaces, '', $documentXml);
                $cleaned = true;
            }

            // 2. ${...} - متغيرات template كاملة غير مستبدلة
            $pattern1 = '/\$\{([^}]+)\}/u';
            if (preg_match($pattern1, $documentXml)) {
                $documentXml = preg_replace($pattern1, '', $documentXml);
                $cleaned = true;
            }

            // 3. ${ أو ${variable بدون } (متغير template مكسور)
            $pattern3 = '/\$\{[^}]*$/u';
            if (preg_match($pattern3, $documentXml)) {
                $documentXml = preg_replace($pattern3, '', $documentXml);
                $cleaned = true;
            }

            // 4. تنظيف العناصر <w:t> الفارغة أو التي تحتوي فقط على } أو $ أو }$
            $pattern4 = '/<w:t[^>]*>[\s]*[\}\$]+[\s]*<\/w:t>/u';
            if (preg_match($pattern4, $documentXml)) {
                $documentXml = preg_replace($pattern4, '<w:t></w:t>', $documentXml);
                $cleaned = true;
            }

            // 5. $variable (بدون {}) - لكن بحذر شديد لتجنب حذف $ من XML attributes
            // نتحقق أن $variable داخل <w:t>...</w:t> فقط
            $pattern5 = '/<w:t[^>]*>([^<]*)\$([a-zA-Z_][a-zA-Z0-9_]*)([^<]*)<\/w:t>/u';
            if (preg_match($pattern5, $documentXml)) {
                $documentXml = preg_replace_callback($pattern5, function($matches) {
                    $before = trim($matches[1]);
                    $varName = $matches[2];
                    $after = trim($matches[3]);
                    // دمج النص مع حذف $variable
                    $content = trim($before . ' ' . $after);
                    return '<w:t>' . htmlspecialchars($content, ENT_XML1, 'UTF-8') . '</w:t>';
                }, $documentXml);
                $cleaned = true;
            }

            // إذا تم التنظيف، حفظ XML المعدل
            if ($cleaned && $documentXml !== $originalXml) {
                $zip->deleteName('word/document.xml');
                $zip->addFromString('word/document.xml', $documentXml);
                log_message('info', "Cleaned unreplaced template variables from DOCX: {$docxPath}");
            }

            // --- تنظيف بيانات المؤلف (Author Metadata) ---
            $coreXml = $zip->getFromName('docProps/core.xml');
            if ($coreXml !== false) {
                $originalCoreXml = $coreXml;
                
                // استبدال اسم صانع الملف باسم المنصة لمنع ظهور اسم منشئ القالب
                $coreXml = preg_replace('/<dc:creator>.*?<\/dc:creator>/s', '<dc:creator>منصة HOW</dc:creator>', $coreXml);
                $coreXml = preg_replace('/<cp:lastModifiedBy>.*?<\/cp:lastModifiedBy>/s', '<cp:lastModifiedBy>منصة HOW</cp:lastModifiedBy>', $coreXml);
                
                if ($coreXml !== $originalCoreXml) {
                    $zip->deleteName('docProps/core.xml');
                    $zip->addFromString('docProps/core.xml', $coreXml);
                }
            }

            $zip->close();
        } catch (\Exception $e) {
            log_message('error', "Error cleaning unreplaced variables in DOCX {$docxPath}: " . $e->getMessage());
            // لا نرمي exception لأن هذا تنظيف اختياري
        }
    }
}

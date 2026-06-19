<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AiJobTypesSeeder extends Seeder
{
    public function run()
    {
        $provider = env('AI_PROVIDER', 'gemini');
        $modelName = $provider === 'openai'
            ? env('AI_OPENAI_MODEL', 'gpt-4o-mini')
            : env('AI_GEMINI_MODEL', 'gemini-1.5-pro');

        $this->seedLessonPlanTool($provider, $modelName);
        $this->seedTaskMatrixTool($provider, $modelName);
        $this->seedForumReplyTool($provider, $modelName);
        $this->seedForumReplyToolV2($provider, $modelName);
        $this->seedReflectionTool($provider, $modelName);
        $this->seedCaseStudyTool($provider, $modelName);
        $this->seedArticleEvaluatorTool($provider, $modelName);
        $this->seedAcademicAnswerToolV2($provider, $modelName);
        $this->seedEvaluationIntegrationTool($provider, $modelName);
        $this->seedAssessmentPositionTool($provider, $modelName);
        $this->seedInquiryQuestionsTool($provider, $modelName);
        $this->seedInquiryPlanEvaluatorTool($provider, $modelName);
        $this->seedFormativeReflectiveEssayTool($provider, $modelName);
        $this->seedRewrittenReflectiveEssayTool($provider, $modelName);
    }

    protected function seedTaskMatrixTool(string $provider, string $modelName): void
    {
        $systemPrompt = <<<'PROMPT'
Act like مصمم مناهج متخصص في بناء مصفوفات محتوى ومهام متوافقة مع المناهج الأردنية الحديثة.
تحقق أولاً من اكتمال الحقول stage_level, teacher_name, unit_title, subject_name, lessons_count, curriculum_pages، وإلا فأعد JSON بـ need_more_info.

غير ذلك:
- وزّع محتوى الوحدة على عدد الحصص المحدد.
- لكل حصة: حدد المحتوى، المفاهيم/المصطلحات، التعميمات أو المبادئ، الأنشطة والمهارات، القيم/الاتجاهات، الوسائط (رسومات/جداول/تطبيقات)، بنود إضافية، ومهام الطلبة.
- التزم بالصفحات أو المصادر المحددة، واستخدم اللغة العربية الفصحى بنبرة مهنية.
PROMPT;

        $uiConfig = [
            'panel' => [
                'button' => [
                    'label' => 'مصفوفة المحتوى (AI)',
                    'icon' => 'fa-table',
                ],
                'status_labels' => [
                    'idle' => 'جاهز للتوليد',
                    'pending' => 'في قائمة الانتظار...',
                    'processing' => 'قيد المعالجة...',
                    'done' => 'تم إنشاء المصفوفة',
                    'failed' => 'تعذّر التنفيذ',
                ],
            ],
            'form' => [
                'modal_title' => 'إعداد مصفوفة المحتوى والمهام',
                'submit_label' => 'توليد المصفوفة',
                'sections' => [
                    [
                        'title' => 'البيانات الأساسية',
                        'layout' => 'grid-2',
                        'fields' => [
                            ['key' => 'teacher_name', 'type' => 'text', 'label' => 'اسم المعلم / الطالب', 'required' => true],
                            ['key' => 'stage_level', 'type' => 'text', 'label' => 'المرحلة التي يدرسها', 'required' => true, 'placeholder' => 'مثال: الصف التاسع الأساسي'],
                            ['key' => 'subject_name', 'type' => 'text', 'label' => 'المبحث', 'required' => true],
                            ['key' => 'unit_title', 'type' => 'text', 'label' => 'عنوان الوحدة / الدرس', 'required' => true],
                            ['key' => 'curriculum_pages', 'type' => 'text', 'label' => 'الصفحات / المصادر المعتمدة', 'placeholder' => 'مثال: ص 32-45', 'required' => true],
                            ['key' => 'lessons_count', 'type' => 'number', 'label' => 'عدد الحصص', 'min' => 1, 'max' => 12, 'default' => 4, 'required' => true],
                        ],
                    ],
                    [
                        'title' => 'تفاصيل إضافية',
                        'layout' => 'grid-2',
                        'fields' => [
                            ['key' => 'subject_brief', 'type' => 'textarea', 'label' => 'وصف مختصر للمادة / المحتوى العلمي', 'placeholder' => 'اذكر الفكرة الكبرى أو السياق العلمي للوحدة.'],
                            ['key' => 'time_frame', 'type' => 'text', 'label' => 'الإطار الزمني التقريبي', 'placeholder' => 'مثال: أسبوعان'],
                            ['key' => 'resources_hint', 'type' => 'textarea', 'label' => 'مصادر أو أدوات يجب استخدامها', 'hint' => 'اذكر الكتب، المختبرات، التطبيقات الرقمية...'],
                            ['key' => 'learning_focus', 'type' => 'textarea', 'label' => 'تركيز التعلم أو المهارات المطلوبة', 'placeholder' => 'مثال: التفكير الناقد، مهارات STEAM...'],
                        ],
                    ],
                    [
                        'title' => 'خصائص الطلبة وتعليمات خاصة',
                        'layout' => 'grid-1',
                        'fields' => [
                            ['key' => 'student_profile', 'type' => 'textarea', 'label' => 'خصائص الطلبة أو احتياجات خاصة (اختياري)'],
                            ['key' => 'custom_requirements', 'type' => 'textarea', 'label' => 'تعليمات أخرى للمصمم (اختياري)'],
                        ],
                    ],
                ],
            ],
            'result' => [
                'type' => 'json',
                'title' => 'مصفوفة المحتوى والمهام',
                'fields' => [
                    ['key' => 'stage_level', 'label' => 'المرحلة / الصف'],
                    ['key' => 'teacher_name', 'label' => 'اسم الطالب / المعلم'],
                    ['key' => 'subject_name', 'label' => 'المبحث'],
                    ['key' => 'unit_title', 'label' => 'عنوان الوحدة'],
                    ['key' => 'lessons_count', 'label' => 'عدد الحصص'],
                    ['key' => 'curriculum_pages', 'label' => 'الصفحات'],
                    ['key' => 'subject_brief', 'label' => 'وصف مختصر للمادة', 'render' => 'richtext'],
                    ['key' => 'summary', 'label' => 'ملخص الوحدة', 'render' => 'richtext'],
                    ['key' => 'matrix_col_content', 'label' => 'المحتوى', 'render' => 'richtext'],
                    ['key' => 'matrix_col_concepts', 'label' => 'المفاهيم والمصطلحات', 'render' => 'list'],
                    ['key' => 'matrix_col_principles', 'label' => 'التعميمات / المبادئ / القوانين', 'render' => 'list'],
                    ['key' => 'matrix_col_activities', 'label' => 'الأنشطة والمهارات', 'render' => 'list'],
                    ['key' => 'matrix_col_values', 'label' => 'القيم والاتجاهات', 'render' => 'list'],
                    ['key' => 'matrix_col_visuals', 'label' => 'الرسومات والجداول والأشكال', 'render' => 'list'],
                    ['key' => 'matrix_col_other_tasks', 'label' => 'بنود أخرى / مهام الطلبة', 'render' => 'list'],
                    ['key' => 'additional_notes', 'label' => 'ملاحظات ختامية', 'render' => 'richtext'],
                ],
            ],
        ];

        $row = [
            'type_key' => 'task_matrix_v1',
            'title' => 'مصفوفة المحتوى والمهام',
            'description' => 'إنتاج مصفوفة محتوى/مهام تغطي الوحدة الدراسية وعدد الحصص المحدد.',
            'provider' => $provider,
            'model_name' => $modelName,
            'prompt_maker_class' => \App\Libraries\PromptMaker\TaskMatrixPromptMaker::class,
            'template_path' => null,
            'system_prompt' => $systemPrompt,
            'ui_config_json' => json_encode($uiConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'js_module_path' => '',
            'output_schema' => null,
            'is_enabled' => 1,
            // ─── سلوك الأداة ───
            'needs_task_context' => 0,  // لا تحتاج سياق مهمة
            'needs_specialty' => 0,  // لا تحتاج تخصص
            'task_prompt_mode' => 'ignore',
        ];


        $this->upsertType('task_matrix_v1', $row);
    }

    protected function seedLessonPlanTool(string $provider, string $modelName): void
    {
        $systemPrompt = <<<'PROMPT'
Act like معلّم خبير في تصميم خطط الدروس وفق التعلم البنائي، التعلم الدامج، وتمايز التعليم، مع توظيف روتينيات التفكير (Project Zero - Harvard). هدفك إنتاج خطة حصة واحدة متكاملة قابلة للتنفيذ.

الهدف: بناء خطة لحصة تراعي الفروق الفردية وأنماط التعلم ودمج ذوي الاحتياجات. لا تبدأ العمل قبل التأكد من اكتمال المدخلات الأساسية (teacher_name, grade_level, subject_name, lesson_title, outcomes_count, duration_minutes).

خطوات العمل:
1) حدّد الصف والمادة والموضوع. إن كانت المدخلات الأساسية ناقصة فأعد JSON بصيغة `{"need_more_info":["field_1","field_2"],"message":"..."}`
2) ابحث حصراً في المنهاج الأردني (2022+) لاستخراج التعلم القبلي والتكامل الرأسي/الأفقي.
3) صُغ نواتج تعلم SMART بعدد outcomes_count مع مستوى بلوم وزمن تحقق داخل الحصة.
4) خطّط مراحل الحصة (تهيئة، تمهيد، عرض، غلق) بحيث يكون مجموع الدقائق ≈ duration_minutes.
5) لكل ناتج حدّد استراتيجية تدريس، أداة/استراتيجية تقييم، دور المعلم والطالب، وزمن التنفيذ.
6) اختم بتأمل مهني ≤200 كلمة يتضمن نقاط قوة/تحسين وخطة إجراء مختصرة.

قيود الإخراج:
- لغة عربية فصحى مهنية دون حشو.
- JSON صالح فقط دون أي نص خارجي.
- الحقول الأساسية: teacher_name, school_name, school_class, general_subject, general_unit, general_title, integration_vertical, integration_horizontal, prior_knowledge, knowledge_cognitive, knowledge_skills, knowledge_values, learning_sources, e_application, warmup_teacher, warmup_student, warmup_minutes, intro_teacher, intro_student, intro_minutes, closure_teacher, closure_student, closure_minutes, differentiation_content, differentiation_process, differentiation_product, digital_safety, reflection.
- حقول النواتج يجب أن تكون مصفوفات بطول outcomes_count للمفاتيح: o_statement, o_assess_strategy, o_assess_tool, o_teach_strategy, o_teacher, o_student, o_duration.

Take a deep breath and work on this problem step-by-step.
PROMPT;

        $uiConfig = [
            'panel' => [
                'button' => [
                    'label' => 'توليد خطة الحصة (AI)',
                    'icon' => 'fa-wand-magic-sparkles',
                ],
                'download_label' => 'تنزيل خطة الحصة',
                'status_labels' => [
                    'idle' => 'جاهز للاستخدام',
                    'pending' => 'في قائمة الانتظار...',
                    'processing' => 'قيد المعالجة...',
                    'done' => 'تم الإنشاء',
                    'failed' => 'فشل سابق',
                ],
            ],
            'form' => [
                'modal_title' => 'بيانات الحصة التعليمية',
                'submit_label' => 'إرسال الطلب',
                'sections' => [
                    [
                        'title' => 'البيانات الأساسية',
                        'layout' => 'grid-2',
                        'fields' => [
                            ['key' => 'teacher_name', 'type' => 'text', 'label' => 'اسم المعلم', 'required' => true, 'placeholder' => 'مثال: أ. فاطمة المصري'],
                            [
                                'key' => 'teacher_gender',
                                'type' => 'select',
                                'label' => 'جنس المعلم',
                                'options' => [
                                    ['value' => 'male', 'label' => 'ذكر'],
                                    ['value' => 'female', 'label' => 'أنثى'],
                                ]
                            ],
                            ['key' => 'school_name', 'type' => 'text', 'label' => 'اسم المدرسة', 'required' => true],
                            ['key' => 'grade_level', 'type' => 'text', 'label' => 'الصف/المستوى', 'required' => true, 'placeholder' => 'مثال: التاسع الأساسي'],
                            ['key' => 'subject_name', 'type' => 'text', 'label' => 'المادة / المبحث', 'required' => true],
                            [
                                'key' => 'student_gender',
                                'type' => 'select',
                                'label' => 'جنس الطلبة',
                                'required' => true,
                                'options' => [
                                    ['value' => 'male', 'label' => 'ذكور'],
                                    ['value' => 'female', 'label' => 'إناث'],
                                    ['value' => 'mixed', 'label' => 'مختلط'],
                                ]
                            ],
                            ['key' => 'unit_name', 'type' => 'text', 'label' => 'الوحدة الدراسية'],
                            ['key' => 'lesson_title', 'type' => 'text', 'label' => 'اسم الدرس', 'required' => true],
                            ['key' => 'topic_summary', 'type' => 'textarea', 'label' => 'وصف مختصر (≥100 حرف)', 'minlength' => 100, 'required' => true],
                        ],
                    ],
                    [
                        'title' => 'التفاصيل الأكاديمية',
                        'layout' => 'grid-2',
                        'fields' => [
                            ['key' => 'outcomes_count', 'type' => 'number', 'label' => 'عدد النتاجات', 'min' => 2, 'max' => 8, 'required' => true, 'default' => 2],
                            ['key' => 'duration_minutes', 'type' => 'number', 'label' => 'مدة الحصة (دقائق)', 'min' => 10, 'max' => 120, 'default' => 40, 'required' => true],
                            ['key' => 'class_size', 'type' => 'number', 'label' => 'عدد الطلبة في الشعبة', 'min' => 5, 'max' => 60, 'default' => 30],
                            [
                                'key' => 'focus_axis',
                                'type' => 'select',
                                'label' => 'محور التركيز',
                                'options' => [
                                    ['value' => 'التمييز', 'label' => 'التمييز'],
                                    ['value' => 'العلاقات الصفية/بيئة داعمة', 'label' => 'العلاقات الصفية/بيئة داعمة'],
                                    ['value' => 'الدمج والتكييف', 'label' => 'الدمج والتكييف'],
                                ]
                            ],
                            ['key' => 'outcomes_description', 'type' => 'textarea', 'label' => 'وصف عام للنتاجات'],
                            ['key' => 'learning_assets', 'type' => 'textarea', 'label' => 'موارد التعلّم المتاحة', 'placeholder' => 'سبورة، عارض بيانات، أوراق عمل ...'],
                            ['key' => 'constraints', 'type' => 'textarea', 'label' => 'القيود أو التعليمات الخاصة'],
                            ['key' => 'rough_goals_note', 'type' => 'textarea', 'label' => 'ملاحظات أولية على الأهداف'],
                        ],
                    ],
                    [
                        'title' => 'دعم ذوي الاحتياجات',
                        'layout' => 'grid-2',
                        'fields' => [
                            ['key' => 'sen_present', 'type' => 'toggle', 'label' => 'وجود طلبة ذوي احتياجات خاصة', 'default' => false],
                            [
                                'key' => 'sen_support',
                                'type' => 'select',
                                'label' => 'نوع الدعم المقدّم',
                                'show_if' => ['field' => 'sen_present', 'value' => true],
                                'options' => [
                                    ['value' => 'تكييف زمني', 'label' => 'تكييف زمني (وقت إضافي)'],
                                    ['value' => 'تبسيط التعليمات', 'label' => 'تبسيط التعليمات'],
                                    ['value' => 'وسائل بصرية', 'label' => 'وسائل بصرية/سمعية'],
                                    ['value' => 'مساعدة فردية', 'label' => 'مساعدة فردية داخل الصف'],
                                    ['value' => 'مواد بديلة مبسطة', 'label' => 'مواد بديلة مبسطة'],
                                    ['value' => 'بيئة صفية هادئة', 'label' => 'تهيئة بيئة صفية هادئة'],
                                    ['value' => 'other', 'label' => 'أخرى'],
                                ]
                            ],
                            ['key' => 'sen_support_other', 'type' => 'text', 'label' => 'اذكر نوع الدعم (أخرى)', 'show_if' => ['field' => 'sen_support', 'value' => 'other']],
                        ],
                    ],
                    [
                        'title' => 'استراتيجيات التدريس',
                        'layout' => 'chips',
                        'fields' => [
                            [
                                'key' => 'teaching_strategies',
                                'type' => 'chips',
                                'label' => 'استراتيجيات التدريس (حتى 3)',
                                'max' => 3,
                                'options' => [
                                    'العصف الذهني',
                                    'فكر–زاوج–شارك',
                                    'التعلّم التعاوني',
                                    'العرض العملي',
                                    'الاستقصاء العلمي',
                                    'لعب الأدوار',
                                    'الحوار السقراطي',
                                    'التعلّم بالمحطات',
                                    'التعلّم المقلوب',
                                    'حل المشكلات',
                                    'مشاريع خدمة المجتمع',
                                    'Gallery Walk',
                                    'التعلّم القائم على اللعب'
                                ],
                            ],
                        ],
                    ],
                    [
                        'title' => 'استراتيجيات التقويم',
                        'layout' => 'chips',
                        'fields' => [
                            [
                                'key' => 'assessment_methods',
                                'type' => 'chips',
                                'label' => 'أساليب التقويم (حتى 3)',
                                'max' => 3,
                                'options' => [
                                    'بطاقة دخول',
                                    'بطاقة خروج',
                                    'Rubric بنائي',
                                    'ملاحظة صفية',
                                    'تقييم ذاتي',
                                    'تقييم أقران',
                                    'مهام أدائية قصيرة',
                                    'عرض تقديمي',
                                    'خرائط مفاهيم',
                                    'سؤال شفهي سريع',
                                    'استبيان رقمي',
                                    'سجل قصصي'
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'result' => [
                'type' => 'docx',
            ],
        ];

        $row = [
            'type_key' => 'lesson_plan_v1',
            'title' => 'منشئ خطة الحصة — الإصدار 1',
            'description' => 'إنشاء خطة حصة متكاملة وإخراج ملف DOCX قابل للتنزيل.',
            'provider' => $provider,
            'model_name' => $modelName,
            'prompt_maker_class' => \App\Libraries\PromptMaker\LessonPlanPromptMaker::class,
            'template_path' => 'templates/lesson_plan_template.docx',
            'system_prompt' => $systemPrompt,
            'ui_config_json' => json_encode($uiConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'js_module_path' => '',
            'output_schema' => null,
            'is_enabled' => 1,
            // ─── سلوك الأداة ───
            'needs_task_context' => 0,  // لا تحتاج سياق مهمة
            'needs_specialty' => 0,
            'task_prompt_mode' => 'ignore',
        ];


        $this->upsertType('lesson_plan_v1', $row);
    }

    protected function seedForumReplyTool(string $provider, string $modelName): void
    {
        $systemPrompt = <<<'PROMPT'
Act like خبير في كتابة ردود منتدى تعليمية. استوعب الموضوع والسياق والتعليمات وأعد ردًا داعمًا ومحفزًا للحوار، بلغة عربية واضحة ومحترمة.

إن كانت المدخلات الأساسية (topic, context) ناقصة فأعد JSON بالهيئة:
{"need_more_info":["topic","context"],"message":"يرجى تزويدي بالبيانات الناقصة."}

غير ذلك، أعد JSON بالمفاتيح:
- title: عنوان قصير للرد.
- content: الرد التفصيلي (≤ 250 كلمة) مع فقرات موجزة.
- tags: مصفوفة وسوم مرتبطة بالموضوع.

احترم نبرة الرد المطلوبة (tone) والجمهور (audience). استخدم المصادر أو الأمثلة الحديثة إن طلب.
PROMPT;

        $uiConfig = [
            'panel' => [
                'button' => [
                    'label' => 'مساعد مناقشة المنتدى (AI)',
                    'icon' => 'fa-comments',
                ],
                'status_labels' => [
                    'idle' => 'جاهز للردود',
                    'pending' => 'يتم تجهيز الرد...',
                    'processing' => 'قيد الكتابة...',
                    'done' => 'تم إنشاء الرد',
                    'failed' => 'تعذّر التنفيذ',
                ],
            ],
            'form' => [
                'modal_title' => 'توليد رد على المنتدى',
                'submit_label' => 'توليد الرد',
                'sections' => [
                    [
                        'title' => 'تفاصيل الرد',
                        'layout' => 'grid-1',
                        'fields' => [
                            ['key' => 'requirements', 'type' => 'textarea', 'label' => 'تعليمات خاصة', 'placeholder' => 'مثال: استخدم لغة رسمية واذكر مصدرين حديثين'],
                        ],
                    ],
                    [
                        'title' => 'تفضيلات الرد',
                        'layout' => 'grid-2',
                        'fields' => [
                            [
                                'key' => 'tone',
                                'type' => 'select',
                                'label' => 'نبرة الرد',
                                'default' => 'formal',
                                'options' => [
                                    ['value' => 'formal', 'label' => 'رسمية'],
                                    ['value' => 'friendly', 'label' => 'ودّية'],
                                    ['value' => 'critical', 'label' => 'ناقدة بنّاءة'],
                                    ['value' => 'motivational', 'label' => 'تحفيزية'],
                                ]
                            ],
                            [
                                'key' => 'audience',
                                'type' => 'select',
                                'label' => 'الجمهور المستهدف',
                                'default' => 'teachers',
                                'options' => [
                                    ['value' => 'teachers', 'label' => 'المعلمين'],
                                    ['value' => 'students', 'label' => 'الطلبة'],
                                    ['value' => 'admins', 'label' => 'إداريي المنصة'],
                                    ['value' => 'parents', 'label' => 'أولياء الأمور'],
                                ]
                            ],
                            [
                                'key' => 'highlights',
                                'type' => 'chips',
                                'label' => 'محاور يجب تضمينها (حتى 3)',
                                'max' => 3,
                                'options' => [
                                    'مراجع حديثة',
                                    'سؤال مفتوح',
                                    'اقتراح نشاط',
                                    'تغذية راجعة بنّاءة',
                                    'ربط بواقع الصف',
                                    'تنبيه أكاديمي'
                                ],
                            ],
                        ],
                    ],
                    [
                        'title' => 'إضافات اختيارية',
                        'layout' => 'grid-1',
                        'fields' => [
                            [
                                'key' => 'student_answer',
                                'type' => 'textarea',
                                'label' => 'إجابتك الخاصة (اختياري)',
                                'placeholder' => 'اكتب هنا أي أفكار أو ملاحظات خاصة بك تريد دمجها في الرد لضمان فريدية الإجابة...',
                                'hint' => 'هذا الحقل اختياري. يمكنك كتابة أفكارك الخاصة التي تريد أن يدمجها الذكاء الاصطناعي في الرد.',
                            ],
                        ],
                    ],
                ],
            ],
            'result' => [
                'type' => 'text',
                'fields' => [
                    ['key' => 'title', 'label' => 'عنوان الرد'],
                    ['key' => 'content', 'label' => 'محتوى الرد', 'render' => 'richtext'],
                    ['key' => 'tags', 'label' => 'الوسوم', 'render' => 'chips'],
                ],
            ],
        ];

        $row = [
            'type_key' => 'forum_reply_v1',
            'title' => 'مساعد مناقشة المنتدى',
            'description' => 'كتابة ردود منتديات تعليمية بنبرة مخصصة.',
            'provider' => $provider,
            'model_name' => $modelName,
            'prompt_maker_class' => \App\Libraries\PromptMaker\ForumReplyPromptMaker::class,
            'template_path' => null,
            'system_prompt' => $systemPrompt,
            'ui_config_json' => json_encode($uiConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'js_module_path' => '',
            'output_schema' => null,
            'is_enabled' => 1,
            // ─── سلوك الأداة ───
            'needs_task_context' => 1,  // تحتاج task_title + task_prompt
            'needs_specialty' => 0,
            'task_prompt_mode' => 'append',
        ];


        $this->upsertType('forum_reply_v1', $row);
    }

    protected function seedForumReplyToolV2(string $provider, string $modelName): void
    {
        $systemPrompt = <<<'PROMPT'
Act like مقيم تربوي خبير في تقييم الخطط التعليمية (كالخطط الدرسية أو خطط الأنشطة).
المهمة: تقييم الخطة المرفقة لك ضمن قسم السطر الذي يبدأ بـ "السياق/وصف المهمة (Context / Task Prompt)" بناءً على المعايير التربوية الحديثة، مع تقديم تغذية راجعة بناءة.

لا تطلب معلومات إضافية طالما أن هناك نصاً أو خطة مرفقة في هذا القسم لتتمكن من تقييمها.

إن لم يكن هناك أي خطة على الإطلاق، فأعد JSON بالهيئة:
{"need_more_info":["context"],"message":"عذراً، لم أتمكن من العثور على الخطة. يرجى إرفاق الخطة المطلوبة لتقييمها في وصف المهمة (Task Prompt)."}

غير ذلك، أعد التقييم كـ JSON بالهيكل التالي:
- title: عنوان قصير للتقييم.
- content: التقييم التفصيلي للخطة، يتضمن نقاط القوة، ومجالات التحسين، ومقترحات عملية للتطوير (في حوالي 150-250 كلمة).
- tags: مصفوفة وسوم مرتبطة بالخطة والتقييم، مثل ["تقييم خطة", "تطوير مهني"].

استخدم أسلوب الناندويتش (Praise - Critique - Suggestion) في التقييم، وتجنب استخدام لغة قاسية. التزم بنبرة الرد المطلوبة (tone) والجمهور (audience).
PROMPT;

        $uiConfig = [
            'panel' => [
                'button' => [
                    'label' => 'مساعد مناقشة المنتدى (تقييم خطة)',
                    'icon' => 'fa-comments',
                ],
                'status_labels' => [
                    'idle' => 'جاهز للتقييم',
                    'pending' => 'يتم تجهيز التقييم...',
                    'processing' => 'قيد الكتابة...',
                    'done' => 'تم إنشاء التقييم',
                    'failed' => 'تعذّر التنفيذ',
                ],
            ],
            'form' => [
                'modal_title' => 'توليد تقييم لخطة',
                'submit_label' => 'توليد التقييم',
                'sections' => [
                    [
                        'title' => 'تفاصيل التقييم',
                        'layout' => 'grid-1',
                        'fields' => [
                            ['key' => 'requirements', 'type' => 'textarea', 'label' => 'تعليمات خاصة', 'placeholder' => 'مثال: ركز على تقييم نواتج التعلم'],
                        ],
                    ],
                    [
                        'title' => 'تفضيلات الرد',
                        'layout' => 'grid-2',
                        'fields' => [
                            [
                                'key' => 'tone',
                                'type' => 'select',
                                'label' => 'نبرة الرد',
                                'default' => 'critical',
                                'options' => [
                                    ['value' => 'formal', 'label' => 'رسمية'],
                                    ['value' => 'friendly', 'label' => 'ودّية'],
                                    ['value' => 'critical', 'label' => 'ناقدة بنّاءة'],
                                    ['value' => 'motivational', 'label' => 'تحفيزية'],
                                ]
                            ],
                            [
                                'key' => 'audience',
                                'type' => 'select',
                                'label' => 'الجمهور المستهدف',
                                'default' => 'teachers',
                                'options' => [
                                    ['value' => 'teachers', 'label' => 'المعلمين'],
                                    ['value' => 'students', 'label' => 'الطلبة'],
                                    ['value' => 'admins', 'label' => 'إداريي المنصة'],
                                    ['value' => 'parents', 'label' => 'أولياء الأمور'],
                                ]
                            ],
                            [
                                'key' => 'highlights',
                                'type' => 'chips',
                                'label' => 'محاور يجب التركيز عليها (حتى 3)',
                                'max' => 3,
                                'options' => [
                                    'نواتج التعلم',
                                    'استراتيجيات التدريس',
                                    'أساليب التقويم',
                                    'الدمج والتكييف',
                                    'التسلسل المنطقي'
                                ],
                            ],
                        ],
                    ],
                    [
                        'title' => 'إضافات اختيارية',
                        'layout' => 'grid-1',
                        'fields' => [
                            [
                                'key' => 'student_answer',
                                'type' => 'textarea',
                                'label' => 'ملاحظاتك الخاصة (اختياري)',
                                'placeholder' => 'اكتب هنا أي نقاط قوة أو ضعف محددة لاحظتها مسبقاً في الخطة...',
                                'hint' => 'هذا الحقل اختياري. يمكنك توجيه الذكاء الاصطناعي للتركيز على جوانب معينة لاحظتها.',
                            ],
                        ],
                    ],
                ],
            ],
            'result' => [
                'type' => 'text',
                'fields' => [
                    ['key' => 'title', 'label' => 'عنوان التقييم'],
                    ['key' => 'content', 'label' => 'محتوى التقييم', 'render' => 'richtext'],
                    ['key' => 'tags', 'label' => 'الوسوم', 'render' => 'chips'],
                ],
            ],
        ];

        $row = [
            'type_key' => 'forum_reply_v2',
            'title' => 'مساعد مناقشة المنتدى (تقييم خطة)',
            'description' => 'توليد تقييم تربوي بناء لخطة مرفقة في المهمة.',
            'provider' => $provider,
            'model_name' => $modelName,
            'prompt_maker_class' => \App\Libraries\PromptMaker\ForumReplyPromptMaker::class,
            'template_path' => null,
            'system_prompt' => $systemPrompt,
            'ui_config_json' => json_encode($uiConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'js_module_path' => '',
            'output_schema' => null,
            'is_enabled' => 1,
            // ─── سلوك الأداة ───
            'needs_task_context' => 1,  // تحتاج task_prompt (الخطة المرفقة)
            'needs_specialty' => 0,
            'task_prompt_mode' => 'append',
        ];


        $this->upsertType('forum_reply_v2', $row);
    }

    protected function seedReflectionTool(string $provider, string $modelName): void
    {
        $systemPrompt = <<<'PROMPT'
Act like خبير في كتابة تأملات مهنية احترافية لمعلمي ومعلمات الدبلوم المهني في التعليم. استوعب موضوع المهمة ومتطلباتها والتعليمات وأعد تأملاً عميقاً وتأملياً، بلغة عربية واضحة ومحترمة.

إن كانت المدخلات الأساسية (task_title, task_prompt) ناقصة فأعد JSON بالهيئة:
{"need_more_info":["task_title","task_prompt"],"message":"يرجى تزويدي بالبيانات الناقصة."}

غير ذلك، أعد JSON بالمفاتيح:
- content: التأمل الكامل (فقرة واحدة أو فقرتين مترابطتين فقط، حوالي 150 كلمة).

احترم نبرة التأمل المطلوبة (tone) والجمهور (audience) والتخصص (teacher_specialty). استخدم استراتيجية PEEL في كتابة الفقرات دون ذكرها بالاسم.
PROMPT;

        $uiConfig = [
            'panel' => [
                'button' => [
                    'label' => 'إنشاء تأمل (AI)',
                    'icon' => 'fa-lightbulb',
                ],
                'status_labels' => [
                    'idle' => 'جاهز للإنشاء',
                    'pending' => 'يتم تجهيز التأمل...',
                    'processing' => 'قيد الكتابة...',
                    'done' => 'تم إنشاء التأمل',
                    'failed' => 'تعذّر التنفيذ',
                ],
            ],
            'form' => [
                'modal_title' => 'إنشاء تأمل',
                'submit_label' => 'إنشاء التأمل',
                'sections' => [
                    [
                        'title' => 'تفضيلات التأمل',
                        'layout' => 'grid-2',
                        'fields' => [
                            [
                                'key' => 'tone',
                                'type' => 'select',
                                'label' => 'النبرة',
                                'default' => 'formal',
                                'options' => [
                                    ['value' => 'formal', 'label' => 'رسمية'],
                                    ['value' => 'friendly', 'label' => 'ودّية'],
                                    ['value' => 'reflective', 'label' => 'تأملية'],
                                    ['value' => 'analytical', 'label' => 'تحليلية'],
                                ]
                            ],
                            [
                                'key' => 'audience',
                                'type' => 'select',
                                'label' => 'الجمهور المستهدف',
                                'default' => 'teachers',
                                'options' => [
                                    ['value' => 'teachers', 'label' => 'المعلمين'],
                                    ['value' => 'students', 'label' => 'الطلبة'],
                                    ['value' => 'peers', 'label' => 'الزملاء'],
                                    ['value' => 'supervisors', 'label' => 'المشرفين'],
                                ]
                            ],
                            [
                                'key' => 'requirements',
                                'type' => 'chips',
                                'label' => 'خيارات يجب تضمينها (حتى 3)',
                                'max' => 3,
                                'options' => [
                                    'ربط بواقع الصف',
                                    'أمثلة عملية',
                                    'تطبيق نظري',
                                    'تأمل شخصي',
                                    'تحليل نقدي',
                                    'اقتراحات تحسين'
                                ],
                            ],
                        ],
                    ],
                    [
                        'title' => 'أفكار أولية',
                        'layout' => 'grid-1',
                        'fields' => [
                            [
                                'key' => 'initial_ideas',
                                'type' => 'textarea',
                                'label' => 'أفكار أولية/مبدئية (اختياري)',
                                'placeholder' => 'اكتب هنا أي أفكار أو ملاحظات أولية تريد استخدامها كنواة في بناء التأمل...',
                                'hint' => 'هذا الحقل اختياري. يمكنك كتابة أفكارك الأولية التي تريد أن يبني عليها الذكاء الاصطناعي التأمل.',
                            ],
                        ],
                    ],
                ],
            ],
            'result' => [
                'type' => 'text',
                'title' => 'التأمل',
                'fields' => [
                    ['key' => 'content', 'label' => 'محتوى التأمل', 'render' => 'richtext'],
                ],
            ],
        ];

        $row = [
            'type_key' => 'reflection_v1',
            'title' => 'منشئ التأمل',
            'description' => 'كتابة تأمل من 150 كلمة حول موضوع المهمة.',
            'provider' => $provider,
            'model_name' => $modelName,
            'prompt_maker_class' => \App\Libraries\PromptMaker\ReflectionPromptMaker::class,
            'template_path' => null,
            'system_prompt' => $systemPrompt,
            'ui_config_json' => json_encode($uiConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'js_module_path' => '',
            'output_schema' => null,
            'is_enabled' => 1,
            // ─── سلوك الأداة ───
            'needs_task_context' => 1,  // تحتاج task_title + task_prompt
            'needs_specialty' => 1,  // تحتاج تخصص المعلم
            'task_prompt_mode' => 'append',
        ];


        $this->upsertType('reflection_v1', $row);
    }

    protected function seedCaseStudyTool(string $provider, string $modelName): void
    {
        $systemPrompt = <<<'PROMPT'
تصرف كخبير في تحليل دراسات الحالة التربوية والتعليمية.
قم بتحليل دراسة الحالة المقدمة بشكل شامل ومنهجي، مع التركيز على المعايير المحددة للمعلمين.
أعد تحليلاً متكاملاً بصيغة JSON صالحة فقط دون أي شرح خارج JSON.
إن وُجد نقص في المدخلات اتّبع بروتوكول نقص المدخلات المشار إليه في رسالة المستخدم.
PROMPT;

        $uiConfig = [
            'panel' => [
                'button' => [
                    'label' => 'تحليل دراسة الحالة (AI)',
                    'icon' => 'fa-file-lines',
                ],
                'download_label' => 'تنزيل تحليل دراسة الحالة',
                'status_labels' => [
                    'idle' => 'جاهز للاستخدام',
                    'pending' => 'في قائمة الانتظار...',
                    'processing' => 'قيد المعالجة...',
                    'done' => 'تم الإنشاء',
                    'failed' => 'فشل سابق',
                ],
            ],
            'form' => [
                'modal_title' => 'تحليل دراسة الحالة',
                'submit_label' => 'إرسال الطلب',
                'sections' => [
                    [
                        'title' => 'البيانات الأساسية',
                        'layout' => 'grid-2',
                        'fields' => [
                            ['key' => 'teacher_name', 'type' => 'text', 'label' => 'اسم المعلم الطالب', 'required' => true, 'placeholder' => 'مثال: أ. فاطمة المصري'],
                            ['key' => 'grade_level', 'type' => 'text', 'label' => 'المرحلة التي يدرسها', 'required' => true, 'placeholder' => 'مثال: التاسع الأساسي'],
                            [
                                'key' => 'selected_case_index',
                                'type' => 'select',
                                'label' => 'اختر الحالة المراد تحليلها',
                                'required' => true,
                                'options' => [
                                    ['value' => '', 'label' => '— جاري تحميل الحالات —'],
                                ]
                            ],
                            ['key' => 'case_text', 'type' => 'textarea', 'label' => 'نص دراسة الحالة', 'minlength' => 50, 'required' => true, 'readonly' => true, 'show_if' => ['field' => '__always_hidden__', 'value' => '__never__'], 'placeholder' => 'سيتم تحميل النص تلقائياً من حقل موجه المهمة...'],
                            [
                                'key' => 'analysis_nature',
                                'type' => 'select',
                                'label' => 'طبيعة التحليل',
                                'required' => true,
                                'options' => [
                                    ['value' => '', 'label' => '— اختر طبيعة التحليل —'],
                                    ['value' => 'comprehensive', 'label' => 'تحليل شامل ومتكامل'],
                                    ['value' => 'critical', 'label' => 'تحليل نقدي يركز على التحديات'],
                                    ['value' => 'solution_oriented', 'label' => 'تحليل يركز على الحلول والبدائل'],
                                    ['value' => 'reflective', 'label' => 'تحليل تأملي يركز على التعلم'],
                                    ['value' => 'practical', 'label' => 'تحليل عملي يركز على التطبيق'],
                                    ['value' => 'theoretical', 'label' => 'تحليل نظري يركز على الأطر المفاهيمية'],
                                ],
                                'hint' => 'اختر طبيعة التحليل لضمان تنوع الإجابات بين الطلبة'
                            ],
                        ],
                    ],
                ],
            ],
            'result' => [
                'type' => 'docx',
                'title' => 'تحليل دراسة الحالة',
                'fields' => [
                    ['key' => 'teacher_name', 'label' => 'اسم المعلم الطالب'],
                    ['key' => 'grade_level', 'label' => 'المرحلة التي يدرسها'],
                    ['key' => 'case_title', 'label' => 'عنوان الحالة'],
                    ['key' => 'context', 'label' => 'السياق', 'render' => 'richtext'],
                    ['key' => 'main_dilemma', 'label' => 'الحالة / المعضلة الرئيسة', 'render' => 'richtext'],
                    ['key' => 'characters', 'label' => 'الشخصيات', 'render' => 'richtext'],
                    ['key' => 'case_identification', 'label' => 'تحديد الحالة', 'render' => 'richtext'],
                    ['key' => 'case_description', 'label' => 'وصف الحالة', 'render' => 'richtext'],
                    ['key' => 'case_evaluation', 'label' => 'تقييم الحالة', 'render' => 'richtext'],
                    ['key' => 'proposed_alternatives', 'label' => 'البدائل المقترحة', 'render' => 'richtext'],
                    ['key' => 'teacher_criteria', 'label' => 'معيار / معايير المعلمين', 'render' => 'richtext'],
                ],
            ],
        ];

        $row = [
            'type_key' => 'case_study_v1',
            'title' => 'تحليل دراسة الحالة',
            'description' => 'تحليل شامل ومنهجي لدراسة حالة تربوية مع ربطها بمعايير المعلمين.',
            'provider' => $provider,
            'model_name' => $modelName,
            'prompt_maker_class' => \App\Libraries\PromptMaker\CaseStudyPromptMaker::class,
            'template_path' => 'templates/case_study_template.docx',
            'system_prompt' => $systemPrompt,
            'ui_config_json' => json_encode($uiConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'js_module_path' => '',
            'output_schema' => null,
            'is_enabled' => 1,
            // ─── سلوك الأداة ───
            'needs_task_context' => 1,  // تحتاج task_prompt (نحتوي دراسة الحالة)
            'needs_specialty' => 0,
            'task_prompt_mode' => 'append',
        ];


        $this->upsertType('case_study_v1', $row);
    }

    protected function seedCaseIntegrationTool(string $provider, string $modelName): void
    {
        $systemPrompt = <<<'PROMPT'
تصرف كخبير في بناء سيناريوهات دمج الحالة التربوية والتعليمية.
قم بتحليل المدخلات الأولية من المعلم الطالب وبناء سيناريو كامل ومناسب لدمج الحالة في سياق تعليمي.
أعد إجابة متكاملة بصيغة JSON صالحة فقط دون أي شرح خارج JSON.
إن وُجد نقص في المدخلات اتّبع بروتوكول نقص المدخلات المشار إليه في رسالة المستخدم.
PROMPT;

        $uiConfig = [
            'panel' => [
                'button' => [
                    'label' => 'دمج الحالة (AI)',
                    'icon' => 'fa-link',
                ],
                'download_label' => 'تنزيل سيناريو دمج الحالة',
                'status_labels' => [
                    'idle' => 'جاهز للاستخدام',
                    'pending' => 'في قائمة الانتظار...',
                    'processing' => 'قيد المعالجة...',
                    'done' => 'تم الإنشاء',
                    'failed' => 'فشل سابق',
                ],
            ],
            'form' => [
                'modal_title' => 'دمج الحالة',
                'submit_label' => 'إرسال الطلب',
                'sections' => [
                    [
                        'title' => 'البيانات الأساسية',
                        'layout' => 'grid-2',
                        'fields' => [
                            ['key' => 'teacher_name', 'type' => 'text', 'label' => 'اسم المعلم الطالب', 'required' => true, 'placeholder' => 'مثال: أ. فاطمة المصري'],
                            ['key' => 'teacher_specialty', 'type' => 'text', 'label' => 'التخصص', 'required' => true, 'placeholder' => 'مثال: الرياضيات، اللغة العربية'],
                            [
                                'key' => 'case_category',
                                'type' => 'select',
                                'label' => 'تصنيف الحالة',
                                'required' => true,
                                'options' => [
                                    ['value' => 'صعوبات التعلم الأكاديمية', 'label' => 'صعوبات التعلم الأكاديمية'],
                                    ['value' => 'اضطرابات التواصل واللغة', 'label' => 'اضطرابات التواصل واللغة'],
                                    ['value' => 'اضطرابات الانتباه والسلوك', 'label' => 'اضطرابات الانتباه والسلوك'],
                                    ['value' => 'الإعاقات الحسية', 'label' => 'الإعاقات الحسية'],
                                    ['value' => 'الإعاقات الحركية والصحية', 'label' => 'الإعاقات الحركية والصحية'],
                                    ['value' => 'الإعاقة العقلية/النمائية', 'label' => 'الإعاقة العقلية/النمائية'],
                                    ['value' => 'اضطراب طيف التوحد (ASD)', 'label' => 'اضطراب طيف التوحد (ASD)'],
                                    ['value' => 'الاضطرابات الانفعالية والنفسية', 'label' => 'الاضطرابات الانفعالية والنفسية'],
                                    ['value' => 'الموهبة والتفوق', 'label' => 'الموهبة والتفوق'],
                                    ['value' => 'فروق فردية مرتبطة بالنوع الاجتماعي والسياق', 'label' => 'فروق فردية مرتبطة بالنوع الاجتماعي والسياق'],
                                ]
                            ],
                            [
                                'key' => 'case',
                                'type' => 'select',
                                'label' => 'نوع الحالة',
                                'required' => true,
                                'disabled' => true,
                                'options' => [
                                    ['value' => '', 'label' => '— اختر التصنيف أولاً —'],
                                ]
                            ],
                            ['key' => 'case_description', 'type' => 'textarea', 'label' => 'وصف الحالة', 'minlength' => 20, 'required' => true, 'placeholder' => 'ما هي تفاصيل الحالة التي تريد دمجها؟ اذكر السياق، التحديات، والاحتياجات التعليمية للطالب/الطلبة...'],
                        ],
                    ],
                ],
            ],
            'result' => [
                'type' => 'docx',
                'title' => 'سيناريو دمج الحالة',
                'fields' => [
                    ['key' => 'teacher_name', 'label' => 'اسم المعلم الطالب'],
                    ['key' => 'case', 'label' => 'الحالة'],
                    ['key' => 'case_description', 'label' => 'وصف الحالة'],
                    ['key' => 'reason_for_integration', 'label' => 'سبب دمج الحالة', 'render' => 'richtext'],
                    ['key' => 'activities', 'label' => 'أنشطة التعلم', 'render' => 'list'],
                    ['key' => 'environment', 'label' => 'بيئة التعلم', 'render' => 'richtext'],
                    ['key' => 'Stakeholders', 'label' => 'أصحاب العلاقة', 'render' => 'list'],
                    ['key' => 'teacher_criteria', 'label' => 'معيار / معايير المعلمين', 'render' => 'list'],
                ],
            ],
        ];

        $row = [
            'type_key' => 'case_integration_v1',
            'title' => 'دمج الحالة',
            'description' => 'بناء سيناريو كامل لدمج حالة تربوية في سياق تعليمي.',
            'provider' => $provider,
            'model_name' => $modelName,
            'prompt_maker_class' => \App\Libraries\PromptMaker\CaseIntegrationPromptMaker::class,
            'template_path' => 'templates/Integrating_the_case_template.docx',
            'system_prompt' => $systemPrompt,
            'ui_config_json' => json_encode($uiConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'js_module_path' => 'assets/js/students/sections/ai_case_integration_modal.js',
            'output_schema' => null,
            'is_enabled' => 1,
            // ─── سلوك الأداة ───
            'needs_task_context' => 0,  // لا تحتاج task_prompt (المستخدم يدخل البيانات مباشرة)
            'needs_specialty' => 0,
            'task_prompt_mode' => 'ignore',
        ];


        $this->upsertType('case_integration_v1', $row);
    }

    protected function seedArticleEvaluatorTool(string $provider, string $modelName): void
    {
        $systemPrompt = <<<'PROMPT'
أنت خبير تربوي وأكاديمي متخصص في تقييم المقالات الأكاديمية لطلبة الدبلوم المهني في التعليم. مهمتك تقديم تغذية راجعة دقيقة ومفصلة من سياق النص نفسه وفق تعليمات التقييم (task_prompt) المضمنة في رسالة المستخدم، مع إبراز نقاط القوة، وتحديد جوانب التطوير دون إغفال أي نقص جوهري، وصياغة أسئلة موجِّهة تساعد الكاتب على تحسين كل جزء.
المخرجات يجب أن تكون JSON صالح فقط دون أي نص خارج JSON، وممنوع استخدام Markdown أو code blocks، ويجب الالتزام حرفيًا بأسماء الحقول وبنية JSON المطلوبة أدناه.
قيّم فقط بناءً على: مدخلات المستخدم + نص المقال + تعليمات task_prompt. لا تفترض معلومات غير موجودة ولا تخترع محتوى أو مراجع أو التزامًا غير مذكور. لغتك العربية الفصحى المهنية، بنبرة متوازنة تجمع بين الرسمية الأكاديمية والدفء المهني.
إذا وُجد نقص في مدخلات أساسية تمنع التقييم (مثل teacher_name أو article_title أو عدم وجود أي أجزاء نصية)، أعِد فقط بروتوكول نقص المدخلات: { "need_more_info": [...], "message": "..." } دون أي حقول إضافية إطلاقًا.

أولًا: صياغة التغذية الراجعة العامة general_feedback باستخدام "التقييم المبدئي"
استخدم الحقول: article_title, turnitin_percentage, references_match, formatting_compliance 
بالإضافة الى total_words 
لصياغة نظرة عامة واحدة متماسكة بالترتيب التالي:
•	تبدأ بشكر المعلم/الطالب على الجهد.
•	تشير إلى مدى توافق عنوان المقال article_title مع عنوان/موضوع المهمة كما يرد في تعليمات المهمة داخل task_prompt (إن توفرت).
•	تشرح الالتزام بتعليمات الكتابة بالاستناد إلى formatting_compliance.
•	تتناول نسبة الاقتباس turnitin_percentage وفق القاعدة:
o	أقل من 19%: نسبة مقبولة.
o	19% فأكثر: نسبة عالية تستدعي المراجعة والعمل على تقليلها.
o	اذا كانت النسبة بين 30 و 39 يتم إضافة النص التالي في نهاية التغذية الراجعة العامة ("نودّ لفت انتباهك إلى أن ما قمت به يُعدّ مخالفة لمعايير الصدق الأكاديمي، الأمر الذي قد يعرّضك لخطر الرسوب في حال تكراره في التقييم الختامي. ومع ذلك تم تقديم هذه التغذية الراجعة حرصًا على دعم تقدّمك الأكاديمي والتزامك بتقديم مهمة التقييم البنائي")
o	إذا كانت القيمة نصية وبها % استخرج الرقم للمقارنة.
•	تتحقق من توافق عدد الكلمات الكلي مع عدد الكلمات المطلوب في تعليمات المهمة:
•	يصلك عدد الكلمات الكلي عبر حقل total_words تاكد من توافقه مع عدد الكلمات المسموح بين 1350 و 1650
•	توضح ما يتعلق بتوافق المراجع بين النص وقائمة المراجع بالاستناد إلى references_match.

ثانيًا: معايير التقييم المرجعية (لغة "أداة التقييم" دون تصنيف صريح)
قاعدة صارمة: ممنوع استخدام عبارات الحكم الصريح أو التصنيف النهائي مثل: "ناجح بتميز/ناجح بتفوق/ناجح بتقدير جيد/ناجح/راسب"، وممنوع إعطاء نتيجة نهائية أو درجة رقمية أو حكم ختامي يدل على نجاح/رسوب.
المسموح: الإشارة للمستوى بطريقة غير مباشرة باستخدام أوصاف وصفية دلالية مثل: مستوى متقدم/مرتفع/قوي جدًا، مستوى جيد جدًا/جيد، مستوى أساسي/أولي، مستوى يحتاج تعزيزًا كبيرًا/غير كافٍ حاليًا، مع ربط ذلك دائمًا بأدلة من النص وبنقاط محددة في التغذية الراجعة.
استخدم روح توصيف الأداء في الدبلوم المهني عبر المحاور التالية دون تصنيف صريح:
•	المعرفة والفهم والتطبيق: عمق الفهم لعملية التعليم والتعلم والممارسات المهنية، وجودة تطبيق المعرفة عبر حجج واضحة وتحليل وتقييم بدل الوصف العام.
•	المهارات الإدراكيّة: التفكير الناقد في تحديد/تحليل/توصيل المفاهيم، والتأمل الناقد للممارسة عبر تقييم الأدلة وتوليفها وتطبيقها في سياق.
•	المهارات المهنيّة: المبادرة وإدارة التعلم الذاتي وتحديد التحديات وحلها، والوعي بفنون التدريس وتنوع الأساليب وملاءمتها للسياق.
•	التقديم: وضوح الأسلوب والاحترافية والالتزام بالعرف الكتابي، الالتزام بالهيكل وعدد الكلمات، والاعتراف بالمصادر والتوثيق المناسب.
ملاحظة: الحكم هنا وصفي استدلالي فقط (قوي/جيد/أساسي/غير كافٍ حاليًا) ويُستمد من النص ومن نتائج التغذية الراجعة، دون أي تصنيف نهائي.
ثالثًا: تقييم الأقسام (introduction / body / conclusion)
لكل قسم:
•	اكتب section_feedback كتقييم ختامي للقسم ككل، مرتبط مباشرة بمنهجية ومعايير وتعليمات ذلك القسم كما وردت في task_prompt.
•	قيّم كل جزء ضمن parts[] مع الالتزام الصارم ببنية "التغذية الراجعة للأجزاء" أدناه.
رابعًا: تقييم الأجزاء (Parts) — فقرة تغذية راجعة واحدة لكل جزء
داخل كل عنصر في parts[]:
•	يجب أن يحتوي كل جزء (part) على الحقول التالية إلزاميًا: part_index, part_title, part_text, feedback_items.
•	part_text يجب أن يكون النص الأصلي كما ورد دون تلخيص أو تعديل.
•	يجب إرجاع حقل feedback_items والذي هو عبارة عن مصفوفة (Array) تحتوي على عناصر التغذية الراجعة.
•	كل عنصر داخل feedback_items يجب أن يحتوي على هذه الحقول الثلاثة: 
  - item_index: ترتيب التعليق (رقم).
  - text_anchor: الكلمات المحددة من النص الأصلي (part_text) التي يتم التعليق عليها (مثل: "أول كلمتين... آخر كلمة").
  - feedback_paragraph: (نص واحد - string) يحتوي على فقرة واحدة مترابطة بدقة تحتوي على: 
	o	strength: نقطة قوة واضحة مرتبطة بالنص.
	o	development: جانب تطوير محدد لا يتجاهل أي نقص/ضعف جوهري.
	o	guiding_question: سؤال موجّه يساعد الكاتب على تحسين هذا الجزء تحديدًا.
•	يجب أن تحتوي الفقرة (feedback_paragraph) على العناصر الثلاثة (strength + development + guiding_question) بشكل طبيعي ومتناسق ومترابط مع تحديد مجال واحد على الأقل من أدلة التقييم فيها.
خامسًا: ملخص نهائي حسب المحاور — مطلوب لكل محور 3 قوة + 3 تحسين
في المستوى الأعلى (Root) يجب إضافة الحقل evaluation_summary ويكون كائنًا يحتوي المحاور الأربعة، ولكل محور:
•	strengths: مصفوفة تحتوي 3 نقاط قوة بالضبط.
•	improvement_areas: مصفوفة تحتوي 3 نقاط تحسين بالضبط.
صيغة كل عنصر داخل هاتين المصفوفتين:
{ "point": "نقطة دقيقة محددة مشتقة من التغذية الراجعة وبصياغة تعكس لغة أداة التقييم بشكل غير مباشر" }
قاعدة إلزامية لصياغة كل point:
•	تبدأ بجملة قصيرة تشير للمستوى بشكل غير مباشر مثل: "يتضح مستوى جيد في… / يظهر عمق متقدم في… / ما زال المستوى أساسيًا في… / يحتاج تعزيزًا كبيرًا في…"
•	ثم تربطها بسلوك/ميزة محددة ظهرت في التغذية الراجعة (لا عموميات).
قواعد الملخص:
•	النقاط يجب أن تكون مشتقة من تغذيات الأقسام والأجزاء (ليست عموميات).
•	لا تكرر نفس الفكرة بصيغ مختلفة.
•	اجعل النقاط متنوعة ومحددة.
سادسًا: إرشادات عامة لتحسين المقال
أضف حقلًا باسم general_guidance ويكون نصًا عمليًا واضحًا ومختصرًا نسبيًا، يلخّص أهم ما ينبغي فعله لتحسين المقال دون تكرار حرفي لما سبق.
سابعًا: هيكل JSON الإلزامي للمخرجات
أعد JSON بهذا الشكل تمامًا:
{
"general_feedback": "…",
"introduction": {
"section_feedback": "…",
"parts": [
{
"part_index": 1,
"part_title": "…",
"part_text": "…",
"feedback_items": [
{ "item_index": 1, "text_anchor": "…", "feedback_paragraph": "…؟" },
{ "item_index": 2, "text_anchor": "…", "feedback_paragraph": "…؟" }
]
}
]
},
"body": {
"section_feedback": "…",
"parts": [
{
"part_index": 1,
"part_title": "…",
"part_text": "…",
"feedback_items": [
{ "item_index": 1, "text_anchor": "…", "feedback_paragraph": "…؟" },
{ "item_index": 2, "text_anchor": "…", "feedback_paragraph": "…؟" }
]
}
]
},
"conclusion": {
"section_feedback": "…",
"parts": [
{
"part_index": 1,
"part_title": "…",
"part_text": "…",
"feedback_items": [
{ "item_index": 1, "text_anchor": "…", "feedback_paragraph": "…؟" },
{ "item_index": 2, "text_anchor": "…", "feedback_paragraph": "…؟" }
]
}
]
},
"evaluation_summary": {
"المعرفة والفهم والتطبيق": {
"strengths": [
{ "point": "…" },
{ "point": "…" },
{ "point": "…" }
],
"improvement_areas": [
{ "point": "…" },
{ "point": "…" },
{ "point": "…" }
]
},
"المهارات الإدراكيّة": {
"strengths": [ { "point": "…" }, { "point": "…" }, { "point": "…" } ],
"improvement_areas": [ { "point": "…" }, { "point": "…" }, { "point": "…" } ]
},
"المهارات المهنيّة": {
"strengths": [ { "point": "…" }, { "point": "…" }, { "point": "…" } ],
"improvement_areas": [ { "point": "…" }, { "point": "…" }, { "point": "…" } ]
},
"التقديم": {
"strengths": [ { "point": "…" }, { "point": "…" }, { "point": "…" } ],
"improvement_areas": [ { "point": "…" }, { "point": "…" }, { "point": "…" } ]
}
},
"general_guidance": "…"
}
وإذا احتجت بروتوكول نقص المدخلات أعد فقط:
{
"need_more_info": ["…"],
"message": "…"
}
PROMPT;

        $uiConfig = [
            'panel' => [
                'button' => [
                    'label' => 'مقيم المقالات (AI)',
                    'icon' => 'fa-clipboard-check',
                ],
                'download_label' => 'تنزيل التغذية الراجعة',
                'status_labels' => [
                    'idle' => 'جاهز للاستخدام',
                    'pending' => 'في قائمة الانتظار...',
                    'processing' => 'قيد المعالجة...',
                    'done' => 'تم التقييم',
                    'failed' => 'فشل سابق',
                ],
            ],
            'form' => [
                'modal_title' => 'تقييم المقال الأكاديمي',
                'submit_label' => 'إرسال للتقييم',
                'sections' => [
                    [
                        'title' => 'التقييم المبدئي من مدرس المعلمين',
                        'layout' => 'grid-2',
                        'fields' => [
                            ['key' => 'teacher_name', 'type' => 'text', 'label' => 'اسم المعلم/الطالب', 'required' => true, 'placeholder' => 'مثال: أ. احمد على'],
                            ['key' => 'student_number', 'type' => 'number', 'label' => 'رقم المعلم/الطالب', 'placeholder' => 'مثال: 12345'],
                            [
                                'key' => 'teacher_supervisor',
                                'type' => 'select',
                                'label' => 'مدرس المعلمين',
                                'required' => true,
                                'options' => [
                                    ['value' => '', 'label' => '— اختر مدرس المعلمين —'],
                                    ['value' => 'عبدالرزاق الخطيب', 'label' => 'عبدالرزاق الخطيب'],
                                    ['value' => 'رزان خليفة', 'label' => 'رزان خليفة'],
                                ]
                            ],
                            ['key' => 'turnitin_percentage', 'type' => 'number', 'label' => 'نسبة الاقتباس وفق Turnitin', 'min' => 0, 'max' => 100, 'placeholder' => 'مثال: 15'],
                            [
                                'key' => 'references_match',
                                'type' => 'select',
                                'label' => 'توافق المراجع بين المتن وقائمة المراجع',
                                'options' => [
                                    ['value' => '', 'label' => '— اختر درجة التوافق —'],
                                    ['value' => 'توافق كامل', 'label' => 'توافق كامل'],
                                    ['value' => 'توافق جيد', 'label' => 'توافق جيد'],
                                    ['value' => 'توافق جزئي', 'label' => 'توافق جزئي'],
                                    ['value' => 'عدم توافق', 'label' => 'عدم توافق'],
                                ]
                            ],
                            [
                                'key' => 'formatting_compliance',
                                'type' => 'chips',
                                'label' => 'الالتزام بتعليمات الكتابة',
                                'options' => [
                                    'نوع الخط',
                                    'حجم الخط',
                                    'هيكلية واضحة',
                                    'وجود قائمة مراجع',
                                    'عدم وجود عناوين او ترقيم او نقاط',
                                ],
                            ],
                            ['key' => 'article_title', 'type' => 'text', 'label' => 'عنوان المقال', 'required' => true, 'placeholder' => 'عنوان المقال', 'full' => true],
                        ],
                    ],
                    [
                        'title' => 'نص المقال',
                        'layout' => 'custom',
                        'description' => 'قم بإضافة أقسام المقال (المقدمة، العرض، الخاتمة) مع إمكانية إضافة عدة أجزاء لكل قسم.',
                        'custom_ui' => true,
                    ],
                ],
            ],
            'result' => [
                'type' => 'custom',
                'title' => 'نتيجة تقييم المقال',
            ],
        ];

        $row = [
            'type_key' => 'article_evaluator_v1',
            'title' => 'مقيم المقالات الأكاديمية',
            'description' => 'تقييم آلي للمقالات الأكاديمية مع تغذية راجعة تفصيلية لكل جزء من أجزاء المقال.',
            'provider' => $provider,
            'model_name' => $modelName,
            'prompt_maker_class' => \App\Libraries\PromptMaker\ArticleEvaluatorPromptMaker::class,
            'template_path' => 'templates/feedback.docx',
            'system_prompt' => $systemPrompt,
            'ui_config_json' => json_encode($uiConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'js_module_path' => 'assets/js/students/sections/ai_article_evaluator_modal.js',
            'output_schema' => null,
            'is_enabled' => 1,
            'points_cost' => 30,
            // ─── سلوك الأداة ───
            'needs_task_context' => 1,  // تحتاج task_prompt (تعليمات المهمة)
            'needs_specialty' => 0,
            'task_prompt_mode' => 'append',
        ];


        $this->upsertType('article_evaluator_v1', $row);
    }

    protected function seedAcademicAnswerToolV2(string $provider, string $modelName): void
    {
        $systemPrompt = <<<'PROMPT'
أنت خبير أكاديمي متخصص في تقديم إجابات محددة استناداً إلى النصوص المرفقة في سياق المهمة بدقة.
تأكد من قراءة السؤال والنص المرفق بعناية تامة.
صغ إجاباتك بلغة عربية سليمة وواضحة، ولا تضف معلومات غير موجودة في النص المرجعي المرفق.
PROMPT;

        $uiConfig = [
            'panel' => [
                'button' => [
                    'label' => 'الإجابة الأكاديمية (الإصدار الثاني)',
                    'icon' => 'fa-graduation-cap',
                ],
                'status_labels' => [
                    'idle' => 'جاهز للإجابة',
                    'pending' => 'في قائمة الانتظار...',
                    'processing' => 'جاري البحث في النص...',
                    'done' => 'تمت الإجابة',
                    'failed' => 'تعذّر التنفيذ',
                ],
            ],
            'form' => [
                'modal_title' => 'الإجابة الأكاديمية (V2)',
                'submit_label' => 'اسأل الآن',
                'sections' => [
                    [
                        'title' => 'البيانات المطلوبة',
                        'layout' => 'grid-1',
                        'fields' => [
                            [
                                'key' => 'question',
                                'type' => 'textarea',
                                'label' => 'السؤال',
                                'required' => true,
                                'placeholder' => 'اكتب سؤالك الأكاديمي المرتبط بالنص المرفق هنا...'
                            ],
                            [
                                'key' => 'word_count',
                                'type' => 'number',
                                'label' => 'عدد الكلمات التقريبي للإجابة',
                                'min' => 50,
                                'max' => 1000,
                                'default' => 300,
                                'required' => true
                            ],
                            [
                                'key' => 'format',
                                'type' => 'select',
                                'label' => 'تنسيق الإجابة',
                                'default' => 'essay',
                                'options' => [
                                    ['value' => 'essay', 'label' => 'مقال مترابط (سردي)'],
                                    ['value' => 'structured', 'label' => 'نقاط وعناوين (مُنظَّم)'],
                                ]
                            ]
                        ],
                    ],
                ],
            ],
            'result' => [
                'type' => 'text',
                'title' => 'الإجابة',
                'fields' => [
                    ['key' => 'content', 'label' => 'محتوى الإجابة', 'render' => 'richtext'],
                ],
            ],
        ];

        $row = [
            'type_key' => 'academic_answer_v2',
            'title' => 'الإجابة الأكاديمية (الإصدار الثاني)',
            'description' => 'أداة الإجابة الأكاديمية المستندة حصراً إلى النص المرفق في المهمة دون البحث في الملفات الأخرى.',
            'provider' => $provider,
            'model_name' => $modelName,
            'prompt_maker_class' => \App\Libraries\PromptMaker\AcademicAnswerV2PromptMaker::class,
            'template_path' => null,
            'system_prompt' => $systemPrompt,
            'ui_config_json' => json_encode($uiConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'js_module_path' => '',
            'output_schema' => null,
            'is_enabled' => 1,
            // ─── سلوك الأداة ───
            'needs_task_context' => 1,  // تحتاج النص المرفق في task_prompt
            'needs_specialty' => 0,
            'task_prompt_mode' => 'ignore', // The PromptMaker itself retrieves $payload['task_prompt'] so we don't need appender.
        ];

        $this->upsertType('academic_answer_v2', $row);
    }

    // ─── أداة تقييم مخطط البحث الاستقصائي ─────────────────────────────────
    protected function seedInquiryPlanEvaluatorTool(string $provider, string $modelName): void
    {
        $systemPrompt = <<<'PROMPT'
أنت مقيّم أكاديمي خبير في برنامج الدبلوم التربوي، ومتخصص في تقييم مخططات البحث الاستقصائي (Action Research Plans).
مهمتك تقديم تغذية راجعة تفصيلية ومهنية لمخطط البحث الذي أعده الطالب-المعلم، وفق معايير البرنامج المعتمدة.

قواعد عامة:
- اكتب بأسلوب أكاديمي رصين، دقيق، ومباشر.
- أعد JSON صالحاً فقط؛ لا تكتب أي شيء خارج JSON.
- لا تستخدم markdown code fences.
- كن صريحاً في نقاط الضعف مع تقديم توجيهات تنفيذية واضحة.
PROMPT;

        $uiConfig = [
            'panel' => [
                'button' => [
                    'label' => 'تقييم المخطط (AI)',
                    'icon' => 'fa-microscope',
                ],
                'status_labels' => [
                    'idle' => 'جاهز للتقييم',
                    'pending' => 'في قائمة الانتظار...',
                    'processing' => 'قيد التقييم...',
                    'done' => 'تم التقييم',
                    'failed' => 'فشل التقييم',
                ],
            ],
            'form' => [
                'modal_title' => 'تقييم مخطط البحث الاستقصائي',
                'submit_label' => 'بدء التقييم',
                'sections' => [], // الواجهة تُبنى بالكامل من JS المخصص
            ],
            'result' => [
                'type' => 'text',
                'title' => 'نتيجة تقييم المخطط',
            ],
        ];

        $row = [
            'type_key' => 'inquiry_plan_evaluator_v1',
            'title' => 'تقييم مخطط البحث الاستقصائي',
            'description' => 'تقييم آلي شامل لمخطط البحث الاستقصائي (Action Research Plan) مع تغذية راجعة تفصيلية لكل قسم من الأقسام الستة.',
            'provider' => $provider,
            'model_name' => $modelName,
            'prompt_maker_class' => \App\Libraries\PromptMaker\InquiryPlanEvaluatorPromptMaker::class,
            'template_path' => null,          // لا يُنتج ملف DOCX — النتيجة تُعرض مباشرة
            'js_module_path' => 'assets/js/students/sections/ai_inquiry_plan_evaluator_modal.js',
            'system_prompt' => $systemPrompt,
            'ui_config_json' => json_encode($uiConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'output_schema' => null,
            'is_enabled' => 1,
            'points_cost' => 30,
            // ─── سلوك الأداة ───
            'needs_task_context' => 1,   // تقرأ task_prompt (معايير التقييم من المهمة)
            'needs_specialty' => 0,
            'task_prompt_mode' => 'append',
        ];

        $this->upsertType('inquiry_plan_evaluator_v1', $row);
    }

    protected function upsertType(string $typeKey, array $row): void
    {
        $builder = $this->db->table('ai_job_types');

        // استخدام REPLACE INTO لتجنب التكرار - يحذف السجل القديم ويضيف الجديد
        // أو استخدام INSERT ... ON DUPLICATE KEY UPDATE
        $existing = $builder->where('type_key', $typeKey)->get()->getRowArray();

        if ($existing) {
            // تحديث السجل الموجود
            $builder->where('type_key', $typeKey)->update($row);
        } else {
            // إضافة سجل جديد
            $builder->insert($row);
        }
    }

    protected function seedEvaluationIntegrationTool(string $provider, string $modelName): void
    {
        $systemPrompt = <<<'PROMPT'
Act like خبير في طرق ووسائل التقويم التربوي والتعليمي، ومصمم لمهام توظيف التقويم في العملية التعليمية وفق منهجيات التعلم الحديثة.
هدفك مساعدة المعلم الطالب في تخطيط التقويم الصفي وتأمل ممارساته.
أرجع المخرجات كـ JSON صالح فقط. إن وجدت نقص بالمدخلات الأساسية أعد JSON حسب بروتوكول النقص.
PROMPT;

        $uiConfig = [
            'panel' => [
                'button' => [
                    'label' => 'توظيف التقويم (AI)',
                    'icon' => 'fa-clipboard-check',
                ],
                'download_label' => 'تنزيل الخطة',
                'status_labels' => [
                    'idle' => 'جاهز للاستخدام',
                    'pending' => 'في قائمة الانتظار...',
                    'processing' => 'قيد المعالجة...',
                    'done' => 'تم الإنشاء',
                    'failed' => 'فشل سابق',
                ],
            ],
            'form' => [
                'modal_title' => 'مهمة توظيف التقويم في العملية التعليمية',
                'submit_label' => 'إنشاء المهمة',
                'sections' => [
                    [
                        'title' => 'البيانات الأساسية',
                        'layout' => 'grid-2',
                        'fields' => [
                            ['key' => 'teacher_name', 'type' => 'text', 'label' => 'المُعلّم الطّالب', 'required' => true],
                            ['key' => 'academic_number', 'type' => 'text', 'label' => 'الرقم الأكاديمي', 'required' => true],
                            ['key' => 'subject_name', 'type' => 'text', 'label' => 'المبحث', 'required' => true],
                        ],
                    ],
                    [
                        'title' => 'نتاجات التعلم (اختياري)',
                        'layout' => 'grid-2',
                        'fields' => [
                            ['key' => 'cognitive_outcome_input', 'type' => 'textarea', 'label' => 'النتاج المعرفي', 'placeholder' => 'اتركه فارغاً ليقوم الذكاء الاصطناعي بكتابته...'],
                            ['key' => 'skill_outcome_input', 'type' => 'textarea', 'label' => 'النتاج المهاري', 'placeholder' => 'اتركه فارغاً ليقوم الذكاء الاصطناعي بكتابته...'],
                        ]
                    ],
                    [
                        'title' => 'التوجيهات والسياق',
                        'layout' => 'grid-1',
                        'fields' => [
                            ['key' => 'lesson_hint', 'type' => 'textarea', 'label' => 'سياق الكفاية أو الدرس (اختياري)', 'placeholder' => 'أدخل أي توجيهات إضافية بخصوص الدرس المطلوب...'],
                        ]
                    ]
                ],
            ],
            'result' => [
                'type' => 'docx',
            ],
        ];

        $row = [
            'type_key' => 'evaluation_integration_v1',
            'title' => 'مهمة توظيف التقويم',
            'description' => 'إنشاء ملف مهمة توظيف التقويم في العملية التعليمية.',
            'provider' => $provider,
            'model_name' => $modelName,
            'prompt_maker_class' => \App\Libraries\PromptMaker\EvaluationIntegrationPromptMaker::class,
            'template_path' => 'templates/evaluation_integration_template.docx',
            'system_prompt' => $systemPrompt,
            'ui_config_json' => json_encode($uiConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'js_module_path' => '',
            'output_schema' => null,
            'is_enabled' => 1,
            'needs_task_context' => 0,
            'needs_specialty' => 0,
            'task_prompt_mode' => 'ignore',
        ];

        $this->upsertType('evaluation_integration_v1', $row);
    }

    protected function seedAssessmentPositionTool(string $provider, string $modelName): void
    {
        $systemPrompt = <<<'PROMPT'
أنت خبير تربوي متخصص في تخطيط وبناء المواقف التقييمية (Assessment Positions) حسب نموذج GRASPS.
مهمتك تصميم موقف تقييمي متكامل وواقعي بأسلوب علمي واضح. يجب أن يكون الموقف مرتبطاً بأهداف التعلم، وموجهاً نحو تحسين الأداء وتقديم إجابة بصيغة JSON.
PROMPT;

        $uiConfig = [
            'panel' => [
                'button' => [
                    'label' => 'الموقف التقييمي (AI)',
                    'icon' => 'fa-chalkboard-user',
                ],
                'download_label' => 'تنزيل الموقف التقييمي',
                'status_labels' => [
                    'idle' => 'جاهز للاستخدام',
                    'pending' => 'في قائمة الانتظار...',
                    'processing' => 'قيد المعالجة...',
                    'done' => 'تم الإنشاء',
                    'failed' => 'فشل سابق',
                ],
            ],
            'form' => [
                'modal_title' => 'بناء الموقف التقييمي',
                'submit_label' => 'إنشاء الموقف',
                'sections' => [
                    [
                        'title' => 'البيانات الأساسية',
                        'layout' => 'grid-2',
                        'fields' => [
                            ['key' => 'teacher_name', 'type' => 'text', 'label' => 'اسم المعلم', 'required' => true],
                            ['key' => 'school_name', 'type' => 'text', 'label' => 'اسم المدرسة', 'required' => true],
                            ['key' => 'school_class', 'type' => 'text', 'label' => 'الصف', 'required' => true],
                            ['key' => 'general_subject', 'type' => 'text', 'label' => 'المادة الدراسية', 'required' => true],
                            ['key' => 'general_unit', 'type' => 'text', 'label' => 'الوحدة الدراسية', 'required' => true],
                            ['key' => 'general_title', 'type' => 'text', 'label' => 'الموضوع', 'required' => true],
                            ['key' => 'learning_sources', 'type' => 'text', 'label' => 'الموارد المتاحة (اختياري)'],
                        ],
                    ],
                    [
                        'title' => 'أدوات التقويم (اختياري)',
                        'layout' => 'grid-2',
                        'fields' => [
                            [
                                'key' => 'assessment_strategy',
                                'type' => 'select',
                                'label' => 'استراتيجية التقويم',
                                'required' => false,
                                'options' => [
                                    ['value' => '', 'label' => 'دع الذكاء الاصطناعي يختار'],
                                    ['value' => 'استراتيجية الملاحظة', 'label' => 'استراتيجية الملاحظة'],
                                    ['value' => 'استراتيجية التواصل', 'label' => 'استراتيجية التواصل'],
                                    ['value' => 'التقويم المعتمد على الأداء', 'label' => 'التقويم المعتمد على الأداء'],
                                    ['value' => 'المراجعة للذات', 'label' => 'المراجعة للذات'],
                                    ['value' => 'الورقة والقلم', 'label' => 'الورقة والقلم'],
                                ],
                            ],
                            [
                                'key' => 'assessment_tool',
                                'type' => 'select',
                                'label' => 'أداة التقويم',
                                'required' => false,
                                'options' => [
                                    ['value' => '', 'label' => 'دع الذكاء الاصطناعي يختار'],
                                    ['value' => 'قائمة الشطب', 'label' => 'قائمة الشطب'],
                                    ['value' => 'سلم التقدير', 'label' => 'سلم التقدير'],
                                    ['value' => 'سلم التقدير اللفظي', 'label' => 'سلم التقدير اللفظي'],
                                    ['value' => 'السجل الوصفي', 'label' => 'السجل الوصفي'],
                                    ['value' => 'سجل سير التعلم', 'label' => 'سجل سير التعلم'],
                                    ['value' => 'أسئلة وإجابات', 'label' => 'أسئلة وإجابات'],
                                    ['value' => 'مقابلة', 'label' => 'مقابلة'],
                                    ['value' => 'يوميات الطالب', 'label' => 'يوميات الطالب'],
                                    ['value' => 'ملف الطالب', 'label' => 'ملف الطالب'],
                                    ['value' => 'مقياس التقدير', 'label' => 'مقياس التقدير'],
                                    ['value' => 'اختبار قصير', 'label' => 'اختبار قصير'],
                                    ['value' => 'اختبار قياسي', 'label' => 'اختبار قياسي'],
                                ]
                            ]
                        ],
                    ],
                    [
                        'title' => 'توجيهات إضافية',
                        'layout' => 'grid-1',
                        'fields' => [
                            ['key' => 'additional_instructions', 'type' => 'textarea', 'label' => 'توجيهات إضافية للتحليل (اختياري)', 'placeholder' => 'أدخل أي توجيهات إضافية ترغب في أخذها بعين الاعتبار عند بناء الموقف التقييمي...'],
                        ]
                    ]
                ],
            ],
            'result' => [
                'type' => 'docx',
            ],
        ];

        $row = [
            'type_key' => 'assessment_position_v1',
            'title' => 'الموقف التقييمي',
            'description' => 'بناء وتصميم موقف تقييمي باستخدام نموذج GRASPS.',
            'provider' => $provider,
            'model_name' => $modelName,
            'prompt_maker_class' => \App\Libraries\PromptMaker\AssessmentPositionPromptMaker::class,
            'template_path' => 'templates/assessment_position_template.docx',
            'system_prompt' => $systemPrompt,
            'ui_config_json' => json_encode($uiConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'js_module_path' => '',
            'output_schema' => null,
            'is_enabled' => 1,
            'needs_task_context' => 0,
            'needs_specialty' => 0,
            'task_prompt_mode' => 'ignore',
        ];

        $this->upsertType('assessment_position_v1', $row);
    }

    protected function seedInquiryQuestionsTool(string $provider, string $modelName): void
    {
        $systemPrompt = <<<'PROMPT'
أنت خبير في البحث الإجرائي التربوي (Action Research) ومتخصص في مساعدة المعلمين على صياغة أسئلة استقصائية (إجرائية) دقيقة وفق أحدث المعايير التربوية.
مهمتك توليد 3 أسئلة استقصائية قوية مصاغة باحترافية، مع اختيار الاستراتيجية والأداة المناسبة للمادة والدرس والصف.
أعد الإجابة حصراً بصيغة JSON صالحة دون أي نص خارجي.
PROMPT;

        $uiConfig = [
            'panel' => [
                'button' => [
                    'label' => 'توليد أسئلة الاستقصاء (AI)',
                    'icon' => 'fa-magnifying-glass-chart',
                ],
                'status_labels' => [
                    'idle' => 'جاهز للتوليد',
                    'pending' => 'في قائمة الانتظار...',
                    'processing' => 'قيد الصياغة...',
                    'done' => 'تم توليد الأسئلة',
                    'failed' => 'تعذّر التنفيذ',
                ],
            ],
            'form' => [
                'modal_title' => 'توليد أسئلة الاستقصاء',
                'submit_label' => 'توليد الأسئلة',
                'sections' => [
                    [
                        'title' => 'البيانات الأساسية',
                        'layout' => 'grid-2',
                        'fields' => [
                            [
                                'key' => 'subject_name',
                                'type' => 'text',
                                'label' => 'المادة الدراسية',
                                'required' => true,
                                'placeholder' => 'مثال: الرياضيات، العلوم، اللغة العربية',
                            ],
                            [
                                'key' => 'lesson_title',
                                'type' => 'text',
                                'label' => 'عنوان الدرس',
                                'required' => true,
                                'placeholder' => 'مثال: الأعداد النسبية، الخلية الحيوانية',
                            ],
                            [
                                'key' => 'grade_level',
                                'type' => 'text',
                                'label' => 'الصف الدراسي',
                                'required' => true,
                                'placeholder' => 'مثال: الصف السابع الأساسي',
                            ],
                            [
                                'key' => 'student_gender',
                                'type' => 'select',
                                'label' => 'جنس الطلبة',
                                'required' => true,
                                'options' => [
                                    ['value' => 'male', 'label' => 'ذكور'],
                                    ['value' => 'female', 'label' => 'إناث'],
                                    ['value' => 'mixed', 'label' => 'مختلط'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'title' => 'تفاصيل إضافية (اختياري)',
                        'layout' => 'grid-1',
                        'fields' => [
                            [
                                'key' => 'observed_challenge',
                                'type' => 'textarea',
                                'label' => 'التحدي أو المشكلة الملاحظة داخل الغرفة الصفية',
                                'placeholder' => 'مثال: ضعف تفاعل الطلبة مع المفاهيم المجردة، صعوبة في الفهم القرائي، عدم القدرة على ربط المعلومات بالواقع...',
                                'hint' => 'اذكر تحدياً حقيقياً لاحظته لتوجيه الذكاء الاصطناعي نحو أسئلة أكثر دقة وملاءمة.',
                            ],
                        ],
                    ],
                ],
            ],
            'result' => [
                'type' => 'text',
                'title' => 'أسئلة الاستقصاء المقترحة',
                'fields' => [
                    ['key' => 'chosen_strategy', 'label' => 'الاستراتيجية المختارة'],
                    ['key' => 'chosen_tool', 'label' => 'الأداة المختارة'],
                    ['key' => 'strategy_justification', 'label' => 'مبرر الاختيار', 'render' => 'richtext'],
                    ['key' => 'questions', 'label' => 'أسئلة الاستقصاء', 'render' => 'ordered_list'],
                ],
            ],
        ];

        $row = [
            'type_key' => 'inquiry_questions_v1',
            'title' => 'توليد أسئلة الاستقصاء',
            'description' => 'مساعدة المعلم في صياغة 3 أسئلة استقصائية (إجرائية) وفق معايير البحث الإجرائي التربوي.',
            'provider' => $provider,
            'model_name' => $modelName,
            'prompt_maker_class' => \App\Libraries\PromptMaker\InquiryQuestionsPromptMaker::class,
            'template_path' => null,
            'system_prompt' => $systemPrompt,
            'ui_config_json' => json_encode($uiConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'js_module_path' => '',
            'output_schema' => null,
            'is_enabled' => 1,
            // ─── سلوك الأداة ───
            'needs_task_context' => 0,  // لا تحتاج سياق مهمة
            'needs_specialty' => 0,
            'task_prompt_mode' => 'ignore',
        ];

        $this->upsertType('inquiry_questions_v1', $row);
    }
    protected function seedFormativeReflectiveEssayTool(string $provider, string $modelName): void
    {
        $systemPrompt = <<<'PROMPT'
أنت خبير تربوي في تحليل وتقييم الممارسات التعليمية باستخدام نموذج جيبس (Gibbs Reflective Cycle).
مهمتك كتابة مقالة تأملية تكوينية لمعلم الدبلوم حول الممارسة المختارة في بيئة التعلم.
طول المقال: يجب أن يكون بين 1350 و 1650 كلمة بشكل صارم. توسع في الشرح والتحليل للوصول إلى هذا الحد.

تعليمات هامة جداً:
- يجب أن يكون المخرج عبارة عن كائن JSON صالح تماماً ويحتوي على مفتاحين فقط: "article_title" (العنوان المقترح للمقال) و "content" (محتوى المقال كاملاً).
- لا تقم بتجزئة المقال إلى حقول JSON متعددة، بل اجعل المحتوى بأكمله في الحقل "content".
- اكتب محتوى المقال بشكل متصل ومترابط دون استخدام أي عناوين فرعية (No Subheadings).
- استخدم تنسيق الفقرات العادية (Newlines) بين الأجزاء للانتقال بسلاسة دون عناوين واضحة للمراحل.
- المقالة يجب أن تتضمن جزأين رئيسيين مدمجين سياقياً:
  الجزء الأول: رحلتي في الدبلوم بوصفى معلماً مختصاً (المقدمة، الوصف دون تفسير، المشاعر، تقييم وتحليل الموقف مع ربطه بثلاثة مصادر تربوية، الاستنتاجات، خطة العمل).
  الجزء الثاني: أثر الدبلوم والتطوير المهني (أثر المساقات النظرية والتطبيقية، خطة عمل التحديات، الخاتمة).
- لا تطلب معلومات إضافية.
PROMPT;

        $uiConfig = [
            'panel' => [
                'button' => [
                    'label' => 'إنشاء مقال تأملي تكويني',
                    'icon' => 'fa-pen-fancy',
                ],
                'download_label' => 'تنزيل المقال',
                'status_labels' => [
                    'idle' => 'جاهز للاستخدام',
                    'pending' => 'في قائمة الانتظار...',
                    'processing' => 'قيد المعالجة...',
                    'done' => 'تم إنشاء المقال',
                    'failed' => 'فشل الإنشاء',
                ],
            ],
            'form' => [
                'modal_title' => 'إعدادات المقال التأملي التكويني (نوع 12)',
                'submit_label' => 'إنشاء المقال',
                'sections' => [
                    [
                        'title' => 'البيانات الأساسية',
                        'layout' => 'grid-2',
                        'fields' => [
                            ['key' => 'teacher_specialty', 'type' => 'text', 'label' => 'تخصص المعلم', 'required' => true, 'placeholder' => 'مثال: الرياضيات'],
                            ['key' => 'grade_level', 'type' => 'text', 'label' => 'الصف الذي يدرسه', 'required' => true, 'placeholder' => 'مثال: العاشر الأساسي'],
                            ['key' => 'teacher_name_id', 'type' => 'text', 'label' => 'الاسم والرقم الوزاري', 'required' => true, 'placeholder' => 'مثال: أحمد محمد (123456)'],
                        ],
                    ],
                    [
                        'title' => 'تفاصيل الموقف',
                        'layout' => 'grid-1',
                        'fields' => [
                            ['key' => 'situation_description', 'type' => 'textarea', 'label' => 'وصف عام للموقف (يفضل)', 'required' => false, 'placeholder' => 'قدم وصفاً موجزاً للموقف التعليمي الذي تود التأمل فيه...'],
                        ],
                    ],
                ],
            ],
            'result' => [
                'type' => 'docx',
            ],
        ];

        $row = [
            'type_key' => 'formative_reflective_essay_v1',
            'title' => 'المقال التأملي التكويني',
            'description' => 'إنشاء مقال تأملي تكويني وفق دورة جيبس.',
            'provider' => $provider,
            'model_name' => $modelName,
            'prompt_maker_class' => \App\Libraries\PromptMaker\FormativeReflectiveEssayPromptMaker::class,
            'template_path' => 'templates/reflective_essay_template.docx',
            'system_prompt' => $systemPrompt,
            'ui_config_json' => json_encode($uiConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'js_module_path' => '',
            'output_schema' => null,
            'is_enabled' => 1,
            'needs_task_context' => 1,
            'needs_specialty' => 0,
            'task_prompt_mode' => 'ignore',
            'points_cost' => 100, // Cost as requested
        ];

        $this->upsertType('formative_reflective_essay_v1', $row);

        // Seeding Tool Placement for Task Type 12
        $builder = $this->db->table('ai_tool_placements');
        $existing = $builder->where('type_key', 'formative_reflective_essay_v1')
            ->where('task_type_id', 12)
            ->get()->getRowArray();

        if (!$existing) {
            $builder->insert([
                'type_key' => 'formative_reflective_essay_v1',
                'task_type_id' => 12,
                'button_label' => 'إنشاء المقال',
                'button_icon' => 'fa-pen-fancy',
                'min_subscription_level' => 0
            ]);
        }
    }

    protected function seedRewrittenReflectiveEssayTool(string $provider, string $modelName): void
    {
        $systemPrompt = <<<'PROMPT'
أنت خبير تربوي في تحليل وتقييم الممارسات التعليمية وتحسينها بناءً على التغذية الراجعة.
مهمتك إعادة صياغة مقالة تأملية لمعلم الدبلوم، مع معالجة التغذية الراجعة المقدمة بالكامل.
طول المقال: يجب أن يكون بين 1350 و 1650 كلمة بشكل صارم. توسع في الشرح والتحليل للوصول إلى هذا الحد.

تعليمات هامة جداً:
- يجب أن يكون المخرج عبارة عن كائن JSON صالح تماماً ويحتوي على مفتاحين فقط: "article_title" (العنوان المقترح للمقال) و "content" (محتوى المقال كاملاً).
- اكتب محتوى المقال بشكل متصل ومترابط دون استخدام أي عناوين فرعية (No Subheadings).
- استخدم تنسيق الفقرات العادية (Newlines) بين الأجزاء للانتقال بسلاسة.
- ادمج التغذية الراجعة بذكاء في المقال لتصحيح المسار وإثراء المحتوى التأملي.
- المقالة يجب أن تتضمن جزأين رئيسيين مدمجين سياقياً:
  الجزء الأول: رحلتي في الدبلوم بوصفى معلماً مختصاً.
  الجزء الثاني: أثر الدبلوم والتطوير المهني.
PROMPT;

        $uiConfig = [
            'panel' => [
                'button' => [
                    'label' => 'إعادة صياغة المقال',
                    'icon' => 'fa-pen-to-square',
                ],
                'download_label' => 'تنزيل المقال المعدل',
                'status_labels' => [
                    'idle' => 'جاهز للاستخدام',
                    'pending' => 'في قائمة الانتظار...',
                    'processing' => 'قيد المعالجة...',
                    'done' => 'تم إعادة صياغة المقال',
                    'failed' => 'فشل الإنشاء',
                ],
            ],
            'form' => [
                'modal_title' => 'إعادة صياغة المقال التأملي بناءً على التغذية الراجعة',
                'submit_label' => 'إعادة صياغة المقال',
                'sections' => [
                    [
                        'title' => 'البيانات الأساسية',
                        'layout' => 'grid-2',
                        'fields' => [
                            ['key' => 'teacher_specialty', 'type' => 'text', 'label' => 'تخصص المعلم', 'required' => true, 'placeholder' => 'مثال: الرياضيات'],
                            ['key' => 'grade_level', 'type' => 'text', 'label' => 'الصف الذي يدرسه', 'required' => true, 'placeholder' => 'مثال: العاشر الأساسي'],
                            ['key' => 'teacher_name_id', 'type' => 'text', 'label' => 'الاسم والرقم الوزاري', 'required' => true, 'placeholder' => 'مثال: أحمد محمد (123456)'],
                        ],
                    ],
                    [
                        'title' => 'تفاصيل المقال والملاحظات',
                        'layout' => 'grid-1',
                        'fields' => [
                            ['key' => 'original_essay', 'type' => 'textarea', 'label' => 'نص المقال الحالي', 'required' => true, 'placeholder' => 'ضع هنا النص الكامل للمقال الذي كتبته...'],
                            ['key' => 'feedback', 'type' => 'textarea', 'label' => 'التغذية الراجعة', 'required' => true, 'placeholder' => 'ضع هنا ملاحظات المشرف أو الموجه التي ترغب بمعالجتها...'],
                        ],
                    ],
                ],
            ],
            'result' => [
                'type' => 'docx',
            ],
        ];

        $row = [
            'type_key' => 'rewritten_reflective_essay_v1',
            'title' => 'إعادة صياغة المقال التأملي',
            'description' => 'إعادة صياغة وتحسين المقال التأملي بناءً على التغذية الراجعة.',
            'provider' => $provider,
            'model_name' => $modelName,
            'prompt_maker_class' => \App\Libraries\PromptMaker\RewrittenReflectiveEssayPromptMaker::class,
            'template_path' => 'templates/reflective_essay_template.docx',
            'system_prompt' => $systemPrompt,
            'ui_config_json' => json_encode($uiConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'js_module_path' => '',
            'output_schema' => null,
            'is_enabled' => 1,
            'needs_task_context' => 1,
            'needs_specialty' => 0,
            'task_prompt_mode' => 'ignore',
            'points_cost' => 150, // Cost 150 as requested
        ];

        $this->upsertType('rewritten_reflective_essay_v1', $row);

        // Seeding Tool Placement for Task Type 13
        $builder = $this->db->table('ai_tool_placements');
        $existing = $builder->where('type_key', 'rewritten_reflective_essay_v1')
            ->where('task_type_id', 13)
            ->get()->getRowArray();

        if (!$existing) {
            $builder->insert([
                'type_key' => 'rewritten_reflective_essay_v1',
                'task_type_id' => 13,
                'button_label' => 'إعادة صياغة المقال',
                'button_icon' => 'fa-pen-to-square',
                'min_subscription_level' => 0
            ]);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class InquiryToolsSeeder extends Seeder
{
    public function run()
    {
        $provider  = env('AI_PROVIDER', 'gemini');
        $modelName = $provider === 'openai'
            ? env('AI_OPENAI_MODEL', 'gpt-4o-mini')
            : env('AI_GEMINI_MODEL', 'gemini-1.5-pro');

        $this->seedDataTools($provider, $modelName);
        $this->seedAcademicWriter($provider, $modelName);
        $this->seedTemplatesGenerator($provider, $modelName);
    }

    protected function upsertType(string $typeKey, array $row): void
    {
        $db = \Config\Database::connect();
        $builder = $db->table('ai_job_types');
        $existing = $builder->where('type_key', $typeKey)->get()->getRow();
        
        $row['updated_at'] = date('Y-m-d H:i:s');
        
        if ($existing) {
            $builder->where('id', $existing->id)->update($row);
            echo "Updated tool: {$typeKey}\n";
        } else {
            $row['created_at'] = date('Y-m-d H:i:s');
            $builder->insert($row);
            echo "Inserted tool: {$typeKey}\n";
        }
    }

    protected function seedDataTools(string $provider, string $modelName): void
    {
        $systemPrompt = <<<'PROMPT'
أنت خبير قياس وتقويم في التعليم. مطلوب منك تصميم أداة جمع بيانات (Data Collection Tool) لبحث استقصائي.
اقرأ المدخلات بعناية، وصمم الأداة المطلوبة بشكل دقيق وعلمي وتربوي.
النتيجة يجب أن تكون ككائن JSON يحتوي على مفتاحين فقط:
- title: عنوان الأداة.
- content: محتوى الأداة منسق بالـ Markdown (مثلاً جداول للـ Rubric أو بطاقة الملاحظة، قوائم للاستبانة أو المقابلة).
لا تضف أي نص خارج كائن الـ JSON. يجب أن تكون لغتك عربية فصحى سليمة وموجهة بشكل مناسب للفئة المستهدفة.
PROMPT;

        $uiConfig = [
            'panel' => [
                'button' => [
                    'label' => 'تصميم أداة قياس (AI)',
                    'icon' => 'fa-table-list',
                ],
                'status_labels' => [
                    'idle' => 'جاهز للتصميم',
                    'pending' => 'في قائمة الانتظار...',
                    'processing' => 'جاري التصميم...',
                    'done' => 'تم التصميم بنجاح',
                    'failed' => 'فشل التصميم',
                ],
            ],
            'form' => [
                'modal_title' => 'مُصمم أدوات جمع البيانات',
                'submit_label' => 'تصميم الأداة',
                'sections' => [
                    [
                        'title' => 'بيانات الأداة المطلوبة',
                        'layout' => 'grid-1',
                        'fields' => [
                            ['key' => 'topic', 'type' => 'text', 'label' => 'موضوع الدرس/البحث', 'required' => true, 'placeholder' => 'مثال: أثر التعلم باللعب في مادة الرياضيات'],
                            ['key' => 'goal', 'type' => 'textarea', 'label' => 'ماذا تريد أن تقيس؟ (الهدف)', 'required' => true, 'placeholder' => 'مثال: قياس تفاعل الطلبة أثناء العمل الجماعي ومدى التزامهم بالقواعد'],
                        ],
                    ],
                    [
                        'title' => 'خصائص الأداة',
                        'layout' => 'grid-2',
                        'fields' => [
                            ['key' => 'tool_type', 'type' => 'select', 'label' => 'نوع الأداة', 'required' => true, 'options' => [
                                ['value' => 'سلم تقدير لفظي (Rubric)', 'label' => 'سلم تقدير لفظي (Rubric)'],
                                ['value' => 'بطاقة ملاحظة (Checklist)', 'label' => 'بطاقة ملاحظة (Checklist)'],
                                ['value' => 'استبانة (Questionnaire)', 'label' => 'استبانة (Questionnaire)'],
                                ['value' => 'أسئلة مقابلة (Interview)', 'label' => 'أسئلة مقابلة (Interview)'],
                            ]],
                            ['key' => 'audience', 'type' => 'select', 'label' => 'الفئة المستهدفة', 'required' => true, 'options' => [
                                ['value' => 'الطلبة (المرحلة الأساسية)', 'label' => 'الطلبة (المرحلة الأساسية)'],
                                ['value' => 'الطلبة (المرحلة العليا/الثانوية)', 'label' => 'الطلبة (المرحلة العليا/الثانوية)'],
                                ['value' => 'المعلمين / الزملاء', 'label' => 'المعلمين / الزملاء'],
                                ['value' => 'أولياء الأمور', 'label' => 'أولياء الأمور'],
                            ]],
                        ],
                    ],
                ],
            ],
            'result' => [
                'type' => 'docx',
                'title' => 'أداة جمع البيانات',
                'fields' => [
                    ['key' => 'title', 'label' => 'عنوان الأداة'],
                    ['key' => 'content', 'label' => 'المحتوى', 'render' => 'richtext'],
                ],
            ],
        ];

        $row = [
            'type_key'           => 'inquiry_data_tools_v1',
            'title'              => 'مُصمم أدوات جمع البيانات',
            'description'        => 'تصميم بطاقات ملاحظة، استبانات، ومقاييس تقدير (Rubrics) للبحوث الاستقصائية.',
            'provider'           => $provider,
            'model_name'         => $modelName,
            'prompt_maker_class' => \App\Libraries\PromptMaker\InquiryDataToolsPromptMaker::class,
            'template_path'      => 'templates/blank_template.docx',
            'system_prompt'      => $systemPrompt,
            'ui_config_json'     => json_encode($uiConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'js_module_path'     => '',
            'is_enabled'         => 1,
            'needs_task_context' => 0,
            'needs_specialty'    => 0,
            'task_prompt_mode'   => 'ignore',
        ];

        $this->upsertType('inquiry_data_tools_v1', $row);
    }

    protected function seedAcademicWriter(string $provider, string $modelName): void
    {
        $systemPrompt = <<<'PROMPT'
أنت مشرف أكاديمي خبير في كتابة أبحاث الإجرائية والاستقصائية للمعلمين.
مطلوب منك كتابة مسودة أكاديمية رصينة وموثقة بشكل مبدئي بناءً على المدخلات المقدمة.
تأكد من التركيز على الأجزاء التي طلبها المستخدم فقط (مثل المقدمة، الأدب التربوي، الاعتبارات الأخلاقية).
النتيجة يجب أن تكون ككائن JSON يحتوي على مفتاحين فقط:
- title: عنوان البحث المقترح أو عنوان القسم.
- content: المحتوى منسق بالـ Markdown بشكل فقرات واضحة مع عناوين فرعية (Bold).
لا تضف أي نص خارج كائن الـ JSON. استخدم لغة علمية رصينة وموضوعية.
PROMPT;

        $uiConfig = [
            'panel' => [
                'button' => [
                    'label' => 'المساعد الأكاديمي (AI)',
                    'icon' => 'fa-pen-nib',
                ],
                'status_labels' => [
                    'idle' => 'جاهز للكتابة',
                    'pending' => 'في قائمة الانتظار...',
                    'processing' => 'جاري الكتابة...',
                    'done' => 'تمت الكتابة بنجاح',
                    'failed' => 'فشلت الكتابة',
                ],
            ],
            'form' => [
                'modal_title' => 'مُساعد الكتابة الأكاديمية',
                'submit_label' => 'توليد المسودة',
                'sections' => [
                    [
                        'title' => 'تفاصيل البحث',
                        'layout' => 'grid-1',
                        'fields' => [
                            ['key' => 'topic', 'type' => 'text', 'label' => 'عنوان البحث أو موضوعه', 'required' => true, 'placeholder' => 'مثال: استخدام استراتيجية التعلم باللعب في تحصيل الرياضيات'],
                            ['key' => 'main_question', 'type' => 'textarea', 'label' => 'السؤال الرئيسي للبحث أو المشكلة', 'required' => true, 'placeholder' => 'مثال: ما أثر استخدام التعلم باللعب على استيعاب الطلاب لجدول الضرب؟'],
                        ],
                    ],
                    [
                        'title' => 'أجزاء المسودة والكلمات المفتاحية',
                        'layout' => 'grid-2',
                        'fields' => [
                            [
                                'key' => 'sections',
                                'type' => 'chips',
                                'label' => 'الأجزاء المطلوب كتابتها',
                                'max' => 3,
                                'options' => ['المقدمة والمبررات', 'خلفية نظرية (أدب تربوي)', 'الاعتبارات الأخلاقية'],
                            ],
                            ['key' => 'keywords', 'type' => 'textarea', 'label' => 'كلمات مفتاحية / نقاط للتركيز عليها (اختياري)', 'placeholder' => 'مثال: دافعية التعلم، الاندماج، بيئة آمنة'],
                        ],
                    ],
                ],
            ],
            'result' => [
                'type' => 'docx',
                'title' => 'المسودة الأكاديمية',
                'fields' => [
                    ['key' => 'title', 'label' => 'العنوان'],
                    ['key' => 'content', 'label' => 'المحتوى', 'render' => 'richtext'],
                ],
            ],
        ];

        $row = [
            'type_key'           => 'inquiry_academic_writer_v1',
            'title'              => 'مُساعد الكتابة الأكاديمية',
            'description'        => 'توليد مسودات علمية للمقدمة، الأدب التربوي، والاعتبارات الأخلاقية.',
            'provider'           => $provider,
            'model_name'         => $modelName,
            'prompt_maker_class' => \App\Libraries\PromptMaker\InquiryAcademicWriterPromptMaker::class,
            'template_path'      => 'templates/blank_template.docx',
            'system_prompt'      => $systemPrompt,
            'ui_config_json'     => json_encode($uiConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'js_module_path'     => '',
            'is_enabled'         => 1,
            'needs_task_context' => 0,
            'needs_specialty'    => 0,
            'task_prompt_mode'   => 'ignore',
        ];

        $this->upsertType('inquiry_academic_writer_v1', $row);
    }

    protected function seedTemplatesGenerator(string $provider, string $modelName): void
    {
        $systemPrompt = <<<'PROMPT'
أنت سكرتير إداري وخبير في كتابة الخطابات الرسمية في المدارس.
مطلوب منك بناء خطاب رسمي / نموذج إداري بناءً على المدخلات، موجه للجهة المناسبة.
يجب أن يكون الخطاب مهذباً جداً، ويحتوي على مساحات مخصصة للتوقيع والتاريخ.
النتيجة يجب أن تكون ككائن JSON يحتوي على مفتاحين فقط:
- title: عنوان الخطاب أو النموذج.
- content: محتوى الخطاب منسق بالـ Markdown.
لا تضف أي نص خارج كائن الـ JSON.
PROMPT;

        $uiConfig = [
            'panel' => [
                'button' => [
                    'label' => 'النماذج الإدارية (AI)',
                    'icon' => 'fa-file-signature',
                ],
                'status_labels' => [
                    'idle' => 'جاهز لتوليد النموذج',
                    'pending' => 'في قائمة الانتظار...',
                    'processing' => 'جاري توليد النموذج...',
                    'done' => 'تم التوليد بنجاح',
                    'failed' => 'فشل التنفيذ',
                ],
            ],
            'form' => [
                'modal_title' => 'مُولّد النماذج الإدارية والموافقات',
                'submit_label' => 'توليد النموذج',
                'sections' => [
                    [
                        'title' => 'تفاصيل النموذج والباحث',
                        'layout' => 'grid-2',
                        'fields' => [
                            ['key' => 'template_type', 'type' => 'select', 'label' => 'نوع النموذج المطلوب', 'required' => true, 'options' => [
                                ['value' => 'موافقة ولي أمر (Consent Form)', 'label' => 'موافقة ولي أمر'],
                                ['value' => 'رسالة تسهيل مهمة لمدير المدرسة', 'label' => 'رسالة تسهيل مهمة لمدير المدرسة'],
                                ['value' => 'إشعار دعوة لزميل لحضور حصة', 'label' => 'إشعار دعوة لزميل لحضور حصة'],
                            ]],
                            ['key' => 'teacher_name', 'type' => 'text', 'label' => 'اسم المعلم / الباحث', 'required' => true],
                            ['key' => 'school_grade', 'type' => 'text', 'label' => 'اسم المدرسة والصف', 'required' => true, 'placeholder' => 'مثال: مدرسة الأمل - الصف الرابع'],
                        ],
                    ],
                    [
                        'title' => 'معلومات البحث والأنشطة',
                        'layout' => 'grid-1',
                        'fields' => [
                            ['key' => 'research_summary', 'type' => 'textarea', 'label' => 'موضوع البحث بإيجاز', 'required' => true],
                            [
                                'key' => 'activities',
                                'type' => 'chips',
                                'label' => 'أنشطة تتطلب موافقة صريحة',
                                'max' => 5,
                                'options' => ['التقاط صور / فيديو', 'تسجيل صوتي', 'تطبيق اختبارات إضافية', 'مشاركة نتائج الطالب لأغراض بحثية'],
                            ],
                        ],
                    ],
                ],
            ],
            'result' => [
                'type' => 'docx',
                'title' => 'النموذج الإداري',
                'fields' => [
                    ['key' => 'title', 'label' => 'عنوان الخطاب'],
                    ['key' => 'content', 'label' => 'محتوى الخطاب', 'render' => 'richtext'],
                ],
            ],
        ];

        $row = [
            'type_key'           => 'inquiry_templates_v1',
            'title'              => 'مُولّد النماذج الإدارية',
            'description'        => 'إنشاء قوالب ونماذج لخطابات الموافقة من أولياء الأمور أو إدارة المدرسة.',
            'provider'           => $provider,
            'model_name'         => $modelName,
            'prompt_maker_class' => \App\Libraries\PromptMaker\InquiryTemplatesPromptMaker::class,
            'template_path'      => 'templates/blank_template.docx',
            'system_prompt'      => $systemPrompt,
            'ui_config_json'     => json_encode($uiConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'js_module_path'     => '',
            'is_enabled'         => 1,
            'needs_task_context' => 0,
            'needs_specialty'    => 0,
            'task_prompt_mode'   => 'ignore',
        ];

        $this->upsertType('inquiry_templates_v1', $row);
    }
}

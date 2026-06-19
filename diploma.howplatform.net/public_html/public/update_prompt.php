<?php
define('FCPATH', __DIR__ . '/');
require FCPATH . '../app/Config/Paths.php';
$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '\\/ ') . '/bootstrap.php';

$provider  = env('AI_PROVIDER', 'gemini');
$modelName = $provider === 'openai'
    ? env('AI_OPENAI_MODEL', 'gpt-4o-mini')
    : env('AI_GEMINI_MODEL', 'gemini-1.5-pro');

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
                    ['key' => 'tone', 'type' => 'select', 'label' => 'نبرة الرد', 'default' => 'critical', 'options' => [
                        ['value' => 'formal', 'label' => 'رسمية'],
                        ['value' => 'friendly', 'label' => 'ودّية'],
                        ['value' => 'critical', 'label' => 'ناقدة بنّاءة'],
                        ['value' => 'motivational', 'label' => 'تحفيزية'],
                    ]],
                    ['key' => 'audience', 'type' => 'select', 'label' => 'الجمهور المستهدف', 'default' => 'teachers', 'options' => [
                        ['value' => 'teachers', 'label' => 'المعلمين'],
                        ['value' => 'students', 'label' => 'الطلبة'],
                        ['value' => 'admins', 'label' => 'إداريي المنصة'],
                        ['value' => 'parents', 'label' => 'أولياء الأمور'],
                    ]],
                    [
                        'key' => 'highlights',
                        'type' => 'chips',
                        'label' => 'محاور يجب التركيز عليها (حتى 3)',
                        'max' => 3,
                        'options' => [
                            'نواتج التعلم', 'استراتيجيات التدريس', 'أساليب التقويم', 'الدمج والتكييف', 'التسلسل المنطقي'
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
];

$db = \Config\Database::connect();
$builder = $db->table('ai_job_types');
$existing = $builder->where('type_key', 'forum_reply_v2')->get()->getRowArray();

if ($existing) {
    $builder->where('type_key', 'forum_reply_v2')->update($row);
    echo "Successfully updated forum_reply_v2 in DB!\n";
} else {
    $builder->insert($row);
    echo "Successfully inserted forum_reply_v2 in DB!\n";
}

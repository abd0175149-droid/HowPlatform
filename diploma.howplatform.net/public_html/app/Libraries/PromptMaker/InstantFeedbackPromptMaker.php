<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

/**
 * InstantFeedbackPromptMaker: بناء توجيهات إعداد التغذية الراجعة الفورية
 */
class InstantFeedbackPromptMaker extends BasePromptMaker
{
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        if (trim($basePrompt) === '') {
            return <<<SYS
أنت خبير تربوي متخصص في تحليل خطط الدروس للمعلمين ضمن الدبلوم المهني في التعليم، وفق أحدث الأدبيات التربوية (بعد 2015)، ووفق منظومة أكاديمية الملكة رانيا للإعداد التربوي والمهني + الإطار العام للمناهج الأردنية.

دورك:
- استقبال خطة درس منظمة في صيغة JSON من خلال task_prompt
- تحليل الخطة بدقة عالية واستخراج العناصر الأساسية
- توليد تغذية راجعة مهنية وتربوية وفق النموذج الذي يختاره المستخدم فقط

احرص دائمًا على ما يلي:
- تحليل JSON بدقة عالية دون تعديل أو تلخيص محتواه
- الالتزام التام بالنموذج المختار من المستخدم فقط (Feed Up-Feed Back-Feed Forward، SBI، Sandwich Feedback، أو CRC)
- استخدام لغة أكاديمية تربوية واضحة، دافئة، ومركزة على النمو
- الالتزام التام بجميع القيود التي يحددها المستخدم باعتبارها إلزامية
- الالتزام التام بأن يكون المخرج كائن JSON واحد صالح فقط، بدون أي نص إضافي قبله أو بعده

لا تُنتِج أبدًا أي شيء خارج JSON صالح واحد.
SYS;
        }
        return trim($basePrompt);
    }

    public function buildUserPrompt(array $payload): string
    {
        $val = fn(?string $s): string => $this->cleanValue($s);

        // جلب البيانات من payload
        $taskTitle = $val($payload['task_title'] ?? '');
        $taskPrompt = $val($payload['task_prompt'] ?? '');
        
        // الحقول الجديدة
        $feedbackModel = $val($payload['feedback_model'] ?? 'feed_up_back_forward');
        $depthLevel = $val($payload['depth_level'] ?? 'medium');
        $tone = $val($payload['tone'] ?? 'professional');
        $length = (int)($payload['length'] ?? 400);
        $customConstraints = $val($payload['custom_constraints'] ?? '');
        
        // محاور التركيز الجديدة (chips) - مخصصة لخطط الدروس
        $focusPoints = is_array($payload['focus_points'] ?? null)
            ? $payload['focus_points']
            : [];

        // التحقق من وجود task_prompt
        if ($taskPrompt === '') {
            return <<<USR
IMPORTANT: Output MUST be JSON only with no extra text outside JSON.
Return exactly ONE valid JSON object with the following key:
- "feedback": رسالة خطأ تشير إلى أن task_prompt غير موجود.

معلومات المهمة (task_prompt) غير موجودة في قاعدة البيانات. يرجى التأكد من وجود المهمة.
USR;
        }

        // تعريف نماذج التغذية الراجعة
        $feedbackModels = [
            'feed_up_back_forward' => [
                'label' => 'Feed Up – Feed Back – Feed Forward',
                'description' => <<<MODEL
نموذج ثلاثي الأبعاد:
- Feed Up (ما قبل): تحديد الأهداف والتوقعات الواضحة (ماذا نريد تحقيقه؟)
- Feed Back (الحاضر): تقييم الأداء الحالي بناءً على المعايير (أين أنت الآن؟)
- Feed Forward (ما بعد): اقتراحات للخطوات القادمة والتحسينات (إلى أين تذهب بعد ذلك؟)
MODEL
            ],
            'sbi' => [
                'label' => 'SBI (Situation – Behavior – Impact)',
                'description' => <<<MODEL
نموذج بناء على ثلاث عناصر:
- Situation (الموقف): وصف السياق والموقف الذي حدث فيه السلوك
- Behavior (السلوك): وصف السلوك أو الإجراء الملاحظ بشكل موضوعي
- Impact (التأثير): شرح التأثير والنتائج المترتبة على هذا السلوك
MODEL
            ],
            'sandwich' => [
                'label' => 'Sandwich Feedback (Praise – Critique – Suggestion)',
                'description' => <<<MODEL
نموذج الساندويتش ثلاثي الطبقات:
- Praise (المديح): بدء بتعزيز الإيجابيات ونقاط القوة
- Critique (النقد البناء): تقديم نقاط التحسين والتحديات بشكل بنّاء
- Suggestion (الاقتراح): إنهاء باقتراحات عملية وواضحة للتحسين
MODEL
            ],
            'crc' => [
                'label' => 'CRC (Comment – Recommendation – Comment)',
                'description' => <<<MODEL
نموذج تعليق-توصية-تعليق:
- Comment (التعليق الأول): ملاحظات ومراجعة عامة للعمل
- Recommendation (التوصية): توصيات محددة وواضحة للتحسين
- Comment (التعليق النهائي): تعليقات ختامية داعمة ومشجعة
MODEL
            ]
        ];

        $selectedModel = $feedbackModels[$feedbackModel] ?? $feedbackModels['feed_up_back_forward'];

        // تعريف محاور التركيز الجديدة لخطط الدروس
        $focusPointsMap = [
            'objectives_activities_link' => 'الترابط بين الأهداف والأنشطة: تحليل مدى ارتباط الأنشطة بالأهداف المحددة',
            'procedures_outcomes_alignment' => 'ملاءمة الإجراءات للنتاجات: تقييم مدى توافق الإجراءات مع النتاجات المتوقعة',
            'roles_integration' => 'تكامل الأدوار (المعلم/الطالب): تحليل توزيع الأدوار وتكاملها',
            'strategies_implementation' => 'سلاسة توظيف الاستراتيجيات: تقييم فعالية واستخدام الاستراتيجيات',
            'assessment_tools_quality' => 'جودة أدوات التقويم: تحليل جودة أدوات التقويم ودرجتها في قياس النتاجات',
            'integration_horizontal_vertical' => 'قوة التكامل الأفقي والرأسي: تقييم التكامل مع المواد الأخرى (أفقي) والمستويات (رأسي)',
            'diploma_standards_compliance' => 'التوافق مع معايير الدبلوم المهني: تقييم مدى توافق الخطة مع معايير الدبلوم المهني للتعليم'
        ];

        $selectedFocusPoints = [];
        foreach ($focusPoints as $point) {
            if (isset($focusPointsMap[$point])) {
                $selectedFocusPoints[] = $focusPointsMap[$point];
            }
        }

        // تعريف درجات التفصيل
        $depthLevels = [
            'brief' => [
                'label' => 'مختصر',
                'description' => 'تحليل موجز يركز على النقاط الرئيسية فقط (حوالي 200-300 كلمة)',
                'wordCount' => '250'
            ],
            'medium' => [
                'label' => 'متوسط',
                'description' => 'تحليل متوازن يغطي النقاط الرئيسية مع بعض التفاصيل (حوالي 400-600 كلمة)',
                'wordCount' => '500'
            ],
            'detailed' => [
                'label' => 'تفصيلي جداً',
                'description' => 'تحليل شامل وعميق يغطي جميع الجوانب مع أمثلة وتفاصيل (حوالي 800-1200 كلمة)',
                'wordCount' => '1000'
            ]
        ];

        $selectedDepth = $depthLevels[$depthLevel] ?? $depthLevels['medium'];
        if ($length === 400) {
            $length = (int)$selectedDepth['wordCount'];
        }

        // تعريف النبرات
        $tones = [
            'formal' => [
                'label' => 'رسمية',
                'description' => 'استخدم لغة رسمية وأكاديمية مع الحفاظ على الاحترام المهني'
            ],
            'warm' => [
                'label' => 'دافئة',
                'description' => 'استخدم لغة دافئة ومشجعة مع الحفاظ على الطابع المهني'
            ],
            'constructive' => [
                'label' => 'بنائية',
                'description' => 'ركز على البناء والتحسين مع تقديم اقتراحات عملية واضحة'
            ],
            'professional' => [
                'label' => 'مهنية',
                'description' => 'استخدم لغة مهنية متوازنة تجمع بين الدقة والوضوح'
            ]
        ];

        $selectedTone = $tones[$tone] ?? $tones['professional'];

        // حساب نطاق الكلمات
        $lengthMin = max(0, $length - 50);
        $lengthMax = $length + 50;

        // === بناء Core Prompt Section ===
        $corePromptSection = <<<CORE

=== الجزء الأول: الدور والتحليل المطلوب (Core Prompt) ===

دورك هو خبير تربوي متخصص في تحليل خطط الدروس للمعلمين ضمن الدبلوم المهني في التعليم، وفق أحدث الأدبيات التربوية (بعد 2015)، ووفق منظومة أكاديمية الملكة رانيا للإعداد التربوي والمهني + الإطار العام للمناهج الأردنية.

أنت تستقبل خطة درس منظمة في صيغة JSON من خلال task_prompt، وتقوم بما يلي:

1. تحليل الخطة بدقة عالية واستخراج:
   - منطق الترابط بين الأهداف والأنشطة والتقويم
   - ملاءمة الإجراءات للنتاجات
   - تكامل الأدوار (المعلم/الطالب)
   - سلاسة توظيف الاستراتيجيات
   - جودة أدوات التقويم ودرجتها في قياس النتاجات
   - قوة التكامل الأفقي والرأسي
   - مدى التوافق مع معايير الدبلوم المهني للتعليم

2. توليد تغذية راجعة مهنية وتربوية وفق النموذج الذي يختاره المستخدم فقط، وبدون أي نموذج آخر.

3. الالتزام التام بإدخالات المستخدم باعتبارها قيودًا إلزامية لا يمكن تجاهلها.

4. استخدام لغة أكاديمية تربوية واضحة، دافئة، ومركزة على النمو.

5. عدم تعديل أو تلخيص JSON؛ فقط تحليله بعمق.

6. كتابة التغذية الراجعة على شكل فقرات مترابطة وسلسة دون استخدام عناوين فرعية أو رموز خاصة أو قوائم نقطية.

7. توجيه التقييم والنقد للخطة نفسها كوثيقة تربوية، وليس للكاتب. استخدم تعبيرات مثل "الخطة تحقق..." أو "التصميم يوضح..." بدلاً من "أنت فعلت..." أو "عملك جيد...".

CORE;

        // === بناء Task Instructions Section ===
        $taskTitleSection = $taskTitle !== '' ? "عنوان المهمة: {$taskTitle}\n" : '';
        $taskInstructionsSection = <<<TASK

=== الجزء الثاني: تفاصيل المهمة (Task Instructions Block) ===

{$taskTitleSection}المهمة:

تحليل خطة درس تعليمية واردة بصيغة JSON (مذكورة لاحقاً) وكتابة تغذية راجعة تفصيلية ومهنية حول:

- جودة تصميم الخطة
- مدى مواءمة الاستراتيجيات للنتاجات
- تدرّج مراحل الدرس
- وضوح أدوار المعلم والطالب
- جودة التقييم التكويني والختامي
- توظيف المصادر والأدوات
- تكامل المهارات والمعارف والقيم
- التوصيات العملية لتحسين الخطة

ويجب أن تُكتب التغذية الراجعة حصراً وفق نموذج التغذية الراجعة الذي اختاره المستخدم من بين:
- Feed Up – Feed Back – Feed Forward
- SBI (Situation – Behavior – Impact)
- Sandwich Feedback
- CRC (Comment – Recommendation – Comment)

ولا يجوز استخدام أي نموذج آخر أو الخلط بينها.

=== خطة الدرس (JSON من task_prompt) ===

{$taskPrompt}

TASK;

        // === بناء User Constraints Section ===
        $focusPointsSection = '';
        if (!empty($selectedFocusPoints)) {
            $focusList = implode("\n- ", $selectedFocusPoints);
            $focusPointsSection = <<<FOCUS

نقاط التركيز الأساسية:
- {$focusList}

FOCUS;
        }

        $customConstraintsSection = '';
        if ($customConstraints !== '') {
            $customConstraintsSection = <<<CUSTOM

قيود خاصة إضافية:
{$customConstraints}

CUSTOM;
        }

        $userConstraintsSection = <<<CONSTRAINTS

=== الجزء الثالث: إدخالات المستخدم كقيود إلزامية (User Constraints Block) ===

يجب الالتزام التام بالقيود التالية التي حدّدها المستخدم:

نموذج التغذية الراجعة المطلوب:

{$selectedModel['label']}

{$selectedModel['description']}

(يحظر استخدام أي نموذج آخر مهما كانت الظروف)

{$focusPointsSection}
درجة العمق المطلوبة:

{$selectedDepth['label']} - {$selectedDepth['description']}

نبرة الكتابة:

{$selectedTone['label']} - {$selectedTone['description']}

الطول التقريبي للإجابة:

حوالي {$length} كلمة (يمكن أن يتراوح بين {$lengthMin} و {$lengthMax} كلمة)

{$customConstraintsSection}
CONSTRAINTS;

        // === بناء تعليمات بناء التغذية الراجعة ===
        return <<<USR
IMPORTANT: Output MUST be JSON only with no extra text outside JSON.
Return exactly ONE valid JSON object with the following key:
- "feedback": التغذية الراجعة الفورية كسلسلة نصية واحدة.

{$corePromptSection}

{$taskInstructionsSection}

{$userConstraintsSection}

=== تعليمات إلزامية لبناء محتوى "feedback" ===

1. **الالتزام بالنموذج المختار ({$selectedModel['label']}):**
   - يجب استخدام هذا النموذج حصراً دون أي انحراف
   - لا يجوز الخلط بين النماذج أو استخدام نموذج آخر
   - يجب تطبيق بنية النموذج بدقة كما هو موضح أعلاه

2. **تحليل JSON خطة الدرس:**
   - حلّل خطة الدرس (JSON) بدقة عالية
   - لا تعدّل أو تلخّص محتوى JSON
   - استخرج العناصر المطلوبة فقط وتحللها
   - ركّز على النقاط السبعة للتحليل المذكورة في Core Prompt

3. **درجة العمق ({$selectedDepth['label']}):**
   - يجب أن يكون التحليل بمستوى العمق المحدد: {$selectedDepth['description']}
   - التزم بالطول المحدد: حوالي {$length} كلمة

4. **النبرة ({$selectedTone['label']}):**
   - استخدم النبرة المحددة: {$selectedTone['description']}
   - ركّز على الخطة نفسها كوثيقة تربوية، وليس على الكاتب
   - استخدم تعبيرات مثل "الخطة تعزز..." أو "التصميم يوضح..." أو "البنية تحقق..."

5. **التقييم والنقد (مهم جداً):**
   - يجب أن يكون التقييم والنقد موجهًا للخطة نفسها ككيان منفصل عن الكاتب
   - لا توجّه المديح أو الذم للكاتب أو لشخصه
   - استخدم تعبيرات موجهة للخطة: "الخطة تحقق..."، "الخطة تعزز..."، "الخطة تحتاج إلى..."، "التصميم يوضح..."، "البنية تتضمن..."
   - تجنّب تماماً: "أنت فعلت..."، "عملك جيد..."، "أنت تحتاج..." - استبدلها بتعبيرات عن الخطة نفسها
   - أنت في موضع تقديم نقد بناء وتغذية راجعة على الخطة كوثيقة تربوية، وليس على كاتبها
   - ركّز على نقاط القوة والضعف في تصميم الخطة وإعدادها ومحتواها

6. **نقاط التركيز:**
   - إذا تم تحديد نقاط تركيز، يجب أن تركز التغذية الراجعة عليها بشكل واضح
   - اربط كل نقطة في التغذية الراجعة بنقطة تركيز محددة

7. **القيود الإضافية:**
   - الالتزام التام بأي قيود إضافية محددة من المستخدم
   - هذه القيود إلزامية ولا يمكن تجاهلها

8. **التنسيق والبنية:**
   - اكتب التغذية الراجعة على شكل فقرات مترابطة وسلسة
   - لا تستخدم عناوين فرعية (###) أو رموز خاصة لتمييز الأقسام
   - لا تستخدم قوائم نقطية أو أرقام أو رموز في التنسيق
   - اجعل النص ينساب بشكل طبيعي من فقرة لأخرى
   - استخدم روابط كلامية (علاوة على ذلك، بالإضافة إلى ذلك، من ناحية أخرى...) للربط بين الأفكار
   - اجعل النص سهل القراءة والفهم كنسق متماسك ومترابط

9. **تجنّب:**
   - لا تعدّل أو تلخّص JSON خطة الدرس
   - لا تستخدم نموذجاً غير المختار
   - لا تخلط بين النماذج
   - لا تستخدم تعبيرات عامة أو غير محددة
   - لا تستخدم عناوين فرعية (###) أو رموز خاصة في التنسيق
   - لا تستخدم قوائم نقطية أو أرقام أو رموز
   - لا توجّه المديح أو الذم للكاتب - فقط للخطة نفسها
   - لا تستخدم ضمير المخاطب ("أنت") - استخدم تعبيرات عن الخطة نفسها
   - لا تخرج عن نطاق JSON - يجب أن يكون المخرج كائن JSON واحد فقط

تذكير أخير:
- أخرج النتيجة على شكل كائن JSON واحد فقط بالمفتاح "feedback" كما هو محدد أعلاه، بلا أي شرح أو تعليقات خارجية.
- أي خروج عن هذه البنية أو إضافة نص خارج JSON يُعتبر مخالفة للتعليمات.
- الالتزام التام بالنموذج المختار ({$selectedModel['label']}) فقط.
- اكتب التغذية الراجعة على شكل فقرات مترابطة وسلسة دون عناوين فرعية أو رموز خاصة أو قوائم نقطية.
- توجيه التقييم والنقد للخطة نفسها فقط، وليس للكاتب. استخدم "الخطة تحقق..." وليس "أنت فعلت...".
USR;
    }
}


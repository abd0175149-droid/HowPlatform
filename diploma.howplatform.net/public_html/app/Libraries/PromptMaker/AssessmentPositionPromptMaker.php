<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

class AssessmentPositionPromptMaker extends BasePromptMaker
{
    /**
     * @param string $basePrompt
     * @param array $payload
     * @return string
     */
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        $systemPrompt = "أنت خبير تربوي متخصص في تخطيط وبناء المواقف التقييمية (Assessment Positions) حسب نموذج GRASPS.\n";
        $systemPrompt .= "مهمتك تصميم موقف تقييمي متكامل وواقعي بأسلوب علمي واضح. يجب أن يكون الموقف مرتبطاً بأهداف التعلم، وموجهاً نحو تحسين الأداء.\n";
        return $systemPrompt;
    }

    /**
     * @param array $payload
     * @return string
     */
    public function buildUserPrompt(array $payload): string
    {
        $teacherName = $payload['teacher_name'] ?? 'معلم/ة متألق/ة';
        $schoolName = $payload['school_name'] ?? '';
        $schoolClass = $payload['school_class'] ?? '';
        $subject = $payload['general_subject'] ?? '';
        $unit = $payload['general_unit'] ?? '';
        $title = $payload['general_title'] ?? '';
        $sources = $payload['learning_sources'] ?? '';
        $instructions = $payload['additional_instructions'] ?? '';

        $strategy = $payload['assessment_strategy'] ?? '';
        $tool = $payload['assessment_tool'] ?? '';

        $userPrompt = "يرجى بناء موقف تقييمي باستخدام المدخلات التالية:\n";
        $userPrompt .= "- اسم المعلم: {$teacherName}\n";
        $userPrompt .= "- المدرسة: {$schoolName}\n";
        $userPrompt .= "- الصف: {$schoolClass}\n";
        $userPrompt .= "- المادة: {$subject}\n";
        $userPrompt .= "- الوحدة: {$unit}\n";
        $userPrompt .= "- الموضوع: {$title}\n";
        
        if (!empty($sources)) {
            $userPrompt .= "- المصادر المتاحة: {$sources}\n";
        }
        if (!empty($strategy)) {
            $userPrompt .= "- استراتيجية التقويم المطلوبة: {$strategy}\n";
        }
        if (!empty($tool)) {
            $userPrompt .= "- أداة التقويم المطلوبة: {$tool}\n";
        }
        if (!empty($instructions)) {
            $userPrompt .= "- توجيهات إضافية: {$instructions}\n";
        }

        $userPrompt .= "\nيجب أن تقوم بإنتاج المخرجات بصيغة JSON Object (Flat Output) حصراً بدون أي نصوص إضافية لتتطابق مع المتطلبات.\n";
        $userPrompt .= "تأكد أن الحقول المطلوبة متواجدة في جذر الـ JSON (Root Level) ولا تقم بإنشاء كائنات متداخلة (No nested objects).\n";
        $userPrompt .= "\nالمفاتيح المطلوبة (JSON Keys MUST match exactly):\n";
        $userPrompt .= "\"teacher_name\", \"school_name\", \"school_class\", \"general_subject\", \"general_unit\", \"general_title\", \"learning_sources\",\n";
        $userPrompt .= "\"cognitive_outcome\", \"skill_outcome\", \"value_outcome\", \"outcomes_time\",\n";
        $userPrompt .= "\"learning_strategies\", \"situation_description\", \"assessment_task\",\n";
        $userPrompt .= "\"grasps_goal\", \"grasps_role\", \"grasps_audience\", \"grasps_situation\", \"grasps_performance\", \"grasps_standards\",\n";
        $userPrompt .= "\"assessment_strategy\", \"assessment_tool\", \"assessment_model\"\n\n";
        
        $userPrompt .= "إرشادات هامة جداً لصياغة المحتوى (يجب أن يكون الأسلوب مشابهاً للمثال التالي):\n";
        $userPrompt .= "1. صُغ نتاجات التعلم بشكل دقيق (مثال: نتاج معرفي 'تحديد الطالب الموقع الفلكي'، نتاج مهاري 'يرسم الخريطة...' نتاج قيمي 'يقدر...').\n";
        $userPrompt .= "2. وصف الموقف يجب أن يكون سردياً يصف إجراءات المعلم والطلبة (مثال: 'قامت المعلمة بشرح كذا.. ثم على الطالب أن يصوغ كذا...').\n";
        $userPrompt .= "3. في مكونات GRASPS:\n";
        $userPrompt .= "   - الدور (Role): 'محدّد للموقع الفلكي' أو 'طبيب استشاري' إلخ.\n";
        $userPrompt .= "   - الجمهور (Audience): 'الطلبة/ الجغرافيون' إلخ.\n";
        $userPrompt .= "   - الموقف (Situation): 'هو السياق المحدد لكذا وكذا، والتحدي الذي قد يواجهه...'.\n";
        $userPrompt .= "   - الأداء (Performance): 'بطاقات لاصقة باسم الإقليم'، أو 'إعداد تقرير طبي' إلخ.\n";
        $userPrompt .= "   - المعايير (Standards): عبارات قصيرة مثل 'تحديد خطوط الطول/ صياغة حدود الموقع/ توزيع الأقاليم'.\n";
        $userPrompt .= "4. بناءً على طبيعة الموقف، اختر 'استراتيجية التقويم' الأنسب و'أداة التقويم' المتوافقة معها حصراً من الخيارات التالية (إلا إذا تم إجبارك عليها في المدخلات السابقة):\n";
        $userPrompt .= "   - استراتيجية الملاحظة: (أدواتها: سجل سير التعلم، السجل الوصفي، قائمة الشطب، سلم التقدير).\n";
        $userPrompt .= "   - استراتيجية التواصل: (أدواتها: أسئلة وإجابات، مقابلة).\n";
        $userPrompt .= "   - التقويم المعتمد على الأداء: (أدواتها: قائمة الشطب، سلم التقدير، سلم التقدير اللفظي، السجل الوصفي).\n";
        $userPrompt .= "   - المراجعة للذات: (أدواتها: يوميات الطالب، ملف الطالب، مقياس التقدير).\n";
        $userPrompt .= "   - الورقة والقلم: (أدواتها: اختبار قصير، اختبار قياسي).\n";
        $userPrompt .= "   ملاحظة صارمة: إذا احتوت المدخلات على 'استراتيجية التقويم المطلوبة' أو 'أداة التقويم المطلوبة'، يجب الالتزام بها تماماً وعدم اختيار بدائل.\n";
        $userPrompt .= "5. قيمة الحقل `assessment_model` (نموذج أداة التقويم) يجب أن تحتوي على النموذج الفعلي المصمم للأداة التي اخترتها بشكل نصي واضح جداً وجاهز للطباعة.\n";
        $userPrompt .= "   - إذا اخترت (قائمة شطب)، صمم جدولاً يحتوي على قائمة بالمعايير أو المؤشرات وأمام كل معيار خيار (نعم / لا) أو (أتقن / لم يتقن).\n";
        $userPrompt .= "   - إذا اخترت (سلم تقدير)، صمم جدولاً يوضح مستويات الأداء (مثلاً: ممتاز، جيد جداً، جيد، بحاجة لتحسين) مع وصف موجز لكل مستوى أمام كل معيار.\n";
        $userPrompt .= "   - اجعل النموذج شاملاً بحيث يمكن للمعلم استخدامه فوراً لتقييم الطلبة.\n";

        return $userPrompt;
    }
}

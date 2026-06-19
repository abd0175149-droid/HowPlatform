<?php
namespace App\Controllers\Test;
use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;

class ToolSetup extends BaseController
{
    use ResponseTrait;

    /**
     * GET /test/tool-setup
     * يُنشئ أو يُحدّث سجل bilingual_lesson_plan_v1 في ai_job_types
     */
    public function run()
    {
        $db      = \Config\Database::connect();
        $typeKey = 'bilingual_lesson_plan_v1';

        // ─── ui_config_json ───────────────────────────────────────────────────
        // ملاحظة مهمة: ai_tool_factory.js يقرأ field.key (وليس field.name)
        // لذا جميع الحقول يجب أن تستخدم "key" وليس "name"
        $uiConfig = [
            "panel" => [
                "icon"   => "fa-solid fa-language",
                "color"  => "blue",
                "button" => ["label" => "إنشاء خطة الدرس", "icon" => "fa-solid fa-wand-magic-sparkles"],
            ],
            "form" => [
                "submit_label" => "توليد الخطة بالذكاء الاصطناعي",
                "sections" => [
                    [
                        "title"  => "معلومات أساسية",
                        "layout" => "grid-2",
                        "fields" => [
                            [
                                "key"      => "language",
                                "type"     => "select",
                                "label"    => "لغة الخطة",
                                "required" => true,
                                "options"  => [
                                    ["value" => "ar", "label" => "العربية (Arabic)"],
                                    ["value" => "en", "label" => "الإنجليزية (English)"],
                                ],
                            ],
                            [
                                "key"         => "subject",
                                "type"        => "text",
                                "label"       => "المبحث / المادة الدراسية",
                                "placeholder" => "مثال: الرياضيات، العلوم، اللغة العربية",
                                "required"    => true,
                            ],
                            [
                                "key"      => "teacher_gender",
                                "type"     => "select",
                                "label"    => "جنس المعلم",
                                "required" => true,
                                "options"  => [
                                    ["value" => "ذكر",  "label" => "ذكر (Male)"],
                                    ["value" => "أنثى", "label" => "أنثى (Female)"],
                                ],
                            ],
                            [
                                "key"      => "student_gender",
                                "type"     => "select",
                                "label"    => "جنس الطلبة",
                                "required" => true,
                                "options"  => [
                                    ["value" => "ذكور",   "label" => "ذكور (Male Students)"],
                                    ["value" => "إناث",   "label" => "إناث (Female Students)"],
                                    ["value" => "مختلط", "label" => "مختلط (Mixed)"],
                                ],
                            ],
                            [
                                "key"         => "grade_level",
                                "type"        => "text",
                                "label"       => "الصف الدراسي",
                                "placeholder" => "مثال: الصف العاشر / Grade 10",
                                "required"    => true,
                            ],
                            [
                                "key"         => "classes_count",
                                "type"        => "number",
                                "label"       => "عدد الحصص",
                                "placeholder" => "مثال: 2",
                                "required"    => true,
                                "min"         => 1,
                                "max"         => 20,
                            ],
                            [
                                "key"         => "duration_minutes",
                                "type"        => "number",
                                "label"       => "زمن الحصة (بالدقائق)",
                                "placeholder" => "مثال: 45",
                                "required"    => true,
                                "min"         => 10,
                                "max"         => 120,
                            ],
                        ],
                    ],
                    [
                        "title"  => "محتوى الدرس",
                        "layout" => "grid-2",
                        "fields" => [
                            [
                                "key"         => "unit_title",
                                "type"        => "text",
                                "label"       => "عنوان الوحدة",
                                "placeholder" => "مثال: الأعداد الصحيحة",
                                "required"    => true,
                            ],
                            [
                                "key"         => "lesson_title",
                                "type"        => "text",
                                "label"       => "عنوان الدرس",
                                "placeholder" => "مثال: ضرب الأعداد السالبة",
                                "required"    => true,
                            ],
                            [
                                "key"         => "previous_learning",
                                "type"        => "textarea",
                                "label"       => "التعلم القبلي (اختياري)",
                                "placeholder" => "ما يعرفه الطلبة مسبقاً من مفاهيم ومعلومات...",
                                "required"    => false,
                                "full"        => true,
                            ],
                            [
                                "key"         => "main_outcomes",
                                "type"        => "textarea",
                                "label"       => "النتاجات الرئيسية للدرس",
                                "placeholder" => "اذكر النتاجات التعليمية المراد تحقيقها...",
                                "required"    => true,
                                "full"        => true,
                            ],
                        ],
                    ],
                    [
                        "title"  => "الاستراتيجيات",
                        "layout" => "chips",
                        "fields" => [
                            [
                                "key"     => "teaching_strategies",
                                "type"    => "chips",
                                "label"   => "استراتيجيات التدريس (حتى 3)",
                                "max"     => 3,
                                "required"=> false,
                                "options" => [
                                    ["value" => "المناقشة والحوار",          "label" => "المناقشة والحوار"],
                                    ["value" => "التعلم التعاوني",            "label" => "التعلم التعاوني"],
                                    ["value" => "الاستقصاء",                 "label" => "الاستقصاء"],
                                    ["value" => "حل المشكلات",               "label" => "حل المشكلات"],
                                    ["value" => "العصف الذهني",              "label" => "العصف الذهني"],
                                    ["value" => "التعلم بالمشروع",           "label" => "التعلم بالمشروع"],
                                    ["value" => "لعب الأدوار",               "label" => "لعب الأدوار"],
                                    ["value" => "التعلم الذاتي",             "label" => "التعلم الذاتي"],
                                    ["value" => "التدريس المباشر",           "label" => "التدريس المباشر"],
                                    ["value" => "الخرائط المعرفية",          "label" => "الخرائط المعرفية"],
                                    ["value" => "التعلم القائم على الأدلة", "label" => "التعلم القائم على الأدلة"],
                                    ["value" => "القصة والسرد",              "label" => "القصة والسرد"],
                                ],
                                "full"    => true,
                            ],
                            [
                                "key"     => "evaluation_strategies",
                                "type"    => "chips",
                                "label"   => "استراتيجيات التقويم (حتى 3)",
                                "max"     => 3,
                                "required"=> false,
                                "options" => [
                                    ["value" => "الملاحظة",              "label" => "الملاحظة"],
                                    ["value" => "الأسئلة الشفهية",       "label" => "الأسئلة الشفهية"],
                                    ["value" => "الاختبار القصير",       "label" => "الاختبار القصير"],
                                    ["value" => "بطاقة الخروج",          "label" => "بطاقة الخروج"],
                                    ["value" => "ورقة العمل",            "label" => "ورقة العمل"],
                                    ["value" => "التقييم الذاتي",        "label" => "التقييم الذاتي"],
                                    ["value" => "تقييم الأقران",         "label" => "تقييم الأقران"],
                                    ["value" => "المشروع",               "label" => "المشروع"],
                                    ["value" => "العرض التقديمي",        "label" => "العرض التقديمي"],
                                    ["value" => "المهمة الأدائية",       "label" => "المهمة الأدائية"],
                                    ["value" => "ملف الإنجاز",           "label" => "ملف الإنجاز"],
                                    ["value" => "السجل القصصي",          "label" => "السجل القصصي"],
                                ],
                                "full"    => true,
                            ],
                        ],
                    ],
                    [
                        "title"  => "ملاحظات إضافية",
                        "layout" => "grid-1",
                        "fields" => [
                            [
                                "key"         => "general_description",
                                "type"        => "textarea",
                                "label"       => "وصف عام للدرس (اختياري)",
                                "placeholder" => "أي ملاحظات أو سياق إضافي للدرس...",
                                "required"    => false,
                                "full"        => true,
                            ],
                        ],
                    ],
                ],
            ],
            "result" => [
                "type"    => "download_only",
                "message" => "تم إنشاء خطة الدرس! يمكنك تحميل الملف الآن.",
            ],
        ];

        // ─── System Prompt ────────────────────────────────────────────────────
        $sp  = "أنت مساعد متخصص في التخطيط التربوي.\n";
        $sp .= "عند تلقي طلب بناء خطة درس، أنتج إجابتك بصيغة JSON حصراً بدون أي نص خارجها.\n";
        $sp .= "إذا كانت اللغة (language) = 'en' أنتج جميع النصوص بالإنجليزية.\n";
        $sp .= "إذا كانت اللغة (language) = 'ar' أنتج جميع النصوص بالعربية.\n\n";
        $sp .= "هيكل JSON المطلوب:\n";
        $sp .= "{\n";
        $sp .= "  \"subject\": \"...\",\n";
        $sp .= "  \"teacher_gender\": \"...\",\n";
        $sp .= "  \"student_gender\": \"...\",\n";
        $sp .= "  \"grade_level\": \"...\",\n";
        $sp .= "  \"unit_title\": \"...\",\n";
        $sp .= "  \"lesson_title\": \"...\",\n";
        $sp .= "  \"classes_count\": \"...\",\n";
        $sp .= "  \"previous_learning\": \"...\",\n";
        $sp .= "  \"main_outcomes\": \"...\",\n";
        $sp .= "  \"teaching_strategies\": [\"...\", \"...\"],\n";
        $sp .= "  \"evaluation_strategies\": [\"...\", \"...\"],\n";
        $sp .= "  \"general_description\": \"...\",\n";
        $sp .= "  \"outcome_rows\": [\n";
        $sp .= "    { \"time\": \"...\", \"learner_action\": \"...\", \"teacher_action\": \"...\" },\n";
        $sp .= "    { \"time\": \"...\", \"learner_action\": \"...\", \"teacher_action\": \"...\" },\n";
        $sp .= "    { \"time\": \"...\", \"learner_action\": \"...\", \"teacher_action\": \"...\" },\n";
        $sp .= "    { \"time\": \"...\", \"learner_action\": \"...\", \"teacher_action\": \"...\" }\n";
        $sp .= "  ]\n";
        $sp .= "}\n";
        $sp .= "outcome_rows يجب أن يحتوي بالضبط 4 عناصر تمثل مراحل:\n";
        $sp .= "1-Engagement  2-Explanation  3-Elaboration  4-Closing\n";

        // ─── Data row ─────────────────────────────────────────────────────────
        $data = [
            'type_key'            => $typeKey,
            'title'               => 'خطة درس (ثنائية اللغة)',
            'description'         => 'أداة لإنشاء خطة درس بالعربية أو الإنجليزية مع قالب Word مخصص لكل لغة.',
            'is_enabled'          => 1,
            'provider'            => 'gemini',
            'model_name'          => 'gemini-2.5-pro',
            'prompt_maker_class'  => 'App\\Libraries\\PromptMaker\\BilingualLessonPlanPromptMaker',
            'system_prompt'       => $sp,
            'template_path'       => 'templates/bilingual_plan_ar.docx',
            'ui_config_json'      => json_encode($uiConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'points_cost'         => 30,
            'updated_at'          => date('Y-m-d H:i:s'),
        ];

        $existing = $db->table('ai_job_types')->where('type_key', $typeKey)->get()->getRowArray();

        if ($existing) {
            $db->table('ai_job_types')->where('type_key', $typeKey)->update($data);
            $msg = "✅ تم تحديث الأداة بنجاح (id={$existing['id']})";
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $db->table('ai_job_types')->insert($data);
            $msg = "✅ تم إنشاء الأداة بنجاح (id=" . $db->insertID() . ")";
        }

        // إعادة السجل المحدّث للتحقق
        $row = $db->table('ai_job_types')->where('type_key', $typeKey)->get()->getRowArray();

        return $this->respond([
            'ok'      => true,
            'message' => $msg,
            'record'  => $row,
        ]);
    }
}

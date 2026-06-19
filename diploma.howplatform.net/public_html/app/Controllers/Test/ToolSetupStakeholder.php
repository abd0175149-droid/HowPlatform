<?php
namespace App\Controllers\Test;
use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;

class ToolSetupStakeholder extends BaseController
{
    use ResponseTrait;

    public function run()
    {
        $db      = \Config\Database::connect();
        $typeKey = 'stakeholder_integration_v1';

        $uiConfig = [
            "panel" => [
                "icon"   => "fa-solid fa-users",
                "color"  => "green",
                "button" => ["label" => "دمج أصحاب العلاقة", "icon" => "fa-solid fa-wand-magic-sparkles"],
            ],
            "form" => [
                "submit_label" => "توليد الخطة بالذكاء الاصطناعي",
                "sections" => [
                    [
                        "title"  => "معلومات النشاط",
                        "layout" => "grid-2",
                        "fields" => [
                            [
                                "key"         => "grade_level",
                                "type"        => "text",
                                "label"       => "الصف الدراسي",
                                "placeholder" => "مثال: العاشر الأساسي",
                                "required"    => true,
                            ],
                            [
                                "key"         => "subject_area",
                                "type"        => "text",
                                "label"       => "المبحث الدراسي",
                                "placeholder" => "مثال: العلوم الحياتية",
                                "required"    => true,
                            ],
                            [
                                "key"         => "learning_outcome",
                                "type"        => "textarea",
                                "label"       => "نتاج التعلم",
                                "placeholder" => "اكتب نتاج التعلم المستهدف...",
                                "required"    => true,
                                "full"        => true,
                            ],
                            [
                                "key"      => "stakeholder_role",
                                "type"     => "select",
                                "label"    => "صفة دور صاحب العلاقة المدعو",
                                "required" => true,
                                "options"  => [
                                    ["value" => "ولي أمر",  "label" => "ولي أمر"],
                                    ["value" => "معلم زميل", "label" => "معلم زميل"],
                                    ["value" => "زائر خارجي", "label" => "زائر خارجي"],
                                ],
                                "full"     => true,
                            ],
                        ],
                    ],
                ],
            ],
            "result" => [
                "type"    => "download_only",
                "message" => "تم إنشاء نموذج دمج أصحاب العلاقة! يمكنك تحميل الملف الآن.",
            ],
        ];

        $sp = "راجع الفئة الخاصة ببناء التوجيه (PromptMaker).";

        $data = [
            'type_key'            => $typeKey,
            'title'               => 'دمج أصحاب العلاقة',
            'description'         => 'أداة لإنشاء خطة إجرائية مفصلة لدمج أصحاب العلاقة (أولياء الأمور، زملاء، زوار خارجيين) في العملية التعليمية.',
            'is_enabled'          => 1,
            'provider'            => 'gemini',
            'model_name'          => 'gemini-2.5-pro',
            'prompt_maker_class'  => 'App\\Libraries\\PromptMaker\\StakeholderIntegrationPromptMaker',
            'system_prompt'       => $sp,
            'template_path'       => 'templates/stakeholder_integration_template.docx',
            'ui_config_json'      => json_encode($uiConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'points_cost'         => 20,
            'updated_at'          => date('Y-m-d H:i:s'),
        ];

        $existing = $db->table('ai_job_types')->where('type_key', $typeKey)->get()->getRowArray();

        if ($existing) {
            $db->table('ai_job_types')->where('type_key', $typeKey)->update($data);
            $jobTypeId = $existing['id'];
            $msg = "✅ تم تحديث الأداة بنجاح (id={$jobTypeId})";
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $db->table('ai_job_types')->insert($data);
            $jobTypeId = $db->insertID();
            $msg = "✅ تم إنشاء الأداة بنجاح (id={$jobTypeId})";
        }

        $placementData = [
            'type_key'     => $typeKey,
            'task_type_id' => 48,
        ];
        
        $placementExist = $db->table('ai_tool_placements')->where($placementData)->get()->getRowArray();
        if (!$placementExist) {
            $placementData['is_enabled'] = 1;
            $db->table('ai_tool_placements')->insert($placementData);
            $msg .= " - تم الربط مع المهمة 48";
        }

        return $this->respond([
            'ok'      => true,
            'message' => $msg,
        ]);
    }
}

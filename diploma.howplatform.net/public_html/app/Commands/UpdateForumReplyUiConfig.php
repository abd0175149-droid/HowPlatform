<?php
declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * تحديث ui_config_json لأداة forum_reply_v1
 */
class UpdateForumReplyUiConfig extends BaseCommand
{
    protected $group       = 'ai-tools';
    protected $name        = 'ai-tools:update-forum-reply';
    protected $description = 'تحديث ui_config_json لأداة forum_reply_v1';

    public function run(array $params)
    {
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
                        'title' => 'بيانات المناقشة',
                        'layout' => 'grid-1',
                        'fields' => [
                            ['key' => 'topic', 'type' => 'text', 'label' => 'عنوان المناقشة', 'required' => true],
                            ['key' => 'context', 'type' => 'textarea', 'label' => 'نص السؤال أو السياق', 'required' => true, 'minlength' => 40],
                            ['key' => 'requirements', 'type' => 'textarea', 'label' => 'تعليمات خاصة', 'placeholder' => 'مثال: استخدم لغة رسمية واذكر مصدرين حديثين'],
                        ],
                    ],
                    [
                        'title' => 'تفضيلات الرد',
                        'layout' => 'grid-2',
                        'fields' => [
                            ['key' => 'tone', 'type' => 'select', 'label' => 'نبرة الرد', 'default' => 'formal', 'options' => [
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
                                'label' => 'محاور يجب تضمينها (حتى 3)',
                                'max' => 3,
                                'options' => [
                                    'مراجع حديثة','سؤال مفتوح','اقتراح نشاط','تغذية راجعة بنّاءة','ربط بواقع الصف','تنبيه أكاديمي'
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

        $db = Database::connect();
        $builder = $db->table('ai_job_types');
        
        $existing = $builder->where('type_key', 'forum_reply_v1')->get()->getRowArray();
        
        if (!$existing) {
            CLI::error('أداة forum_reply_v1 غير موجودة في قاعدة البيانات!');
            CLI::write('يرجى تشغيل: php spark db:seed AiJobTypesSeeder');
            return;
        }

        $updated = $builder->where('type_key', 'forum_reply_v1')->update([
            'ui_config_json' => json_encode($uiConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        if ($updated) {
            // إعادة تحميل الـ registry
            \App\Services\AiTools\AiToolRegistry::reload();
            
            CLI::write('✓ تم تحديث ui_config_json لأداة forum_reply_v1 بنجاح!', 'green');
            CLI::write('يرجى إعادة تحميل الصفحة في المتصفح.');
        } else {
            CLI::error('فشل تحديث البيانات.');
        }
    }
}


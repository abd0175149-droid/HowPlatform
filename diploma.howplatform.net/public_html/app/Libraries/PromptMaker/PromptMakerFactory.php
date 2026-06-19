<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

use Config\Database;

/**
 * PromptMakerFactory: إنشاء PromptMaker المناسب حسب type_key
 *
 * ترتيب الأولوية:
 * 1. الكلاسات المُسجَّلة يدوياً في $makers (للتوافق مع الإصدارات الموجودة)
 * 2. قراءة prompt_maker_class من قاعدة البيانات (ai_job_types)
 * 3. GenericPromptMaker كـ fallback عام لأي أداة جديدة
 */
class PromptMakerFactory
{
    /**
     * تسجيل الكلاسات الموجودة — للتوافق العكسي فقط.
     * الأدوات الجديدة لا تحتاج هنا، يكفي prompt_maker_class في DB.
     */
    private static array $makers = [
        'lesson_plan_v1'        => LessonPlanPromptMaker::class,
        'forum_reply_v1'        => ForumReplyPromptMaker::class,
        'forum_reply_v2'        => ForumReplyPromptMaker::class,
        'reflection_v1'         => ReflectionPromptMaker::class,
        'weekly_reflection_v1'  => WeeklyReflectionPromptMaker::class,
        'academic_answer_v1'    => AcademicAnswerPromptMaker::class,
        'academic_answer_v2'    => AcademicAnswerV2PromptMaker::class,
        'task_matrix_v1'        => TaskMatrixPromptMaker::class,
        'matrix_analysis_v1'    => MatrixAnalysisPromptMaker::class,
        'smart_ingest_v1'       => SmartIngestionPromptMaker::class,
        'smart_analyzer_v1'     => SmartAnalyzerPromptMaker::class,
        'instant_feedback_v1'   => InstantFeedbackPromptMaker::class,
        'teacher_feedback_v1'   => TeacherFeedbackPromptMaker::class,
        'analytical_article_v1' => AnalyticalArticlePromptMaker::class,
        'case_study_v1'         => CaseStudyPromptMaker::class,
        'case_integration_v1'   => CaseIntegrationPromptMaker::class,
        'article_evaluator_v1'  => ArticleEvaluatorPromptMaker::class,
        'evaluation_integration_v1' => EvaluationIntegrationPromptMaker::class,
        'assessment_position_v1'=> AssessmentPositionPromptMaker::class,
        'test_design_v1'        => TestDesignPromptMaker::class,
        'question_develop_v1'   => QuestionDevelopPromptMaker::class,
        'inquiry_questions_v1'  => InquiryQuestionsPromptMaker::class,
        'inquiry_research_v1'        => InquiryResearchPromptMaker::class,
        'inquiry_plan_evaluator_v1'  => InquiryPlanEvaluatorPromptMaker::class,
        'inquiry_report_evaluator_v1' => InquiryReportEvaluatorPromptMaker::class,
    ];

    /**
     * إنشاء PromptMaker حسب type_key
     *
     * التسلسل:
     * 1. ابحث في $makers (الكلاسات المُرمَّزة للتوافق العكسي)
     * 2. اقرأ prompt_maker_class من DB
     * 3. استخدم GenericPromptMaker كـ fallback
     */
    public static function create(string $typeKey): BasePromptMaker
    {
        // 1. البحث في الكلاسات المُسجَّلة (أولوية قصوى للتوافق العكسي)
        $class = self::$makers[$typeKey] ?? null;

        // 2. قراءة كلاس الـ PromptMaker من DB إن لم يُسجَّل محلياً
        if (!$class) {
            $class = self::resolveClassFromDb($typeKey);
        }

        // 3. التحقق من وجود الكلاس وصحة الوراثة
        if ($class && class_exists($class) && is_subclass_of($class, BasePromptMaker::class)) {
            return new $class();
        }

        // 4. Fallback: GenericPromptMaker يعمل مع أي أداة
        log_message('debug', "PromptMakerFactory: using GenericPromptMaker for type_key={$typeKey}");
        return new GenericPromptMaker();
    }

    /**
     * قراءة prompt_maker_class من قاعدة البيانات
     */
    private static function resolveClassFromDb(string $typeKey): ?string
    {
        try {
            $db  = Database::connect();
            $row = $db->table('ai_job_types')
                ->select('prompt_maker_class')
                ->where('type_key', $typeKey)
                ->where('is_enabled', 1)
                ->get()
                ->getRowArray();

            return !empty($row['prompt_maker_class']) ? $row['prompt_maker_class'] : null;
        } catch (\Throwable $e) {
            log_message('error', 'PromptMakerFactory::resolveClassFromDb error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * تسجيل PromptMaker جديد (للاستخدام برمجياً — الطريقة المفضّلة الآن هي DB)
     */
    public static function register(string $typeKey, string $makerClass): void
    {
        if (!is_subclass_of($makerClass, BasePromptMaker::class)) {
            throw new \RuntimeException("{$makerClass} must extend BasePromptMaker");
        }
        self::$makers[$typeKey] = $makerClass;
    }

    /**
     * الحصول على قائمة type_keys المسجلة (محلياً فقط)
     */
    public static function getRegisteredTypes(): array
    {
        return array_keys(self::$makers);
    }
}

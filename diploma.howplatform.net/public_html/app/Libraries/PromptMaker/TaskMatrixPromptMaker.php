<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

/**
 * TaskMatrixPromptMaker: يبني توجيهات أداة مصفوفة المحتوى والمهام
 */
class TaskMatrixPromptMaker extends BasePromptMaker
{
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        if (trim($basePrompt) === '') {
            return implode("\n", [
                'تصرف كمصمم مناهج خبير في تحليل المحتوى للمنهاج الأردني بعد 2022.',
                'الهدف: إعداد تحليل محتوى واحد للوحدة يملأ أعمدة الجدول التالية: المحتوى، المفاهيم والمصطلحات، التعميمات/المبادئ/القوانين، الأنشطة والمهارات، القيم والاتجاهات، الرسومات والجداول والأشكال، بنود أخرى (مهام الطلبة).',
                'لا تقسم التحليل إلى حصص؛ جميع الأعمدة تصف الوحدة كاملة.',
                'استخدم لغة فصحى مهنية، واذكر أمثلة واقعية ومرجع الصفحات.',
                'إذا كانت أي من الحقول الأساسية ناقصة (stage_level, teacher_name, unit_title, subject_name, lessons_count, curriculum_pages) فأعد JSON بالهيئة {"need_more_info":[...]} فقط.',
                'غير ذلك، يجب إرجاع JSON واحد صالح مطابق للبنية المحددة.',
            ]);
        }

        return trim($basePrompt);
    }

    public function buildUserPrompt(array $payload): string
    {
        $val = fn(?string $s): string => $this->cleanValue($s);

        $stageLevel    = $val($payload['stage_level'] ?? $payload['grade_level'] ?? '');
        $teacherName   = $val($payload['teacher_name'] ?? '');
        $unitTitle     = $val($payload['unit_title'] ?? $payload['lesson_title'] ?? '');
        $subjectName   = $val($payload['subject_name'] ?? '');
        $lessonsCount  = max(1, min(12, (int) ($payload['lessons_count'] ?? 1)));
        $currPages     = $val($payload['curriculum_pages'] ?? '');
        $subjectBrief  = $val($payload['subject_brief'] ?? $payload['subject_overview'] ?? '');
        $timeFrame     = $val($payload['time_frame'] ?? '');
        $resourcesHint = $val($payload['resources_hint'] ?? '');
        $learningFocus = $val($payload['learning_focus'] ?? '');
        $studentProfile = $val($payload['student_profile'] ?? '');
        $customReq     = $val($payload['custom_requirements'] ?? '');

        $required = [
            'teacher_name' => $teacherName,
            'stage_level' => $stageLevel,
            'subject_name' => $subjectName,
            'unit_title' => $unitTitle,
            'curriculum_pages' => $currPages,
        ];

        $missing = array_keys(array_filter($required, fn($v) => $v === ''));
        if (empty($missing) && $lessonsCount <= 0) {
            $missing[] = 'lessons_count';
        }

        if (!empty($missing)) {
            return json_encode([
                'need_more_info' => $missing,
                'message' => 'يرجى تزويدي بالحقول الناقصة قبل بناء المصفوفة.',
            ], JSON_UNESCAPED_UNICODE);
        }

        $context = implode("\n", [
            "اسم المعلم / الطالب: {$teacherName}",
            "المرحلة التي يُدرّسها: {$stageLevel}",
            "المبحث: {$subjectName}",
            "عنوان الوحدة / الدرس: {$unitTitle}",
            "عدد الحصص المطلوبة: {$lessonsCount}",
            "الصفحات أو المصادر (تحليل المحتوى): {$currPages}",
            "وصف مختصر للمادة أو المحتوى العلمي: {$subjectBrief}",
            "الإطار الزمني التقريبي: {$timeFrame}",
            "موارد أو مراجع لازمة: {$resourcesHint}",
            "تركيز التعلم المطلوب: {$learningFocus}",
            "خصائص / احتياجات الطلبة: {$studentProfile}",
            "تعليمات إضافية من المستخدم: {$customReq}",
        ]);

        return implode("\n", [
            'IMPORTANT: Output MUST be JSON only with no extra text outside JSON. ابدأ مباشرة بـ { وانتهِ بـ }.',
            '',
            'المعطيات الكاملة:',
            $context,
            '',
            'المطلوب:',
            '1. ابنِ تحليلاً واحداً شاملاً للوحدة، ولا تُقسّمه إلى حصص منفصلة.',
            '2. املأ الأعمدة السبعة التالية بنص واضح أو قائمة نقطية:',
            '   - matrix_col_content: المحتوى المعرفي مع الصفحات المرجعية.',
            '   - matrix_col_concepts: المفردات والمفاهيم والمصطلحات الأساسية.',
            '   - matrix_col_principles: التعميمات/المبادئ/القوانين الرئيسة.',
            '   - matrix_col_activities: الأنشطة والمهارات (صفية، منزلية، رقمية...).',
            '   - matrix_col_values: القيم والاتجاهات والسلوكيات المستهدفة.',
            '   - matrix_col_visuals: الرسومات والجداول والأشكال أو الروابط الداعمة.',
            '   - matrix_col_other_tasks: بنود أخرى أو مهام للطلبة (تعلم تعاوني، واجبات...).',
            '3. اربط المحتوى بواقع الطلبة والصفحات المذكورة، واستخدم لغة أكاديمية مختصرة.',
            '',
            'بنية JSON المطلوبة:',
            '{',
            '  "stage_level": "' . $stageLevel . '",',
            '  "teacher_name": "' . $teacherName . '",',
            '  "unit_title": "' . $unitTitle . '",',
            '  "subject_name": "' . $subjectName . '",',
            '  "lessons_count": ' . $lessonsCount . ',',
            '  "curriculum_pages": "' . $currPages . '",',
            '  "subject_brief": "' . $subjectBrief . '",',
            '  "summary": "ملخص موجز لأهداف الوحدة.",',
            '  "matrix_col_content": ["فقرة/جملة ...", "..."],',
            '  "matrix_col_concepts": ["مصطلح 1", "مصطلح 2"],',
            '  "matrix_col_principles": ["مبدأ 1", "مبدأ 2"],',
            '  "matrix_col_activities": ["نشاط 1", "نشاط 2"],',
            '  "matrix_col_values": ["قيمة 1", "اتجاه 2"],',
            '  "matrix_col_visuals": ["رسم/جدول/رابط 1", "رسم 2"],',
            '  "matrix_col_other_tasks": ["مهمة 1", "مهمة 2"],',
            '  "additional_notes": "تعليمات ختامية أو توصيات للمعلم."',
            '}',
            '',
            'شروط صارمة:',
            '- الحقول matrix_col_* يمكن أن تكون نصاً أو قائمة؛ إن استخدمت قائمة فأبقها في صيغة JSON Array.',
            '- لا تترك أي عمود فارغاً؛ استخدم علامة "—" فقط عند الضرورة القصوى.',
            '- لا تستخدم أي نص خارج JSON.',
        ]);
    }
}


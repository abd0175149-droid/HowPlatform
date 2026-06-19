<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

/**
 * BilingualLessonPlanPromptMaker
 * يبني توجيهات أداة التخطيط ثنائية اللغة
 */
class BilingualLessonPlanPromptMaker extends BasePromptMaker
{
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        return $basePrompt;
    }

    public function buildUserPrompt(array $payload): string
    {
        // نحول جميع القيم إلى string قبل cleanValue لتفادي خطأ "int given"
        $str = fn($v) => (string)($v ?? '');

        $language           = $this->cleanValue($str($payload['language']          ?? $payload['Language']          ?? 'ar'));
        $subject            = $this->cleanValue($str($payload['subject']           ?? $payload['Subject']           ?? ''));
        $teacherGender      = $this->cleanValue($str($payload['teacher_gender']    ?? $payload['Teacher Gender']    ?? ''));
        $studentGender      = $this->cleanValue($str($payload['student_gender']    ?? $payload['Students Gender']   ?? ''));
        $gradeLevel         = $this->cleanValue($str($payload['grade_level']       ?? $payload['Grade Level']       ?? ''));
        $unitTitle          = $this->cleanValue($str($payload['unit_title']        ?? $payload['Unit Title']        ?? ''));
        $lessonTitle        = $this->cleanValue($str($payload['lesson_title']      ?? $payload['Lesson Title']      ?? ''));
        $classesCount       = $this->cleanValue($str($payload['classes_count']     ?? $payload['Number of Classes'] ?? ''));
        $durationMinutes    = $this->cleanValue($str($payload['duration_minutes']  ?? $payload['Duration']          ?? ''));
        $previousLearning   = $this->cleanValue($str($payload['previous_learning'] ?? $payload['Previous Learning'] ?? ''));
        $mainOutcomes       = $this->cleanValue($str($payload['main_outcomes']     ?? $payload['Main Outcomes']     ?? ''));
        $generalDescription = $this->cleanValue($str($payload['general_description'] ?? $payload['General Description'] ?? ''));

        $teachingStrategies   = $this->arrayToText($payload['teaching_strategies']   ?? []);
        $evaluationStrategies = $this->arrayToText($payload['evaluation_strategies'] ?? []);

        $lines = [];

        if ($language === 'en') {
            $lines[] = "Please generate a complete lesson plan IN ENGLISH based on the following inputs:";
            if ($subject)            $lines[] = "- Subject: {$subject}";
            if ($teacherGender)      $lines[] = "- Teacher Gender: {$teacherGender}";
            if ($studentGender)      $lines[] = "- Students Gender: {$studentGender}";
            if ($gradeLevel)         $lines[] = "- Grade Level: {$gradeLevel}";
            if ($unitTitle)          $lines[] = "- Unit Title: {$unitTitle}";
            if ($lessonTitle)        $lines[] = "- Lesson Title: {$lessonTitle}";
            if ($classesCount)       $lines[] = "- Number of Classes: {$classesCount}";
            if ($durationMinutes)    $lines[] = "- Duration per Class (minutes): {$durationMinutes}";
            if ($previousLearning)   $lines[] = "- Previous Learning: {$previousLearning}";
            if ($mainOutcomes)       $lines[] = "- Main Outcomes: {$mainOutcomes}";
            if ($teachingStrategies)   $lines[] = "- Teaching Strategies: {$teachingStrategies}";
            if ($evaluationStrategies) $lines[] = "- Evaluation Strategies: {$evaluationStrategies}";
            if ($generalDescription)   $lines[] = "- General Notes: {$generalDescription}";

            $lines[] = "\nYOUR RESPONSE MUST BE A SINGLE VALID JSON OBJECT — NO MARKDOWN, NO EXPLANATION — with these exact keys:";
            $lines[] = '{"subject":"...","teacher_gender":"...","student_gender":"...","grade_level":"...","unit_title":"...","lesson_title":"...","classes_count":"...","previous_learning":"...","main_outcomes":"...","teaching_strategies":["..."],"evaluation_strategies":["..."],"general_description":"...","outcome_rows":[{"time":"...","learner_action":"...","teacher_action":"..."},{"time":"...","learner_action":"...","teacher_action":"..."},{"time":"...","learner_action":"...","teacher_action":"..."},{"time":"...","learner_action":"...","teacher_action":"..."}]}';
            $lines[] = "outcome_rows must have EXACTLY 4 items for: 1-Engagement  2-Explanation  3-Elaboration  4-Closing";
        } else {
            $lines[] = "الرجاء بناء خطة درس باللغة العربية بناءً على المدخلات التالية:";
            if ($subject)            $lines[] = "- المبحث / المادة: {$subject}";
            if ($teacherGender)      $lines[] = "- جنس المعلم: {$teacherGender}";
            if ($studentGender)      $lines[] = "- جنس الطلبة: {$studentGender}";
            if ($gradeLevel)         $lines[] = "- الصف الدراسي: {$gradeLevel}";
            if ($unitTitle)          $lines[] = "- عنوان الوحدة: {$unitTitle}";
            if ($lessonTitle)        $lines[] = "- عنوان الدرس: {$lessonTitle}";
            if ($classesCount)       $lines[] = "- عدد الحصص: {$classesCount}";
            if ($durationMinutes)    $lines[] = "- زمن الحصة (دقيقة): {$durationMinutes}";
            if ($previousLearning)   $lines[] = "- التعلم القبلي: {$previousLearning}";
            if ($mainOutcomes)       $lines[] = "- النتاجات الرئيسية: {$mainOutcomes}";
            if ($teachingStrategies)   $lines[] = "- استراتيجيات التدريس: {$teachingStrategies}";
            if ($evaluationStrategies) $lines[] = "- استراتيجيات التقويم: {$evaluationStrategies}";
            if ($generalDescription)   $lines[] = "- وصف عام: {$generalDescription}";

            $lines[] = "\nيجب أن يكون ردك كائن JSON واحد صالح فقط — بدون Markdown ولا شرح — بهذه المفاتيح بالضبط:";
            $lines[] = '{"subject":"...","teacher_gender":"...","student_gender":"...","grade_level":"...","unit_title":"...","lesson_title":"...","classes_count":"...","previous_learning":"...","main_outcomes":"...","teaching_strategies":["..."],"evaluation_strategies":["..."],"general_description":"...","outcome_rows":[{"time":"...","learner_action":"...","teacher_action":"..."},{"time":"...","learner_action":"...","teacher_action":"..."},{"time":"...","learner_action":"...","teacher_action":"..."},{"time":"...","learner_action":"...","teacher_action":"..."}]}';
            $lines[] = "outcome_rows يجب أن تحتوي بالضبط 4 عناصر تمثل: 1-Engagement  2-Explanation  3-Elaboration  4-Closing";
        }

        return implode("\n", $lines);
    }
}

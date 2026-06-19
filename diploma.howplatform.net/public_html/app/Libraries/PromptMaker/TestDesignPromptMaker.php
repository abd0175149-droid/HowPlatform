<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

/**
 * TestDesignPromptMaker: بناء توجيهات أداة تصميم الاختبار
 * تُنتج 3 ملفات: وورد تحليل المحتوى + إكسيل جدول المواصفات + وورد الاختبار
 */
class TestDesignPromptMaker extends BasePromptMaker
{
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        if (trim($basePrompt) !== '') {
            return trim($basePrompt);
        }

        return <<<SYS
أنت خبير تربوي متخصص في بناء الاختبارات وفق مستويات بلوم المعرفية وجداول المواصفات التربوية.
مهمتك تحليل محتوى الوحدة الدراسية وبناء اختبار متكامل يشمل:
1. تحليل المحتوى (حقائق، مفاهيم، مهارات، قيم وأنشطة) لكل درس.
2. جدول مواصفات الاختبار موزعاً على مستويات بلوم الست.
3. صياغة فقرات الاختبار وتعليماته وفق الوزن النسبي لكل مستوى.
أرجع المخرجات كـ JSON صالح فقط. لا تضف أي نص خارج JSON.
SYS;
    }

    public function buildUserPrompt(array $payload): string
    {
        $val = fn(?string $s): string => $this->cleanValue($s);

        $teacherName    = $val($payload['teacher_name']    ?? '');
        $schoolName     = $val($payload['school_name']     ?? '');
        $schoolClass    = $val($payload['school_class']    ?? '');
        $subject        = $val($payload['general_subject'] ?? '');
        $unit           = $val($payload['general_unit']    ?? '');
        $unitPages      = $val($payload['unit_pages']      ?? '');
        $semester       = $val($payload['semester']        ?? '');
        $academicYear   = $val($payload['academic_year']   ?? '');
        $totalQuestions = (int)($payload['total_exam_questions'] ?? 30);

        // معالجة الدروس: JSON array من الـ UI الديناميكي أو نص عادي
        $lessonsRaw = $payload['lessons_input'] ?? '';
        $lessonsText = '';
        if (!empty($lessonsRaw)) {
            $decoded = json_decode($lessonsRaw, true);
            if (is_array($decoded)) {
                // JSON من الـ UI الديناميكي
                $lines = [];
                foreach ($decoded as $i => $lesson) {
                    $num      = $i + 1;
                    $title    = $val($lesson['title']    ?? '');
                    $sessions = $val($lesson['sessions'] ?? '');
                    $outcomes = $val($lesson['outcomes'] ?? '');
                    $line = "الدرس {$num}: {$title}";
                    if ($sessions !== '') $line .= " | {$sessions} حصة";
                    if ($outcomes !== '') $line .= "\nالنتاجات: {$outcomes}";
                    else                  $line .= "\nالنتاجات: (يُحددها الذكاء الاصطناعي)";
                    $lines[] = $line;
                }
                $lessonsText = implode("\n\n", $lines);
            } else {
                // نص عادي
                $lessonsText = $val($lessonsRaw);
            }
        }

        $today = date('Y/m/d');

        return <<<USR
IMPORTANT: Output MUST be valid JSON only. No extra text outside JSON.

المطلوب: بناء اختبار تربوي متكامل وفق المعلومات التالية:
- اسم المعلم: {$teacherName}
- المدرسة: {$schoolName}
- الصف: {$schoolClass}
- المادة: {$subject}
- الوحدة الدراسية: {$unit}
- صفحات الوحدة: {$unitPages}
- الفصل الدراسي: {$semester}
- العام الدراسي: {$academicYear}
- تاريخ اليوم: {$today}
- عدد أسئلة الاختبار الكلي: {$totalQuestions}

معلومات الدروس والنتاجات:
{$lessonsText}

أعد JSON بالمفاتيح التالية بالضبط:

=== معلومات عامة ===
- teacher_name, school_name, school_class, general_subject, general_unit, unit_pages
- semester, academic_year, date (تاريخ اليوم بصيغة YYYY/MM/DD)
- lessons_count (عدد الدروس رقم)
- sessions_count (مجموع عدد الحصص الكلي رقم)
- outcomes_count (مجموع النتاجات الكلي رقم)
- total_exam_questions (عدد أسئلة الاختبار الكلي رقم)

=== تحليل المحتوى (للوثيقة الأولى - وورد) ===
"analysis_rows": مصفوفة من الكائنات، كل كائن يمثل نتاجاً واحداً:
{
  "unit_number": "رقم الوحدة",
  "unit_title": "عنوان الوحدة",
  "lesson_title": "عنوان الدرس",
  "outcome": "النتاج التعليمي كاملاً",
  "higher_skills": "مستويات المهارات العليا المناسبة (التحليل، التقويم، التركيب)",
  "lower_skills": "مستويات المهارات الدنيا المناسبة (التذكر، الفهم، التطبيق)"
}

=== جدول المواصفات (للملف الثاني - إكسيل) ===
"specification_rows": مصفوفة من الكائنات، كل كائن يمثل درساً واحداً:
{
  "lesson_title": "اسم الدرس",
  "sessions_count": عدد الحصص (رقم),
  "bloom_remember": عدد أهداف مستوى التذكر (رقم),
  "bloom_understand": عدد أهداف مستوى الفهم (رقم),
  "bloom_apply": عدد أهداف مستوى التطبيق (رقم),
  "bloom_create": عدد أهداف مستوى التركيب (رقم),
  "bloom_analyze": عدد أهداف مستوى التحليل (رقم),
  "bloom_evaluate": عدد أهداف مستوى التقويم (رقم)
}

=== الاختبار (للوثيقة الثالثة - وورد) ===
"exam_time": "زمن الاختبار (مثال: ساعة واحدة)",
"exam_total_marks": الدرجة الكلية (رقم),
"exam_instructions": "تعليمات الاختبار العامة (جملتان أو ثلاث)",
"exam_sections": مصفوفة من الأقسام:
[
  {
    "section_title": "عنوان القسم (مثال: السؤال الأول: اختر الإجابة الصحيحة)",
    "section_marks": الدرجات المخصصة (رقم),
    "section_instruction": "تعليمات القسم",
    "questions": مصفوفة من الأسئلة:
    [
      {
        "number": رقم السؤال,
        "text": "نص السؤال",
        "options": ["أ. الخيار الأول", "ب. الخيار الثاني", "ج. الخيار الثالث", "د. الخيار الرابع"],
        "marks": درجة السؤال
      }
    ]
  }
]

قواعد مهمة:
1. وزّع الأسئلة على مستويات بلوم بما يتناسب مع جدول المواصفات.
2. يجب أن يكون مجموع درجات الأقسام = exam_total_marks.
3. استخدم لغة عربية أكاديمية دقيقة.
4. options تكون فارغة [] للأسئلة المقالية والقصيرة.
5. اجعل الأسئلة مناسبة لمستوى الصف والمادة والوحدة المعطاة.
USR;
    }
}

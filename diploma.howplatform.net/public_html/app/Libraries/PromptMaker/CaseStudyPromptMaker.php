<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

/**
 * CaseStudyPromptMaker: بناء توجيهات تحليل دراسة الحالة
 */
class CaseStudyPromptMaker extends BasePromptMaker
{
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        if (trim($basePrompt) === '') {
            return <<<SYS
أنت خبير متخصص في تحليل دراسات الحالة التربوية والتعليمية. مهمتك هي تحليل دراسة حالة تربوية بشكل منهجي وعميق وفق معايير المعلمين المحددة.

دورك:
- محلل تربوي متخصص في دراسات الحالة التعليمية
- تحلل الحالة بشكل شامل ومنهجي
- تربط التحليل بمعايير المعلمين المحددة
- تقدم تحليلاً عملياً وقابلاً للتطبيق

كيفية التحليل:
1. فهم عميق للحالة: اقرأ الحالة بعناية وافهم جميع العناصر والسياق
2. تحليل منهجي: اتبع هيكل تحليل محدد ومنظم
3. ربط بالمعايير: ربط كل معيار من معايير المعلمين بالحالة بشكل واضح ومحدد
4. تفكير نقدي: تحليل نقاط القوة والضعف والتحديات والفرص
5. حلول عملية: اقتراح بدائل واقعية وقابلة للتطبيق مع تحليل مفصل

المخرجات المطلوبة:
- يجب أن تكون المخرجات JSON صالح فقط، بدون أي نص خارجي
- ابدأ مباشرة بـ { وانتهي بـ }
- لا تستخدم markdown code blocks (```json أو ```)
- لا تضع أي شرح أو تعليقات خارج JSON
- جميع الحقول النصية باللغة العربية الفصحى المهنية
- التحليل يجب أن يكون عميقاً وليس سطحياً

في حالة نقص المدخلات الجوهرية، أعد JSON وفق بروتوكول نقص المدخلات المحدد في رسالة المستخدم.
SYS;
        }
        return trim($basePrompt);
    }

    public function buildUserPrompt(array $payload): string
    {
        $val = fn(?string $s): string => $this->cleanValue($s);

        $teacherName = $val($payload['teacher_name'] ?? '');
        $gradeLevel = $val($payload['grade_level'] ?? '');
        $caseText = $val($payload['case_text'] ?? '');
        $selectedCaseIndex = isset($payload['selected_case_index']) ? (int)$payload['selected_case_index'] : -1;
        $criteria = is_array($payload['criteria'] ?? null) ? $payload['criteria'] : [];
        $analysisNature = $val($payload['analysis_nature'] ?? '');

        // محاولة قراءة JSON من task_prompt إذا كان متوفراً
        $taskPrompt = $val($payload['task_prompt'] ?? '');
        $caseStudyData = null;
        if ($taskPrompt) {
            try {
                $parsed = json_decode($taskPrompt, true);
                if (is_array($parsed) && isset($parsed['type']) && $parsed['type'] === 'case_study') {
                    $caseStudyData = $parsed;
                }
            } catch (\Exception $e) {
                // إذا لم يكن JSON، استخدمه كنص عادي
            }
        }

        // إذا كانت البيانات من JSON
        if ($caseStudyData && isset($caseStudyData['cases']) && is_array($caseStudyData['cases'])) {
            $cases = $caseStudyData['cases'];
            $finalCaseText = '';
            $caseTitle = '';
            
            if ($selectedCaseIndex >= 0 && $selectedCaseIndex < count($cases)) {
                $selectedCase = $cases[$selectedCaseIndex];
                $caseTitle = $selectedCase['title'] ?? '';
                $finalCaseText = $selectedCase['text'] ?? '';
            } elseif (count($cases) > 0) {
                // إذا لم يتم تحديد حالة، استخدم الأولى
                $selectedCase = $cases[0];
                $caseTitle = $selectedCase['title'] ?? '';
                $finalCaseText = $selectedCase['text'] ?? '';
            }
            
            // استخدام المعايير من JSON
            if (isset($caseStudyData['criteria']) && is_array($caseStudyData['criteria'])) {
                $criteria = $caseStudyData['criteria'];
            }
        } else {
            // استخدام النص العادي (للتوافق مع البيانات القديمة)
            $cases = $this->extractCases($caseText);
            $finalCaseText = $caseText;
            $caseTitle = '';
            
            if (count($cases) > 1 && $selectedCaseIndex >= 0 && $selectedCaseIndex < count($cases)) {
                $finalCaseText = $cases[$selectedCaseIndex];
            } elseif (count($cases) > 1) {
                $finalCaseText = $cases[0] ?? $caseText;
            }
        }

        // تحويل المعايير إلى نص
        $criteriaText = '';
        if (!empty($criteria)) {
            $criteriaLines = [];
            foreach ($criteria as $criterion) {
                if (is_string($criterion)) {
                    $criteriaLines[] = '• ' . $criterion;
                } elseif (is_array($criterion) && isset($criterion['code']) && isset($criterion['description'])) {
                    $criteriaLines[] = '• ' . $criterion['code'] . ': ' . $criterion['description'];
                }
            }
            $criteriaText = implode("\n", $criteriaLines);
        }

        $user = <<<USR
IMPORTANT: Output MUST be JSON only with no extra text outside JSON. Start with { and end with }.

المهمة: تحليل دراسة حالة تربوية بشكل منهجي وشامل وفق معايير المعلمين المحددة.

بيانات التحليل:
- اسم المعلم الطالب: {$teacherName}
- المرحلة التي يدرسها: {$gradeLevel}
- عنوان الحالة: {$caseTitle}
- نص دراسة الحالة:
{$finalCaseText}

معايير المعلمين المطلوبة:
{$criteriaText}

تعليمات التحليل خطوة بخطوة:

1. التحقق من المدخلات:
   - تحقق من اكتمال جميع المدخلات (teacher_name, grade_level, case_text, criteria)
   - إذا كان هناك نقص، أعد JSON وفق بروتوكول نقص المدخلات

2. تحليل السياق (Context):
   - حدد البيئة التعليمية (المدرسة، الصف، المادة الدراسية)
   - وصف الظروف المحيطة بالحالة
   - تحديد الإطار الزمني والمكاني
   - وصف العلاقات بين الأطراف المشاركة

3. تحديد المعضلة الرئيسة (Main Dilemma):
   - حدد المشكلة الأساسية أو التحدي المركزي في الحالة
   - اشرح سبب كونها معضلة تربوية
   - وضح الآثار المترتبة على العملية التعليمية

4. تحليل الشخصيات (Characters):
   - حدد جميع الأطراف المشاركة في الحالة
   - لكل شخصية: اكتب اسم الشخصية متبوعاً بوصف دورها ومسؤولياتها وعلاقاتها
   - اكتب كل شخصية كعنصر منفصل في المصفوفة
   - مثال: "المعلم محمد: [وصف دوره وعلاقته بالحالة]"

5. تحديد الحالة (Case Identification):
   - وصف دقيق ومفصل للحالة كما حدثت
   - تحليل العناصر الأساسية المكونة للحالة
   - تحديد العوامل المؤثرة والظروف الخاصة
   - وصف التسلسل الزمني للأحداث

6. وصف الحالة (Case Description):
   - شرح شامل للوضع الراهن
   - وصف التفاعلات بين الأطراف
   - تحليل الظروف والمؤثرات
   - وصف النتائج والتأثيرات الحالية

7. تقييم الحالة (Case Evaluation):
   أ) التقييم العام:
      - تقييم عام وشامل للحالة
      - تحديد التحديات والفرص
      - تقييم تأثير الحالة على العملية التعليمية
      - تحليل النتائج المتوقعة والآثار طويلة المدى
   
   ب) نقاط القوة:
      - حدد نقاط القوة في الحالة (كل نقطة قوة في عنصر منفصل)
      - اكتب كل نقطة قوة كعنصر منفصل في المصفوفة
   
   ج) جوانب التطوير:
      - حدد جوانب التطوير والتحسين المطلوبة (كل جانب في عنصر منفصل)
      - اكتب كل جانب تطوير كعنصر منفصل في المصفوفة

8. البدائل المقترحة (Proposed Alternatives):
   - اقتراح حلول عملية وقابلة للتطبيق (3-5 بدائل على الأقل)
   - لكل بديل: اكتب وصف الحل، الإجراءات المطلوبة، الإيجابيات، السلبيات، تقييم الجدوى، خطة التنفيذ
   - اكتب كل بديل كعنصر منفصل في المصفوفة
   - مثال: "البديل الأول: [وصف شامل للحل مع الإجراءات والإيجابيات والسلبيات]"

9. ربط التحليل بالمعايير (Teacher Criteria):
   - أدرج فقط أرقام المعايير (الكود) بدون أي تفاصيل أو شرح
   - مثال: إذا كان المعيار "1.1.4 - فهم طبيعة التعلم"، أدرج فقط "1.1.4"

متطلبات الجودة:
- اللغة: عربية فصحى مهنية ودقيقة
- العمق: تحليل عميق وليس سطحي
- الدقة: ربط واضح ومحدد بين الحالة والمعايير
- الشمولية: تغطية جميع جوانب الحالة
- العملية: حلول واقعية وقابلة للتطبيق

هيكل JSON المطلوب (بدون قيم فارغة):
{
  "teacher_name": "اسم المعلم",
  "grade_level": "المرحلة",
  "case_title": "عنوان الحالة",
  "analysis_context": "السياق: [وصف شامل]",
  "analysis_main_dilemma": "المعضلة الرئيسة: [وصف مفصل]",
  "analysis_characters": ["الشخصية 1: [وصف]", "الشخصية 2: [وصف]", "..."],
  "analysis_case_identification": "تحديد الحالة: [وصف دقيق]",
  "analysis_case_description": "وصف الحالة: [شرح شامل]",
  "analysis_case_evaluation": "تقييم الحالة: [تقييم عام شامل - التحديات والفرص والتأثير]",
  "analysis_strengths": ["نقطة قوة 1", "نقطة قوة 2", "..."],
  "analysis_improvement_aspects": ["جانب تطوير 1", "جانب تطوير 2", "..."],
  "analysis_proposed_alternatives": ["البديل 1: [وصف شامل]", "البديل 2: [وصف شامل]", "..."],
  "analysis_teacher_criteria": ["1.1.4", "2.3.1", "..."]
}

ملاحظات مهمة:
- analysis_characters: يجب أن يكون مصفوفة (array) حيث كل عنصر يمثل شخصية واحدة
- analysis_strengths: يجب أن يكون مصفوفة (array) حيث كل عنصر يمثل نقطة قوة واحدة
- analysis_improvement_aspects: يجب أن يكون مصفوفة (array) حيث كل عنصر يمثل جانب تطوير واحد
- analysis_proposed_alternatives: يجب أن يكون مصفوفة (array) حيث كل عنصر يمثل بديل واحد كامل
- analysis_teacher_criteria: يجب أن يكون مصفوفة (array) من أرقام المعايير فقط (الكود) بدون أي تفاصيل
- الحقول الأخرى: نصوص عادية (strings)

بروتوكول نقص المدخلات:
إذا كانت أي من المدخلات الجوهرية مفقودة (teacher_name, grade_level, case_text, criteria)، أعد JSON بالهيئة التالية فقط:
{
  "need_more_info": ["اسم_الحقل_1", "اسم_الحقل_2"],
  "message": "يرجى تزويدي بالقيم الناقصة لاستكمال التحليل."
}

تذكير نهائي صارم:
- JSON فقط، بدون أي نص خارجي
- ابدأ مباشرة بـ { ولا تضع أي نص قبله
- انتهِ مباشرة بـ } ولا تضع أي نص بعده
- لا تستخدم markdown code blocks
- جميع الحقول مطلوبة (لا قيم فارغة)
USR;

        return $user;
    }

    /**
     * استخراج حالات متعددة من النص (إذا كانت موجودة)
     * يفترض أن الحالات منفصلة بعلامات واضحة مثل "الحالة الأولى:" أو "Case 1:" أو أرقام
     */
    private function extractCases(string $text): array
    {
        $cases = [];
        
        // محاولة استخراج الحالات بناءً على أنماط مختلفة
        $patterns = [
            '/الحالة\s+(?:الأولى|الثانية|الثالثة|الرابعة|الخامسة|1|2|3|4|5)[:：]/u',
            '/Case\s+(?:1|2|3|4|5|One|Two|Three|Four|Five)[:：]/ui',
            '/\n\s*(?:الحالة|Case)\s*\d+\s*[:：]/ui',
        ];

        $found = false;
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                $found = true;
                $positions = [];
                foreach ($matches[0] as $match) {
                    $positions[] = $match[1];
                }
                
                // إضافة نهاية النص كموضع أخير
                $positions[] = strlen($text);
                
                // استخراج الحالات
                for ($i = 0; $i < count($positions) - 1; $i++) {
                    $start = $positions[$i];
                    $end = $positions[$i + 1];
                    $case = trim(substr($text, $start, $end - $start));
                    if (!empty($case)) {
                        $cases[] = $case;
                    }
                }
                break;
            }
        }

        // إذا لم نجد حالات متعددة، نعيد النص كحالة واحدة
        if (!$found || empty($cases)) {
            return [trim($text)];
        }

        return $cases;
    }

    /**
     * الحصول على توجيهات طبيعة التحليل
     */
    private function getAnalysisNatureGuidance(string $nature): string
    {
        $guidances = [
            'comprehensive' => 'يرجى إجراء تحليل شامل ومتكامل يغطي جميع جوانب الحالة: السياق، الشخصيات، المعضلة، التقييم، والبدائل.',
            'critical' => 'يرجى التركيز على التحليل النقدي: تحديد نقاط الضعف، التحديات، والمشاكل المحتملة في الحالة.',
            'solution_oriented' => 'يرجى التركيز على الحلول والبدائل العملية: اقتراح حلول قابلة للتطبيق مع تحليل إيجابيات وسلبيات كل حل.',
            'reflective' => 'يرجى إجراء تحليل تأملي: التركيز على التعلم والدروس المستفادة من الحالة وكيف يمكن تطبيقها.',
            'practical' => 'يرجى التركيز على الجانب العملي: كيفية تطبيق الحلول في الواقع العملي مع مراعاة القيود والموارد المتاحة.',
            'theoretical' => 'يرجى التركيز على الجانب النظري: ربط الحالة بالأطر المفاهيمية والنظريات التربوية ذات الصلة.',
        ];

        $guidance = $guidances[$nature] ?? '';
        return $guidance ? "\n- توجيهات خاصة: {$guidance}" : '';
    }
}


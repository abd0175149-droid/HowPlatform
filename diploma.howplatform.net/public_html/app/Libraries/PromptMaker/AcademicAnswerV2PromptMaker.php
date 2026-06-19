<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

/**
 * AcademicAnswerV2PromptMaker: بناء prompts لأداة الإجابة الأكاديمية الإصدار 2
 * بالاستناد إلى النص المرفق في المهمة (task_prompt) بدلاً من RAG
 */
class AcademicAnswerV2PromptMaker extends BasePromptMaker
{
    /**
     * بناء system prompt
     */
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        if (trim($basePrompt) === '') {
            return <<<SYS
أنت خبير أكاديمي متخصص في تقديم إجابات وتحليلات أكاديمية احترافية بناءً على النصوص المرجعية المقدمة لك حصراً.
مهمتك إنتاج إجابات دقيقة ومتوازنة تستند إلى فهم عميق للسؤال والنص المرفق.

قبل البدء بالكتابة، يجب عليك:
1. قراءة السؤال أو المهمة المطلوبة بعناية.
2. تحليل النص المرجعي المرفق بدقة لاستخراج المعلومات والمفاهيم المتعلقة بالسؤال.
3. التفكير الناقد لربط الأفكار الموجودة في النص والإجابة بطريقة متماسكة.

احرص دائمًا على ما يلي:
- الكتابة بأسلوب أكاديمي مهني واضح ومتماسك.
- الاعتماد بشكل أساسي وحصري على المعلومات الموجودة في النص المرجعي المرفق.
- تجنب الافتراضات أو المعلومات الخارجية التي لم ترد في النص المرفق.
- الالتزام التام بأن يكون المخرج كائن JSON واحد صالح فقط، بدون أي نص إضافي قبله أو بعده.
- ابدأ استجابتك مباشرة بـ { ولا تضع أي نص قبله.
- انتهِ استجابتك مباشرة بـ } ولا تضع أي نص بعده.
- لا تستخدم markdown code blocks (```json أو ```).
- لا تضع أي شرح أو تعليقات خارج JSON.
- عدم ذكر كونك نموذجًا لغويًا أو نظامًا ذكيًا.

CRITICAL: لا تُنتِج أبدًا أي شيء خارج JSON صالح واحد. ابدأ مباشرة بـ { وانتهِ بـ }.
SYS;
        }
        return trim($basePrompt);
    }

    /**
     * بناء user prompt استناداً للبيانات والنص المرفق
     */
    public function buildUserPrompt(array $payload): string
    {
        $val = fn(?string $s): string => $this->cleanValue($s);

        $question = $val($payload['question'] ?? '');
        $wordCount = (int)($payload['word_count'] ?? 300);
        $format = $val($payload['format'] ?? 'essay'); 
        $contextText = $val($payload['task_prompt'] ?? '');

        if ($question === '') {
            return json_encode([
                'need_more_info' => ['question'],
                'message' => 'يرجى تزويدي بالسؤال أو المهمة المطلوبة.'
            ], JSON_UNESCAPED_UNICODE);
        }

        if ($contextText === '') {
            return json_encode([
                'need_more_info' => ['task_prompt'],
                'message' => 'أحتاج إلى النص المرجعي (المرفق في المهمة) لأتمكن من الإجابة واستخلاص المعلومات.'
            ], JSON_UNESCAPED_UNICODE);
        }

        $formatInstructions = $this->getFormatInstructions($format);

        $minWords = $this->calculateMinWords($wordCount);
        $maxWords = $this->calculateMaxWords($wordCount);

        return <<<USR
CRITICAL: You MUST return ONLY valid JSON. No explanations, no markdown, no code blocks, no text before or after JSON.
Return exactly ONE valid JSON object with the following key:
- "content": الإجابة الكاملة باللغة العربية بناءً على النص المرفق.

السياق/النص المرجعي المرفق:
{$contextText}

المهمة / السؤال:
{$question}

{$formatInstructions}

تعليمات إلزامية للإجابة:
1. استخرج المعلومات المباشرة والضمنية من "النص المرجعي المرفق" فقط لدعم إجابتك.
2. لا تستخدم معلومات خارجية أو معرفة عامة غير مدعمة بالنص.
3. التزم بعدد الكلمات المطلوب: بين {$minWords} و {$maxWords} كلمة.
4. يجب أن تكون الإجابة مبنية بشكل منطقي وأكاديمي رصين.

مثال على الصيغة الصحيحة للإخراج (ابدأ مباشرة بهذا):
{
  "content": "الإجابة هنا..."
}

لا تضع أي شيء قبل { أو بعد }.
USR;
    }

    /**
     * تعليمات التنسيق حسب النوع
     */
    private function getFormatInstructions(string $format): string
    {
        switch ($format) {
            case 'structured':
                return <<<FMT
التنسيق المطلوب: إجابة منظمة
- ابدأ بمقدمة موجزة.
- قسّم الإجابة إلى فقرات رئيسية مع استخدام القوائم النقطية لتوضيح الأفكار المستخرجة.
- اختم بخاتمة تلخص الاستنتاجات.
FMT;

            case 'essay':
                return <<<FMT
التنسيق المطلوب: مقال أكاديمي
- اكتب نصًا متدفقًا ومترابطًا.
- ابدأ بمقدمة تقدم الموضوع وتربطه بالنص.
- طور الأفكار في فقرات سردية مترابطة دون استخدام النقاط (bullets).
- اختم بخاتمة تلخص النقاط الرئيسية التي تم التوصل إليها.
FMT;

            default:
                return <<<FMT
التنسيق المطلوب: نص أكاديمي واضح
- اكتب نصًا واضحًا ومترابطًا ينقل الإجابة بشكل سلس.
- نظّم الأفكار بشكل منطقي.
FMT;
        }
    }

    /**
     * حساب الحد الأدنى للكلمات
     */
    private function calculateMinWords(int $target): int
    {
        return (int) floor($target * 0.9);
    }

    /**
     * حساب الحد الأقصى للكلمات
     */
    private function calculateMaxWords(int $target): int
    {
        return (int) ceil($target * 1.1);
    }
}

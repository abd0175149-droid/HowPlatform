<?php
declare(strict_types=1);

namespace App\Libraries\PromptMaker;

/**
 * ForumReplyPromptMaker: بناء توجيهات ردود المنتدى
 */
class ForumReplyPromptMaker extends BasePromptMaker
{
    public function buildSystemPrompt(string $basePrompt, array $payload): string
    {
        if (trim($basePrompt) === '') {
            return <<<SYS
أنت خبير في كتابة ردود منتدى تعليمية احترافية لمعلمي ومعلمات الدبلوم المهني في التعليم.
مهمتك إنتاج ردود عميقة وناقدة ومترابطة، تراعي سياق المهمة والقراءات السابقة، وتقدّم قيمة مهنية حقيقية.
احرص دائمًا على ما يلي:
- الكتابة بأسلوب مهني، واضح، متماسك، وتأملي موجّه لمعلم/معلمة.
- تجنّب النسخ الحرفي من نص السؤال أو من ملاحظات الطالب، وأعد صياغة الأفكار بلغة جديدة مع الحفاظ على المعنى.
- عدم ذكر كونك نموذجًا لغويًا أو نظامًا ذكيًا؛ تحدّث بصوت معلم محترف يشارك خبرته.
- الالتزام التام بأن يكون المخرج كائن JSON واحد صالح فقط، بدون أي نص إضافي قبله أو بعده أو تعليقات أو شرح خارجي.

لا تُنتِج أبدًا أي شيء خارج JSON صالح واحد.
SYS;
        }
        return trim($basePrompt);
    }

    public function buildUserPrompt(array $payload): string
    {
        $val = fn(?string $s): string => $this->cleanValue($s);

        // إذا كان ردًا على تعليق، استخدم ForumReplyCommentPromptMaker
        if (!empty($payload['is_comment_reply']) && !empty($payload['comment_text'])) {
            $commentMaker = new ForumReplyCommentPromptMaker();
            return $commentMaker->buildUserPrompt($payload);
        }

        // استخدام task_title و task_prompt من قاعدة البيانات بدلاً من topic و context إن وُجِدت
        $topic        = $val($payload['task_title']  ?? $payload['topic']   ?? '');
        $context      = $val($payload['task_prompt'] ?? $payload['context'] ?? '');
        $requirements = $val($payload['requirements'] ?? '');
        $tone         = $val($payload['tone'] ?? 'formal');
        $audience     = $val($payload['audience'] ?? 'teachers');
        $highlights   = is_array($payload['highlights'] ?? null)
            ? $this->arrayToText($payload['highlights'])
            : '';
        $studentAnswer = $val($payload['student_answer'] ?? ''); // إجابة الطالب الخاصة (اختياري)

        $studentAnswerSection = '';
        if ($studentAnswer !== '') {
            $studentAnswerSection = <<<STU

إجابة/ملاحظات خاصة من الطالب (يجب استخدامها كنواة لتمييز الرد وضمان فرديّته):
{$studentAnswer}

تعليمات إلزامية حول إجابة الطالب:
- اقرأ هذه الإجابة بعناية، واستخرج منها المحاور الرئيسة والأفكار الجوهرية ونقاط الترابط مع المهمة والقراءات.
- لا تنسخ عبارات الطالب حرفيًا؛ أعد صياغتها وعمّقها ضمن الفقرة النهائية بأسلوب مهني جديد.
- اجعل صوت الطالب وخبرته وانشغالاته محورًا أساسيًا في بناء الرد، مع توسيعها وتأطيرها نظريًا.
STU;
        }

        return <<<USR
IMPORTANT: Output MUST be JSON only with no extra text outside JSON.
Return exactly ONE valid JSON object with the following keys:
- "title": عنوان قصير ومعبر لرد المنتدى.
- "content": الفقرة/الفقرتان الكاملتان للرد.
- "tags": مصفوفة من 3 إلى 6 وسوم نصية قصيرة (بالعربية أو الإنجليزية) تعبّر عن موضوع الرد.

بيانات المهمة:
- الموضوع (Topic / Task Title): {$topic}
- السياق/وصف المهمة (Context / Task Prompt): {$context}
- المتطلبات/أسئلة المهمة (Requirements): {$requirements}
- الجمهور المستهدف (Audience): {$audience}
- نبرة الرد (Tone): {$tone}
- المحاور المطلوب تضمينها في الرد (Key points to cover): {$highlights}
{$studentAnswerSection}

تعليمات إلزامية لبناء محتوى "content":
1. حلّل سياق المهمة أو أسئلتها، ثم اربط بين سياق المهمة والتعليمات الخاصة (المتطلبات، النبرة، الجمهور، المحاور) باعتبارها تعليمات إلزامية يجب الالتزام بها دائمًا.
2. إذا وُجدت إجابة خاصة للطالب، فهِمْها بعمق واستخرج منها المحاور الرئيسة ونقاط الترابط مع المهمة، واستخدمها كنواة لكتابة الرد مع إعادة الصياغة وعدم النسخ الحرفي.
3. التزم بتوجيهات النظام (system prompt) فيما يخص الاستراتيجية المطلوبة وطول الرد المسموح به.
4. يجب ربط النص بشكل صريح بموضوع المهمة وأسئلتها وسياقها، ولكن بدون اعتماد أسلوب سؤال وجواب، بل على شكل نص تأملي/تحليلي متصل.
5. لا تستخدم في "content" أي ترقيم أو تعداد نقطي أو نجوم أو عناوين فرعية أو جداول أو تنسيق Markdown؛ النص يجب أن يكون متصلًا كفقرات نصية مفصولة.
8. تجنّب إدراج أي JSON أو كود أو أقواس برمجية داخل الحقل "content". يجب أن يحتوي "content" على نص عادي فقط (plain text).
9. في "tags"، استخدم كلمات مفتاحية قصيرة تعبّر عن موضوع النقاش، مثل:
   ["بيئة التعلم","تأمل مهني","تطوير الممارسة","تعلّم ذاتي","التعلم التأملي","استجابات المنتدى"].

تذكير أخير:
- أعد الرد بصيغة JSON فقط، بلا أي شرح أو تعليقات خارجية.
- أي خروج عن البنية المحددة (title, content, tags) أو إضافة نص خارج JSON يُعتبر مخالفة للتعليمات.
USR;
    }
}

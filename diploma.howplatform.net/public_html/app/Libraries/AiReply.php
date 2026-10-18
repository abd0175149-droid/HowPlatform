<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * توليد رد فَطين الأولي على طلب خدمة (نص حر عبر Gemini). كل العمليات غير قاتلة.
 */
class AiReply
{
    /**
     * موديل ردّ الخدمة الافتراضي: خفيف وسريع. لا يرث AI_GEMINI_MODEL (قد يكون
     * gemini-2.5-pro «تفكير» يستهلك رصيد الإخراج قبل كتابة أي نص). قابل للتخصيص
     * عبر AI_SERVICE_REPLY_MODEL.
     */
    private const DEFAULT_MODEL  = 'gemini-2.5-flash';

    /** سقوط احتياطي بلا «تفكير» إطلاقًا إن رجع الموديل الأساسي فارغًا. */
    private const FALLBACK_MODEL = 'gemini-2.0-flash';

    /** يكفي بسهولة لردّ من 3–5 جُمل (مع إطفاء التفكير). */
    private const MAX_OUTPUT_TOKENS = 1024;

    /**
     * رد أولي واحد: تلخيص فهم الطلب + مساعدة عامة + إحالة للفريق الأكاديمي.
     * يعيد نص الرد أو null عند أي تعذّر.
     */
    public static function serviceFirstReply(string $subject, string $description, string $studentName = ''): ?string
    {
        try {
            // نقبل الاسمين: AI_GEMINI_API_KEY (بقية الأدوات) و GEMINI_API_KEY (شات فطين).
            $apiKey = getenv('AI_GEMINI_API_KEY') ?: getenv('GEMINI_API_KEY') ?: '';
            if ($apiKey === '') {
                log_message('warning', 'AiReply: missing API key (AI_GEMINI_API_KEY / GEMINI_API_KEY).');
                return null;
            }
            $model = getenv('AI_SERVICE_REPLY_MODEL') ?: self::DEFAULT_MODEL;

            $system = 'أنت "فَطين"، المساعد الذكي لمنصة كيف التعليمية. أنشأ الطالب طلب خدمة متخصص. '
                . 'اكتب رداً أولياً واحداً ودوداً ومهنياً بالعربية الفصحى المبسّطة، في 3 إلى 5 جمل: '
                . '(1) لخّص فهمك لطلبه بإيجاز. '
                . '(2) قدّم مساعدة أولية عامة أو خطوة/نصيحة مفيدة إن أمكن، ووجّهه للأداة المناسبة من أدوات المنصة إن كان طلبه يخصّها. '
                . '(3) أخبره بوضوح أن الفريق الأكاديمي المعني سيتواصل معه قريباً لمتابعة طلبه. '
                . 'لا تَعِد بمواعيد زمنية محددة، ولا تختلق معلومات أو أدوات غير مذكورة أدناه، وكن دافئاً ومختصراً.'
                // سياق مُصغّر: فقط المعرفة الأوثق صلة بموضوع/تفاصيل الطلب (تخفيف الحمولة).
                . "\n\n" . self::platformContext(trim($subject . ' ' . $description));

            $userText = trim(
                ($studentName !== '' ? "اسم الطالب: {$studentName}\n" : '')
                . "موضوع الطلب: {$subject}\n"
                . "تفاصيل الطلب: {$description}"
            );

            // المحاولة الأساسية: موديل خفيف مع إطفاء «التفكير».
            $text = self::callGemini($apiKey, $model, $system, $userText);

            // سقوط احتياطي: موديل بلا تفكير أصلاً (يعالج بقايا حالات MAX_TOKENS).
            if (($text === null || $text === '') && $model !== self::FALLBACK_MODEL) {
                $text = self::callGemini($apiKey, self::FALLBACK_MODEL, $system, $userText);
            }

            return ($text !== null && $text !== '') ? $text : null;
        } catch (\Throwable $e) {
            log_message('error', 'AiReply::serviceFirstReply failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * نداء Gemini واحد. يُطفئ «التفكير» لموديلات 2.5 (thinkingBudget=0) كي لا يُستهلك
     * رصيد الإخراج قبل النص. يعيد النص أو null.
     */
    private static function callGemini(string $apiKey, string $model, string $system, string $userText): ?string
    {
        try {
            $generationConfig = [
                'temperature'     => 0.6,
                'maxOutputTokens' => self::MAX_OUTPUT_TOKENS,
            ];
            // إطفاء التفكير مدعوم لموديلات 2.5 فقط؛ إرساله لغيرها قد يسبّب 400.
            if (stripos($model, '2.5') !== false) {
                $generationConfig['thinkingConfig'] = ['thinkingBudget' => 0];
            }

            $url    = 'https://generativelanguage.googleapis.com/v1beta/models/'
                . rawurlencode($model) . ':generateContent?key=' . urlencode($apiKey);
            // نسخة غير مشتركة (getShared=false) كي تُطبَّق مهلتنا فعلًا. النسخة المشتركة قد تكون
            // ضُبطت بمهلة قصيرة (Config\Plans = 1.5s) عبر PlansClient في نفس الطلب، فيفشل نداء
            // Gemini بعد 1.5s (cURL 28). هذا سبب عدم ظهور رد فَطين رغم صحّة المفتاح والموديل.
            $client = \Config\Services::curlrequest(
                ['timeout' => 20, 'connect_timeout' => 5],
                null,
                null,
                false
            );
            $res    = $client->post($url, [
                'json' => [
                    'system_instruction' => ['parts' => [['text' => $system]]],
                    'contents'           => [['role' => 'user', 'parts' => [['text' => $userText]]]],
                    'generationConfig'   => $generationConfig,
                ],
                'http_errors' => false,
            ]);

            $status = $res->getStatusCode();
            $data   = json_decode((string) $res->getBody(), true);

            if ($status >= 300) {
                $msg = $data['error']['message'] ?? substr((string) $res->getBody(), 0, 200);
                log_message('warning', "AiReply: Gemini {$model} HTTP {$status}: {$msg}");
                return null;
            }

            // اجمع أجزاء النص وتجاهل أجزاء «التفكير» (thought)
            $parts = $data['candidates'][0]['content']['parts'] ?? [];
            $text  = '';
            foreach ($parts as $p) {
                if (! empty($p['thought'])) {
                    continue;
                }
                if (isset($p['text'])) {
                    $text .= $p['text'];
                }
            }
            $text = trim($text);

            if ($text === '') {
                $fr = $data['candidates'][0]['finishReason'] ?? '?';
                log_message('warning', "AiReply: empty text from {$model} (finishReason={$fr}): " . substr((string) $res->getBody(), 0, 200));
                return null;
            }

            return $text;
        } catch (\Throwable $e) {
            log_message('error', "AiReply::callGemini({$model}) failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * سياق المنصّة لمساعدي الذكاء (فَطين / رد الخدمة).
     * - عند تمرير $query تُرسَل المعرفة الأوثق صلة فقط (تخفيف الحمولة).
     * - عند غيابه تُعاد المعرفة كاملة (توافقًا مع شات فطين العام في FateenAi).
     * تفويض لـ PlatformKnowledge (المصدر الموحّد القابل للتوسّع).
     */
    public static function platformContext(string $query = ''): string
    {
        return PlatformKnowledge::context($query);
    }
}

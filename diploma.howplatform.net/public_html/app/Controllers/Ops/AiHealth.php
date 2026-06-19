<?php

declare(strict_types=1);

namespace App\Controllers\Ops;

use App\Controllers\BaseController;
use App\Libraries\AiClient;
use CodeIgniter\API\ResponseTrait;

/**
 * فحص اتصال مزوّد الذكاء الاصطناعي وعرض تفاصيل تشخيصية.
 * GET /ops/ai/health
 *   - ok:true  => كل شيء سليم ويظهر الردّ المفكوك
 *   - ok:false => تُعرض رسالة الخطأ + ذيل اللوج
 */
class AiHealth extends BaseController
{
    use ResponseTrait;

    public function ping()
    {
        $provider = getenv('AI_PROVIDER') ?: 'gemini';
        $model    = $provider === 'openai'
            ? (getenv('AI_OPENAI_MODEL') ?: 'gpt-4o-mini')
            : (getenv('AI_GEMINI_MODEL') ?: 'gemini-1.5-pro');

        try {
            $client = new AiClient();

            // توجيه صغير يجبر إخراج JSON فقط
            $systemPrompt = 'أعد JSON صالح فقط دون أي نص خارجه. مثال: {"probe":"ok"}';
            $payload      = ['teacher_name' => 'Probe']; // حمولة بسيطة

            $t0    = microtime(true);
            $json  = $client->generateJson($systemPrompt, $payload);
            $ms    = (int) round((microtime(true) - $t0) * 1000);
            $parsed = json_decode($json, true);

            return $this->respond([
                'ok'         => is_array($parsed),
                'provider'   => $provider,
                'model'      => $model,
                'latency_ms' => $ms,
                'raw'        => $json,
                'parsed'     => $parsed ?: null,
            ]);
        } catch (\Throwable $e) {
            // نقرأ ذيل لوج HTTP الذي كتبناه في AiClient::curlJson()
            $tail = $this->readLogTail();

            // نُرجع 200 مع ok:false بدل 500، لتظهر التفاصيل في المتصفح
            return $this->respond([
                'ok'       => false,
                'provider' => $provider,
                'model'    => $model,
                'error'    => $e->getMessage(),
                'hint'     => $this->hintFromError($e->getMessage()),
                'log_tail' => $tail,
            ]);
        }
    }

    /**
     * قراءة آخر ~1200 بايت من ملف اللوج ai_http-YYYYMMDD.log
     */
    protected function readLogTail(): ?string
    {
        $file = WRITEPATH . 'logs/ai_http-' . date('Ymd') . '.log';
        if (!is_file($file)) {
            return null;
        }
        $size = filesize($file);
        $fp   = fopen($file, 'rb');
        if (!$fp) return null;
        $seek = max(0, $size - 1200);
        fseek($fp, $seek);
        $chunk = fread($fp, 1200);
        fclose($fp);
        return $chunk ?: null;
    }

    /**
     * اقتراح سريع بناءً على رسالة الخطأ الشائعة
     */
    protected function hintFromError(string $msg): ?string
    {
        $m = mb_strtolower($msg);
        if (str_contains($m, 'missing ai_gemini_api_key') || str_contains($m, 'missing ai_openai_api_key')) {
            return 'تحقق من المفاتيح في .env ثم امسح كاش البيئة إن وُجد.';
        }
        if (str_contains($m, 'not found') || str_contains($m, '404')) {
            return 'تحقق من اسم الموديل (مثلاً gemini-1.5-pro).';
        }
        if (str_contains($m, 'unauthorized') || str_contains($m, '401') || str_contains($m, 'invalid api key')) {
            return 'مفتاح API غير صحيح أو منتهي. استبدله وتأكد من الصيغة.';
        }
        if (str_contains($m, '429') || str_contains($m, 'rate')) {
            return 'تم تجاوز حدود المعدل (Rate Limit). جرّب لاحقًا أو خفّض التواتر.';
        }
        if (str_contains($m, 'ai did not return pure json')) {
            return 'النموذج أرجع نصًا غير JSON. جرّب إزالة/تعطيل response_mime_type مؤقتًا أو شدد التوجيه.';
        }
        if (str_contains($m, 'bad response') || str_contains($m, 'http')) {
            return 'راجع log_tail لترى الرد الخام من المزوّد (HTTP).';
        }
        return null;
    }
}

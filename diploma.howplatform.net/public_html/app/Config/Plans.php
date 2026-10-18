<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * إعدادات الاتصال بخدمة المحفظة المركزية Plans (Laravel).
 * المصادقة عبر رأس X-Api-Key. الاتصال داخلي عبر شبكة Docker (http://plans/api/v1).
 *
 * walletMode:
 *   off    = لا اتصال إطلاقًا (الافتراضي — سلوك النظام كما هو).
 *   shadow = يستدعي Plans بالتوازي ويسجّل، لكن يعمل على النتيجة المحلّية (صفر تأثير على المستخدم).
 *   api    = (لاحقًا) يعتمد على Plans كمصدر للحقيقة.
 */
class Plans extends BaseConfig
{
    /** عنوان أساس API المحفظة بدون / في النهاية. داخليًا: http://plans/api/v1 */
    public string $baseUrl = '';

    public string $apiKeyHeader = 'X-Api-Key';

    public string $apiKey = '';

    /** off | shadow | api */
    public string $walletMode = 'off';

    /** off | shadow | api — قراءة الصلاحيات (الخطّة/المزايا) من Plans */
    public string $entitlementsMode = 'off';

    /** off | api — كسب النقاط (تتابع/إحالة) عبر Plans */
    public string $earningMode = 'off';

    /** off | api — استبدال أكواد القسائم (الفاوتشر) عبر بطاقات Plans */
    public string $cardsMode = 'off';

    /** off | api — عدّادات استهلاك المزايا المحدودة بحصّة (فطين/التحميلات) عبر Plans */
    public string $usageMode = 'off';

    /** مهلة قصيرة جدًا: الاتصال داخلي (نفس شبكة Docker) ويجب ألا يبطئ مسار الصرف الحيّ.
     *  أسوأ حالة إضافية على الطلب ≈ 1.5s لو تعطّل Plans. */
    public float $timeout = 1.5;

    public float $connectTimeout = 1.0;

    /** التحقق من SSL — false للاتصال الداخلي عبر http */
    public bool $verify = false;

    public function __construct()
    {
        parent::__construct();

        $this->baseUrl        = rtrim((string) env('plans.baseURL', $this->baseUrl), '/');
        $this->apiKey         = (string) env('plans.apiKey', $this->apiKey);
        $this->apiKeyHeader   = (string) env('plans.apiKeyHeader', $this->apiKeyHeader);
        $this->walletMode       = (string) env('plans.wallet.mode', $this->walletMode);
        $this->entitlementsMode = (string) env('plans.entitlements.mode', $this->entitlementsMode);
        $this->earningMode      = (string) env('plans.earning.mode', $this->earningMode);
        $this->cardsMode        = (string) env('plans.cards.mode', $this->cardsMode);
        $this->usageMode        = (string) env('plans.usage.mode', $this->usageMode);
        $this->timeout        = (float) env('plans.timeout', $this->timeout);
        $this->connectTimeout = (float) env('plans.connectTimeout', $this->connectTimeout);
        $this->verify         = (bool) env('plans.verify', $this->verify);
    }
}

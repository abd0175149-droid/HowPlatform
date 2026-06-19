<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * إعدادات الاتصال بخدمة المستخدمين في السب-دومين (Laravel).
 * نعتمد Middleware: verify.apikey ⇒ سنرسل رأس X-Api-Key (مع دعم Authorization Bearer كخيار ثانٍ).
 */
class RemoteUsers extends BaseConfig
{
    /** 
     * العنوان الأساسي لنقطة lookup (بدون / في النهاية).
     * مثال: https://accounts.howplatform.net/api/lookup
     */
    public string $baseUrl = '';

    /** اسم رأس الـ API Key (حسب الميدلوير) */
    public string $apiKeyHeader = 'X-Api-Key';

    /** قيمة الـ API Key (يُفضل ضبطها من .env) */
    public string $apiKey = '';

    /** مهلة الاتصال بالثواني */
    public float $timeout = 5.0;

    /** مهلة فتح الاتصال بالثواني */
    public float $connectTimeout = 3.0;

    public function __construct()
    {
        parent::__construct();

        // السماح بالتهيئة عبر .env
        $this->baseUrl       = rtrim((string) env('remote.users.baseURL', $this->baseUrl), '/');
        $this->apiKey        = (string) env('remote.users.apiKey', $this->apiKey);
        $this->apiKeyHeader  = (string) env('remote.users.apiKeyHeader', $this->apiKeyHeader);
        $this->timeout       = (float) env('remote.users.timeout', $this->timeout);
        $this->connectTimeout= (float) env('remote.users.connectTimeout', $this->connectTimeout);
    }
}

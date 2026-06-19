<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Email extends BaseConfig
{
    // ------------- عناوين الإرسال الافتراضية -------------
    public string $fromEmail  = 'support@howplatform.net';   // ← عدِّل إذا لزم
    public string $fromName   = 'منصة كيف التعليمية';
    public string $recipients = '';                          // يملأ وقت الاستدعاء

    /* معلومات البروتوكول -------------------------------------------------- */
    public string $userAgent = 'CodeIgniter';
    public string $protocol  = 'mail';                       // استخدم SMTP

    /* إعدادات Sendmail (غير مُستخدمة مع SMTP) */
    public string $mailPath  = '/usr/sbin/sendmail';

    /* ---------- إعدادات SMTP (تُقرأ من البيئة EMAIL_* — لا أسرار في الكود) ---------- */
    public string $SMTPHost   = 'smtp.hostinger.com';        // ← مضيف SMTP (افتراضي، يُتجاوز من env)
    public string $SMTPUser   = 'support@howplatform.net';   // ← البريد المُرسِل (اسم المستخدم)
    public string $SMTPPass   = '';                          // ← يُملأ من EMAIL_PASS في .env
    public int    $SMTPPort   = 587;                         // 587 لـ TLS أو 465 لـ SSL
    public bool   $SMTPKeepAlive = false;
    public string $SMTPCrypto = 'tls';                       // 'tls' أو 'ssl' أو ''

    public function __construct()
    {
        parent::__construct();

        // اقرأ القيم من البيئة إن وُجدت (تبقى الأسرار خارج الكود/الـ Git)
        $env = static function (string $key, $default) {
            $v = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
            return ($v === false || $v === null || $v === '') ? $default : $v;
        };

        $this->fromEmail = (string) $env('EMAIL_FROM', $this->fromEmail);
        $this->fromName  = (string) $env('EMAIL_FROM_NAME', $this->fromName);
        $this->SMTPHost  = (string) $env('EMAIL_HOST', $this->SMTPHost);
        $this->SMTPUser  = (string) $env('EMAIL_USER', $this->SMTPUser);
        $this->SMTPPass  = (string) $env('EMAIL_PASS', '');
        $this->SMTPPort  = (int)    $env('EMAIL_PORT', $this->SMTPPort);
        $this->SMTPCrypto = (string) $env('EMAIL_CRYPTO', $this->SMTPCrypto);

        // لإرسال فعلي عبر SMTP يجب أن يكون البروتوكول 'smtp' وكلمة السر مضبوطة
        if ($this->SMTPPass !== '') {
            $this->protocol = 'smtp';
        }
    }

    /* ---------- خصائص عامّة ---------- */
    public bool   $wordWrap = true;
    public int    $wrapChars = 76;
    public string $mailType  = 'html';                       // نرسل HTML
    public string $charset   = 'UTF-8';
    public bool   $validate  = false;
    public int    $priority  = 3;                            // عادي
    public string $CRLF      = "\r\n";
    public string $newline   = "\r\n";
    public bool   $BCCBatchMode  = false;
    public int    $BCCBatchSize = 200;
    public bool   $DSN = false;
}

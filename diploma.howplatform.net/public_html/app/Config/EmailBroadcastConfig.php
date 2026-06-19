<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Email configuration specifically for Email Broadcast module
 * يعتمد على مفاتيح البيئة EMAIL_* المستخدمة في صفحة أخرى (المجرّبة)
 */
class EmailBroadcastConfig extends BaseConfig
{
    /** بروتوكول الإرسال */
    public string $protocol = 'smtp';

    /** SMTP */
    public string $SMTPHost   = '';
    public string $SMTPUser   = '';
    public string $SMTPPass   = '';
    public int    $SMTPPort   = 587;     // افتراضيًا مثل الإعدادات العاملة
    public string $SMTPCrypto = 'tls';   // افتراضيًا مثل الإعدادات العاملة

    /** تهيئة عامة */
    public string $mailType  = 'html';
    public string $charset   = 'utf-8';
    public string $newline   = "\r\n";
    public string $CRLF      = "\r\n";
    public string $userAgent = 'HowPlatformMailer';

    /** from */
    public string $fromEmail = '';
    public string $fromName  = 'منصّة كيف التعليمية';

    /** خيارات إضافية */
    public int  $SMTPTimeout   = 30;
    public bool $SMTPKeepAlive = false;
    public bool $validate      = true;
    public int  $priority      = 3;
    public bool $wordWrap      = true;
    public int  $wrapChars     = 76;

    public function __construct()
    {
        parent::__construct();

        // نقرأ من مفاتيح EMAIL_* (المجرّبة) مع دعم مفاتيح email.broadcast.* إن وُجدت
        $this->SMTPHost   = env('EMAIL_HOST',      env('email.broadcast.host',      $this->SMTPHost));
        $this->SMTPUser   = env('EMAIL_USER',      env('email.broadcast.username',  $this->SMTPUser));
        $this->SMTPPass   = env('EMAIL_PASS',      env('email.broadcast.password',  $this->SMTPPass));
        $this->SMTPPort   = (int) env('EMAIL_PORT',   env('email.broadcast.port',   $this->SMTPPort));
        $this->SMTPCrypto = env('EMAIL_CRYPTO',    env('email.broadcast.crypto',    $this->SMTPCrypto));
        $this->fromEmail  = env('EMAIL_FROM',      env('email.broadcast.fromEmail', $this->fromEmail));
        $this->fromName   = env('EMAIL_FROM_NAME', env('email.broadcast.fromName',  $this->fromName));

        // تأكيد البروتوكول SMTP (لا نسقط إلى mail() تلقائيًا حتى لا نحصل على "Email service not available")
        $this->protocol = 'smtp';

        // لوج توضيحي للاعتماديات (بدون كلمة المرور)
        log_message('info', sprintf(
            'EmailBroadcastConfig: host=%s port=%d crypto=%s from=%s',
            $this->SMTPHost, $this->SMTPPort, $this->SMTPCrypto, $this->fromEmail
        ));
    }

    public function toArray(): array
    {
        return [
            'protocol'      => $this->protocol,
            'SMTPHost'      => $this->SMTPHost,
            'SMTPUser'      => $this->SMTPUser,
            'SMTPPass'      => $this->SMTPPass,
            'SMTPPort'      => $this->SMTPPort,
            'SMTPCrypto'    => $this->SMTPCrypto,
            'SMTPTimeout'   => $this->SMTPTimeout,
            'SMTPKeepAlive' => $this->SMTPKeepAlive,
            'mailType'      => $this->mailType,
            'charset'       => $this->charset,
            'newline'       => $this->newline,
            'CRLF'          => $this->CRLF,
            'userAgent'     => $this->userAgent,
            'fromEmail'     => $this->fromEmail,
            'fromName'      => $this->fromName,
            'validate'      => $this->validate,
            'priority'      => $this->priority,
            'wordWrap'      => $this->wordWrap,
            'wrapChars'     => $this->wrapChars,
        ];
    }

    public function isSmtpConfigured(): bool
    {
        // بما أننا نعتمد إعدادات مؤكدة، نكتفي بالتحقق الأساسي
        return $this->protocol === 'smtp'
            && $this->SMTPHost !== ''
            && $this->SMTPUser !== ''
            && $this->SMTPPass !== ''
            && $this->fromEmail !== '';
    }
}

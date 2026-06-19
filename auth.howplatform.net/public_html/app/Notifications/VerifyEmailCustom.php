<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailCustom extends VerifyEmail
{
    protected function buildMailMessage($url)
    {
        return (new MailMessage)
            ->subject('تأكيد بريدك الإلكتروني - منصة كيف التعليمية')
            ->view('emails.verify', ['url' => $url]);
    }
}

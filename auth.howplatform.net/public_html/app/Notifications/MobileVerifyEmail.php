<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class MobileVerifyEmail extends BaseVerifyEmail
{
    public function __construct(private int $expireMinutes = 60)
    {
        //
    }

    protected function verificationUrl($notifiable): string
    {
        // نبني رابطًا موقّعًا نحو مسار الموبايل دون الحاجة لتسجيل الدخول
        return URL::temporarySignedRoute(
            'mobile.verification.callback',
            Carbon::now()->addMinutes($this->expireMinutes),
            [
                'id'   => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}

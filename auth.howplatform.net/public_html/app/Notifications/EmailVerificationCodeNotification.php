<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $code,
        public int $ttlSeconds // مدة الصلاحية بالثواني
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $minutes = max(1, (int) floor($this->ttlSeconds / 60));
        return (new MailMessage)
            ->subject('رمز التحقق من البريد - HOW Platform')
            ->greeting('مرحبًا ' . ($notifiable->name ?: 'عزيزي المستخدم'))
            ->line('رمز التحقق الخاص بك:')
            ->line('**' . $this->code . '**')
            ->line("صلاحية الرمز: {$minutes} دقيقة.")
            ->line('يرجى إدخال هذا الرمز في التطبيق لإتمام عملية التحقق.')
            ->salutation('شاكرين تعاونك');
    }
}

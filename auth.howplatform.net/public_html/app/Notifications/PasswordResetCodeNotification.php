<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $code,
        public int $ttlSeconds
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $minutes = max(1, (int) floor($this->ttlSeconds / 60));
        return (new MailMessage)
            ->subject('رمز إعادة تعيين كلمة المرور - HOW Platform')
            ->greeting('مرحبًا ' . ($notifiable->name ?: 'عزيزي المستخدم'))
            ->line('رمز إعادة التعيين الخاص بك:')
            ->line('**' . $this->code . '**')
            ->line("صلاحية الرمز: {$minutes} دقيقة.")
            ->line('أدخل هذا الرمز في التطبيق لإتمام عملية إعادة تعيين كلمة المرور.')
            ->salutation('مع التحية');
    }
}

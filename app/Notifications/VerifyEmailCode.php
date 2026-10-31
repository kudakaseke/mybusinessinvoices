<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailCode extends Notification
{
    public function __construct(public string $code) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirm your mybusinessinvoices email')
            ->line('Enter this six-digit code on the email confirmation page:')
            ->line($this->code)
            ->line('This code expires in 10 minutes. Do not share it with anyone.')
            ->line('After confirming your email, log in using your email address and password.');
    }
}

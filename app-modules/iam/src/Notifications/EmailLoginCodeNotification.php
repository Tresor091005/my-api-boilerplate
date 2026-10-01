<?php

declare(strict_types=1);

namespace Lahatre\Iam\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class EmailLoginCodeNotification extends Notification
{
    public function __construct(public readonly string $code) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(__('iam::notifications.email_login.subject'))
            ->line(__('iam::notifications.email_login.body', ['code' => $this->code]))
            ->line(__('iam::notifications.email_login.expiry'));
    }
}

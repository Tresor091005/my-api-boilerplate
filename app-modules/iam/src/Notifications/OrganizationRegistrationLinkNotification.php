<?php

declare(strict_types=1);

namespace Lahatre\Iam\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrganizationRegistrationLinkNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $url) {}

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
            ->subject(__('iam::notifications.organization_registration.subject'))
            ->line(__('iam::notifications.organization_registration.body'))
            ->action(__('iam::notifications.organization_registration.action'), $this->url);
    }
}

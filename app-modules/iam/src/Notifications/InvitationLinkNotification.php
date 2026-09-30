<?php

declare(strict_types=1);

namespace Lahatre\Iam\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class InvitationLinkNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $url, public readonly string $organizationName) {}

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
            ->subject(__('iam::notifications.invitation.subject'))
            ->line(__('iam::notifications.invitation.body', ['organization' => $this->organizationName]))
            ->action(__('iam::notifications.invitation.action'), $this->url);
    }
}

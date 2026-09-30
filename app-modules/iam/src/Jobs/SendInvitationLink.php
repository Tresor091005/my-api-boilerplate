<?php

declare(strict_types=1);

namespace Lahatre\Iam\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lahatre\Iam\Services\InvitationService;
use Lahatre\Shared\Enums\QueueName;

final class SendInvitationLink implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $invitationId,
        public readonly string $token,
    ) {
        $this->queue = QueueName::Email->value;
    }

    public function handle(InvitationService $invitations): void
    {
        $invitations->sendLink($this->organizationId, $this->invitationId, $this->token);
    }
}

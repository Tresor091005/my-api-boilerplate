<?php

declare(strict_types=1);

namespace Lahatre\Iam\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lahatre\Iam\Data\SessionData;
use Lahatre\Iam\Services\SessionService;
use Lahatre\Shared\Enums\QueueName;

final class EnrichSession implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $sessionId,
        public readonly string $tokenableType,
        public readonly string $tokenableId,
        public readonly SessionData $data,
    ) {
        $this->queue = QueueName::Default->value;
        $this->afterCommit();
    }

    public function handle(SessionService $sessions): void
    {
        $sessions->enrich($this->sessionId, $this->tokenableType, $this->tokenableId, $this->data);
    }
}

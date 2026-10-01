<?php

declare(strict_types=1);

namespace Lahatre\Iam\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lahatre\Iam\Services\EmailLoginService;
use Lahatre\Shared\Enums\QueueName;

final class SendLoginCode implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $email, public readonly string $challengeId, public readonly string $code)
    {
        $this->queue = QueueName::Email->value;
    }

    public function handle(EmailLoginService $login): void
    {
        $login->sendCode($this->email, $this->challengeId, $this->code);
    }
}

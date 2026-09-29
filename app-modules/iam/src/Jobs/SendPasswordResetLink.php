<?php

declare(strict_types=1);

namespace Lahatre\Iam\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Password;
use Lahatre\Shared\Enums\QueueName;

final class SendPasswordResetLink implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $email)
    {
        $this->queue = QueueName::Email->value;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Password::broker('users')->sendResetLink(['email' => $this->email]);
    }
}

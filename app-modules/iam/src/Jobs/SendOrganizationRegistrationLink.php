<?php

declare(strict_types=1);

namespace Lahatre\Iam\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lahatre\Iam\Services\OrganizationOnboardingService;
use Lahatre\Shared\Enums\QueueName;

final class SendOrganizationRegistrationLink implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $email)
    {
        $this->queue = QueueName::Email->value;
    }

    /**
     * Execute the job.
     */
    public function handle(OrganizationOnboardingService $onboarding): void
    {
        $onboarding->sendRegistrationToken($this->email);
    }
}

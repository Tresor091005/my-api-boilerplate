<?php

declare(strict_types=1);

namespace Lahatre\Sale\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Lahatre\Customer\Contracts\CustomerUsageInterface;
use Lahatre\Sale\Data\SaleTransitionContext;
use Lahatre\Sale\Enums\SaleEvent;
use Lahatre\Sale\Enums\SaleStatus;
use Lahatre\Sale\Services\SaleCustomerUsage;
use Lahatre\Sale\Services\SaleWorkflowService;
use Lahatre\Shared\StateMachine\StateMachine;

class SaleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('sale.state.machine', static fn (): StateMachine => self::stateMachine());
        $this->app->when(SaleWorkflowService::class)
            ->needs(StateMachine::class)
            ->give(static fn (Application $app): StateMachine => $app->make('sale.state.machine'));
    }

    public function boot(): void
    {
        $this->app->tag([SaleCustomerUsage::class], CustomerUsageInterface::SOURCES_TAG);
    }

    /** `completed` and `cancelling` come with later slices. */
    private static function stateMachine(): StateMachine
    {
        $approvalSettled = static fn (?object $context): bool => $context instanceof SaleTransitionContext && $context->approvalSettled();
        $approvalPending = static fn (?object $context): bool => $context instanceof SaleTransitionContext && !$context->approvalSettled();

        return StateMachine::define(SaleStatus::Draft, SaleEvent::class, SaleTransitionContext::class)
            ->from(SaleStatus::Draft)
            ->addTransition(SaleEvent::Validate, SaleStatus::Approved, $approvalSettled)
            ->addTransition(SaleEvent::Validate, SaleStatus::WaitingApproval, $approvalPending)
            ->addTransition(SaleEvent::Abandon, SaleStatus::Abandoned)
            ->from(SaleStatus::WaitingApproval)
            ->addTransition(SaleEvent::Approve, SaleStatus::Approved)
            ->addTransition(SaleEvent::Reject, SaleStatus::Rejected)
            ->addTransition(SaleEvent::Reopen, SaleStatus::Draft)
            ->addTransition(SaleEvent::Abandon, SaleStatus::Abandoned)
            ->from(SaleStatus::Rejected)
            ->addTransition(SaleEvent::Reopen, SaleStatus::Draft)
            ->addTransition(SaleEvent::Abandon, SaleStatus::Abandoned)
            ->from(SaleStatus::Approved)
            ->addTransition(SaleEvent::Reopen, SaleStatus::Draft)
            ->addTransition(SaleEvent::Confirm, SaleStatus::Active)
            ->addTransition(SaleEvent::Abandon, SaleStatus::Abandoned)
            // the system makes it `cancelled` once everything is given back
            ->from(SaleStatus::Active)
            ->addTransition(SaleEvent::Cancel, SaleStatus::Cancelling)
            ->from(SaleStatus::Completed)
            ->addTransition(SaleEvent::Cancel, SaleStatus::Cancelling)
            ->from(SaleStatus::Cancelling)
            ->addTransition(SaleEvent::Resume, SaleStatus::Active)
            ->freeze();
    }
}

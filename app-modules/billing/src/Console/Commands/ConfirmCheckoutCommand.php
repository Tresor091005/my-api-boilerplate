<?php

declare(strict_types=1);

namespace Lahatre\Billing\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Lahatre\Billing\Exceptions\CheckoutException;
use Lahatre\Billing\Models\Checkout;
use Lahatre\Billing\Services\CheckoutService;

final class ConfirmCheckoutCommand extends Command
{
    protected $signature = 'billing:checkout:confirm {checkout}';

    protected $description;

    public function __construct()
    {
        parent::__construct();
        $this->description = __('billing::console.checkout_confirm.description');
    }

    public function handle(CheckoutService $checkouts): int
    {
        $checkout = $this->findCheckout();

        if ($checkout === null) {
            $this->components->error(__('billing::console.checkout_confirm.not_found'));

            return self::FAILURE;
        }

        try {
            $subscription = $checkouts->confirm($checkout);
        } catch (CheckoutException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(__('billing::console.checkout_confirm.confirmed', [
            'ends_at' => $subscription->ends_at->toIso8601ZuluString(),
        ]));

        return self::SUCCESS;
    }

    private function findCheckout(): ?Checkout
    {
        try {
            $checkout = (new Checkout)->resolveRouteBinding($this->argument('checkout'));
        } catch (ModelNotFoundException) {
            return null;
        }

        return $checkout instanceof Checkout ? $checkout : null;
    }
}

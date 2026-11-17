<?php

declare(strict_types=1);

namespace Lahatre\Sale\Http\Resources;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Lahatre\Customer\Http\Resources\CustomerResource;
use Lahatre\Customer\Models\Customer;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;
use Lahatre\Sale\Enums\SaleMode;
use Lahatre\Sale\Models\Sale;

class SaleResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('number')
            ->field('status')
            ->field('version')
            ->field('customer_id')
            ->field('customer_name')
            ->field('currency_code')
            ->field('mode')
            ->field('invoicing', columns: ['mode'], computedBy: fn (Model $sale): ?string => self::mode($sale)?->invoicing()->value)
            ->field('delivery', columns: ['mode'], computedBy: fn (Model $sale): ?string => self::mode($sale)?->delivery()->value)
            ->field('required_payment_percent')
            ->field('payment_due_date', columns: ['payment_due_date'], computedBy: fn (Model $sale): ?string => self::date($sale, 'payment_due_date'))
            // the date that applies now: the sale's own, else its customer's; never stored
            ->field('due_date', columns: ['payment_due_date', 'required_payment_percent'], relations: ['customer'], computedBy: fn (Model $sale): ?string => self::dueDate($sale))
            ->field('approval_reasons')
            ->field('lines_base_total', columns: ['lines_base_total', 'currency_code'], computedBy: fn (Model $sale): string => self::decimal($sale, 'lines_base_total'))
            ->field('lines_tax_total', columns: ['lines_tax_total', 'currency_code'], computedBy: fn (Model $sale): string => self::decimal($sale, 'lines_tax_total'))
            ->field('lines_total', columns: ['lines_total', 'currency_code'], computedBy: fn (Model $sale): string => self::decimal($sale, 'lines_total'))
            ->field('total', columns: ['total', 'currency_code'], computedBy: fn (Model $sale): string => self::decimal($sale, 'total'))
            ->field('confirmed_at')
            ->field('cancelled_at')
            ->field('created_at')
            ->field('updated_at')
            // a sale holds at most 250 lines, all rendered
            ->include('lines', SaleLineResource::class, limit: 250)
            ->include('customer', CustomerResource::class)
            ->include('transitions', SaleTransitionResource::class)
            ->include('fulfillments', FulfillmentResource::class)
            ->include('holds', SaleHoldResource::class);
    }

    private static function mode(Model $sale): ?SaleMode
    {
        $mode = $sale->getAttribute('mode');

        return $mode instanceof SaleMode ? $mode : null;
    }

    private static function dueDate(Model $sale): ?string
    {
        /** @var Sale $sale */
        /** @var Customer|null $customer */
        $customer = $sale->customer;

        return $sale->dueDate($customer?->payment_due_date)?->toDateString();
    }

    private static function date(Model $sale, string $column): ?string
    {
        $date = $sale->getAttribute($column);

        return $date instanceof CarbonInterface ? $date->toDateString() : null;
    }

    private static function decimal(Model $sale, string $column): string
    {
        return app(MasterInterface::class)->fromMinor((string) $sale->getAttribute($column), (string) $sale->getAttribute('currency_code'));
    }
}

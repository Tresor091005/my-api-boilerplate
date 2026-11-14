<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Lahatre\Organization\Contracts\OrganizationInterface;
use Lahatre\Organization\Models\Organization;

uses(DatabaseTransactions::class);

it('gives the functional currency of an organization once per request', function (): void {
    $organization = Organization::factory()->create(['functional_currency_code' => 'XOF']);
    $currency = app(OrganizationInterface::class)->functionalCurrency($organization->id);

    DB::enableQueryLog();
    $again = app(OrganizationInterface::class)->functionalCurrency($organization->id);

    expect($currency->code)->toBe('XOF')
        ->and($currency->precision)->toBe(0)
        ->and($again)->toBe($currency)
        ->and(DB::getQueryLog())->toBe([]);
});

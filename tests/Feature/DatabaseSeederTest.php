<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('seeds the default organization with its functional currency', function (): void {
    expect(Artisan::call('db:seed', [
        '--class'          => DatabaseSeeder::class,
        '--no-interaction' => true,
    ]))->toBe(0);

    $this->assertDatabaseHas('organization_organizations', [
        'name'                     => 'kouri',
        'functional_currency_code' => 'XOF',
    ]);
});

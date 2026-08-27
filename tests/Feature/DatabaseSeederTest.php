<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the default organization with its functional currency', function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseHas('organization_organizations', [
        'name'                     => 'kouri',
        'functional_currency_code' => 'XOF',
    ]);
});

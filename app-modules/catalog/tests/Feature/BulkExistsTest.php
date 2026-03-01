<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Lahatre\Catalog\DTO\UnitSyncDTO;
use Lahatre\Catalog\Models\Unit;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('fails validation if one of the unit IDs does not exist', function (): void {
    Unit::factory()->create(['id' => '00000000-0000-0000-0000-000000000001', 'unit_group' => 'test-group']);

    $payload = [
        'unit_group' => 'Test Group',
        'units'      => [
            ['id' => '00000000-0000-0000-0000-000000000001', 'name' => 'Existing'],
            ['id' => '00000000-0000-0000-0000-000000000002', 'name' => 'Non-Existing'], // This will fail BulkExists
        ],
    ];

    $dto = new UnitSyncDTO($payload);
})->throws(ValidationException::class);

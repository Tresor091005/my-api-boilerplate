<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\ResponseSelection\Testing\AssertsBoundedQueries;
use Lahatre\ResponseSelection\Testing\AssertsResponseFieldIsolation;
use Lahatre\ResponseSelection\Tests\Support\Example\ExampleSchema;
use Lahatre\ResponseSelection\Tests\Support\Example\ExampleTransfer;
use Lahatre\ResponseSelection\Tests\Support\Example\ExampleTransferResource;
use Lahatre\ResponseSelection\Tests\Support\Example\ExampleUndeclaredColumnTransferResource;
use PHPUnit\Framework\AssertionFailedError;

uses(RefreshDatabase::class, AssertsBoundedQueries::class, AssertsResponseFieldIsolation::class);

beforeEach(function (): void {
    ExampleSchema::createTables();
    ExampleSchema::registerRoutes();
});

$prefix = '/'.ExampleSchema::PREFIX;

it('renders the selected fields and includes of a list through the middleware', function () use ($prefix): void {
    ExampleSchema::seedTransfers(2);
    $first = ExampleTransfer::query()->orderBy('id')->firstOrFail();

    $this->getJson("{$prefix}/transfers?fields=label,line_count&include=source_depot(code),lines(sku,unit_cost)")
        ->assertOk()
        ->assertJsonPath('data.0', [
            'id'           => $first->id,
            'label'        => 'TR-001 (closed)',
            'line_count'   => 2,
            'source_depot' => ['id' => $first->source_depot_id, 'code' => 'MAIN'],
            'lines'        => [
                ['id' => $first->lines()->firstOrFail()->id, 'sku' => 'SKU-0-1', 'unit_cost' => 12.5],
                ['id' => $first->lines()->skip(1)->firstOrFail()->id, 'sku' => 'SKU-0-2', 'unit_cost' => 25],
            ],
        ]);
});

it('answers 422 for an unknown name before the action runs', function () use ($prefix): void {
    ExampleSchema::seedTransfers(1);

    $this->getJson("{$prefix}/transfers?include=lines(colour)")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('include');
});

it('issues as many queries for six transfers as for two', function () use ($prefix): void {
    $this->assertBoundedQueries(
        fn (int $count, int $alreadySeeded) => ExampleSchema::seedTransfers($count, $alreadySeeded),
        fn () => $this->getJson("{$prefix}/transfers?include=source_depot.open_transfers,lines")->assertOk(),
    );
});

it('renders every field of a list and of a single transfer alone as in the full selection', function () use ($prefix): void {
    ExampleSchema::seedTransfers(4);
    $open = ExampleTransfer::query()->where('status', 'open')->orderBy('id')->firstOrFail();

    $this->assertFieldsRenderInIsolation("{$prefix}/transfers", ExampleTransferResource::class);
    $this->assertFieldsRenderInIsolation("{$prefix}/transfers/{$open->id}", ExampleTransferResource::class);
});

it('reveals an included field reading a column that only a sibling declares', function () use ($prefix): void {
    ExampleSchema::seedTransfers(2);

    expect(function () use ($prefix): void {
        $this->assertFieldsRenderInIsolation("{$prefix}/undeclared-column-transfers", ExampleUndeclaredColumnTransferResource::class);
    })->toThrow(AssertionFailedError::class, 'Field [label] of include [source_depot]');
});

it('reveals a closure querying once per row', function () use ($prefix): void {
    expect(function () use ($prefix): void {
        $this->assertBoundedQueries(
            fn (int $count, int $alreadySeeded) => ExampleSchema::seedTransfers($count, $alreadySeeded),
            fn () => $this->getJson("{$prefix}/querying-closure-transfers?fields=line_count")->assertOk(),
        );
    })->toThrow(AssertionFailedError::class, 'The query count depends on the number of rows');
});

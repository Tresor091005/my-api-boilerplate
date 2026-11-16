<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Lahatre\ResponseSelection\ApiResponse;
use Lahatre\ResponseSelection\Declaration\ResponseDeclaration;
use Lahatre\ResponseSelection\Enums\ResponseMode;
use Lahatre\ResponseSelection\Enums\ResponseShape;
use Lahatre\ResponseSelection\Rendering\ResponseContext;
use Lahatre\ResponseSelection\Selection\SelectionParser;
use Lahatre\ResponseSelection\Selection\SelectionValidator;
use Lahatre\ResponseSelection\Selection\SelectionViews;
use Lahatre\ResponseSelection\Testing\AssertsBoundedQueries;
use Lahatre\ResponseSelection\Testing\AssertsResponseFieldIsolation;
use Lahatre\ResponseSelection\Testing\RendersApiResources;
use Lahatre\ResponseSelection\Tests\Support\Example\ExampleDepot;
use Lahatre\ResponseSelection\Tests\Support\Example\ExampleDepotResource;
use Lahatre\ResponseSelection\Tests\Support\Example\ExampleSchema;
use Lahatre\ResponseSelection\Tests\Support\Example\ExampleSummaryTransferResource;
use Lahatre\ResponseSelection\Tests\Support\Example\ExampleTransfer;

uses(RefreshDatabase::class, AssertsBoundedQueries::class, AssertsResponseFieldIsolation::class, RendersApiResources::class);

beforeEach(function (): void {
    ExampleSchema::createTables();
    ExampleSchema::registerRoutes();
});

$prefix = '/'.ExampleSchema::PREFIX;

/**
 * The depot a transfer leaves from, as its `reference` view renders it.
 *
 * @return array<string, mixed>
 */
function viewTestDepotReference(int $depotId): array
{
    $depot = ExampleDepot::query()->findOrFail($depotId);

    return ['id' => $depot->id, 'name' => $depot->name, 'badge' => "[{$depot->code}] {$depot->name}"];
}

it('renders exactly the fields of the view of an include selected bare', function () use ($prefix): void {
    ExampleSchema::seedTransfers(2);
    $transfer = ExampleTransfer::query()->orderBy('id')->firstOrFail();

    $this->getJson("{$prefix}/summary-transfers/{$transfer->id}?fields=reference&include=source_depot")
        ->assertOk()
        ->assertExactJson(['data' => [
            'id'           => $transfer->id,
            'reference'    => 'TR-001',
            'source_depot' => viewTestDepotReference($transfer->source_depot_id),
        ]]);
});

it('answers 422 for a field list or a deeper path under an include fixed by a view', function (string $include) use ($prefix): void {
    ExampleSchema::seedTransfers(1);

    $this->getJson("{$prefix}/summary-transfers?include={$include}")
        ->assertUnprocessable()
        ->assertExactJson(['message' => 'The include "source_depot" has a fixed shape: select it without a field list or a deeper path.', 'errors' => [
            'include' => ['The include "source_depot" has a fixed shape: select it without a field list or a deeper path.'],
        ]]);
})->with(['source_depot(name)', 'source_depot.open_transfers', 'source_depot.open_transfers(reference)']);

it('renders the children a view lists', function () use ($prefix): void {
    ExampleSchema::seedTransfers(4);
    $transfer = ExampleTransfer::query()->orderBy('id')->firstOrFail();
    $open = ExampleTransfer::query()->where('source_depot_id', $transfer->source_depot_id)->where('status', 'open')->orderBy('id')->get();

    $this->getJson("{$prefix}/summary-transfers/{$transfer->id}?fields=id&include=origin")
        ->assertOk()
        ->assertExactJson(['data' => [
            'id'     => $transfer->id,
            'origin' => [
                'id'             => $transfer->source_depot_id,
                'name'           => 'Main depot',
                'open_transfers' => $open->map(fn (ExampleTransfer $sibling): array => ['id' => $sibling->id, 'reference' => $sibling->reference, 'status' => 'open'])->all(),
            ],
        ]]);
});

it('renders a fixed include under two parents of one response, each from its own node', function () use ($prefix): void {
    ExampleSchema::seedTransfers(4);
    $transfers = ExampleTransfer::query()->orderBy('id')->get();

    $response = $this->getJson("{$prefix}/summary-transfers?fields=id&include=source_depot,siblings(reference),siblings.source_depot")->assertOk();

    expect($response->json('data'))->toBe($transfers->map(fn (ExampleTransfer $transfer): array => [
        'id'           => $transfer->id,
        'source_depot' => viewTestDepotReference($transfer->source_depot_id),
        'siblings'     => $transfers->where('source_depot_id', $transfer->source_depot_id)->reject(fn (ExampleTransfer $sibling): bool => $sibling->is($transfer))->values()
            ->map(fn (ExampleTransfer $sibling): array => ['id' => $sibling->id, 'reference' => $sibling->reference, 'source_depot' => viewTestDepotReference($sibling->source_depot_id)])->all(),
    ])->all());

    $tree = (new SelectionParser)->parse('id', 'source_depot,siblings(reference),siblings.source_depot');
    app(SelectionValidator::class)->validate($tree->root(), ExampleSummaryTransferResource::class);
    $expanded = (new SelectionViews)->expand($tree, ExampleSummaryTransferResource::class)->root();
    $nested = $expanded->child('siblings')?->child('source_depot');

    expect($nested)->not->toBeNull()
        ->and($nested)->not->toBe($expanded->child('source_depot'))
        ->and($nested?->path)->toBe('siblings.source_depot')
        ->and($expanded->child('source_depot')?->path)->toBe('source_depot');
});

it('gives a bare entry for a fixed include inside a view the view of that include', function (): void {
    ExampleSchema::seedTransfers(2);
    $transfer = ExampleTransfer::query()->orderBy('id')->firstOrFail();
    $tree = (new SelectionViews)->tree(ExampleSummaryTransferResource::class, 'compact');

    app(ResponseContext::class)->start(ResponseMode::Resource, $tree, new ResponseDeclaration(ExampleSummaryTransferResource::class, ResponseShape::Item, [200]));

    try {
        $response = ApiResponse::item($transfer, ExampleSummaryTransferResource::class);
    } finally {
        app(ResponseContext::class)->reset();
    }

    expect($tree->toArray())->toBe([
        'fields'  => ['reference'],
        'include' => [
            'source_depot' => ['fields' => ['id', 'name', 'badge'], 'include' => []],
            'lines'        => ['fields' => ['sku'], 'include' => []],
        ],
    ])
        ->and($response instanceof JsonResponse ? $response->getData(true)['data'] : null)->toBe([
            'id'           => $transfer->id,
            'reference'    => 'TR-001',
            'source_depot' => viewTestDepotReference($transfer->source_depot_id),
            'lines'        => $transfer->lines()->get()->map(fn ($line): array => ['id' => $line->id, 'sku' => $line->sku])->all(),
        ]);
});

it('keeps every field of a Resource where no view fixes it', function () use ($prefix): void {
    ExampleSchema::seedTransfers(1);
    $transfer = ExampleTransfer::query()->firstOrFail();
    $depot = ExampleDepot::query()->findOrFail($transfer->source_depot_id);

    $this->getJson("{$prefix}/transfers/{$transfer->id}?fields=id&include=source_depot")
        ->assertOk()
        ->assertExactJson(['data' => ['id' => $transfer->id, 'source_depot' => ['id' => $depot->id, 'name' => $depot->name, 'code' => $depot->code]]]);

    expect($this->renderApiResource($depot, ExampleDepotResource::class))->toBe(['id' => $depot->id, 'name' => $depot->name, 'code' => $depot->code]);
});

it('renders every field alone as in the full selection, a fixed include as one block', function () use ($prefix): void {
    ExampleSchema::seedTransfers(4);
    $transfer = ExampleTransfer::query()->orderBy('id')->firstOrFail();

    $this->assertFieldsRenderInIsolation("{$prefix}/summary-transfers", ExampleSummaryTransferResource::class);
    $this->assertFieldsRenderInIsolation("{$prefix}/summary-transfers/{$transfer->id}", ExampleSummaryTransferResource::class);
});

it('issues as many queries for twelve transfers as for four through fixed includes', function () use ($prefix): void {
    // four rows already give each depot a sibling, so every level runs its queries
    $this->assertBoundedQueries(
        fn (int $count, int $alreadySeeded) => ExampleSchema::seedTransfers($count, $alreadySeeded),
        fn () => $this->getJson("{$prefix}/summary-transfers?include=source_depot,origin,lines,siblings.source_depot,siblings.origin")->assertOk(),
        small: 4,
        large: 12,
    );
});

it('renders a fixed include through renderApiResource() by the same rules', function (): void {
    ExampleSchema::seedTransfers(1);
    $transfer = ExampleTransfer::query()->firstOrFail();

    expect($this->renderApiResource($transfer, ExampleSummaryTransferResource::class, 'id', 'source_depot'))->toBe([
        'id'           => $transfer->id,
        'source_depot' => viewTestDepotReference($transfer->source_depot_id),
    ]);

    try {
        $this->renderApiResource($transfer, ExampleSummaryTransferResource::class, 'id', 'source_depot(name)');
        $this->fail('A field list under a fixed include was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['include' => ['The include "source_depot" has a fixed shape: select it without a field list or a deeper path.']]);
    }
});

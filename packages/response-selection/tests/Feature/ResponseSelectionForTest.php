<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Lahatre\ResponseSelection\ApiResponse;
use Lahatre\ResponseSelection\Exceptions\ResponseSelectionException;
use Lahatre\ResponseSelection\Testing\AssertsBoundedQueries;
use Lahatre\ResponseSelection\Tests\Support\Example\ExampleDepot;
use Lahatre\ResponseSelection\Tests\Support\Example\ExampleSchema;
use Lahatre\ResponseSelection\Tests\Support\Example\ExampleTransfer;
use Lahatre\ResponseSelection\Tests\Support\Example\ExampleTransferController;
use Lahatre\ResponseSelection\Tests\Support\Example\ExampleViewResolver;
use Lahatre\ResponseSelection\ViewResolver;
use Throwable;

uses(RefreshDatabase::class, AssertsBoundedQueries::class);

beforeEach(function (): void {
    ExampleSchema::createTables();
    ExampleSchema::registerRoutes();
    ExampleTransferController::$purposeSeen = null;
});

$prefix = '/'.ExampleSchema::PREFIX;

function bindExampleViewResolver(): void
{
    app()->singleton(ViewResolver::class, ExampleViewResolver::class);
}

it('ignores for when no ViewResolver is bound, and fields still works', function () use ($prefix): void {
    ExampleSchema::seedTransfers(1);
    $transfer = ExampleTransfer::query()->firstOrFail();

    $this->getJson("{$prefix}/transfers/{$transfer->id}?for=transfer.pick&fields=reference")
        ->assertOk()
        ->assertExactJson(['data' => ['id' => $transfer->id, 'reference' => 'TR-001']]);

    $this->getJson("{$prefix}/transfers/{$transfer->id}?for[]=a&response=resource")->assertOk();
});

it('fixes the response to the view the resolver names', function () use ($prefix): void {
    bindExampleViewResolver();
    ExampleSchema::seedTransfers(2);
    $this->getJson("{$prefix}/depots?for=depot.pick")
        ->assertOk()
        ->assertExactJson(['data' => ExampleDepot::query()->orderBy('id')->get()->map(fn (ExampleDepot $row): array => [
            'id' => $row->id, 'name' => $row->name, 'badge' => "[{$row->code}] {$row->name}",
        ])->all()]);
});

it('expands the fixed include of a view', function () use ($prefix): void {
    bindExampleViewResolver();
    ExampleSchema::seedTransfers(4);

    $data = $this->getJson("{$prefix}/depots?for=depot.overview")->assertOk()->json('data');

    foreach ($data as $row) {
        expect(array_keys($row))->toBe(['id', 'name', 'open_transfers']);

        foreach ($row['open_transfers'] as $transfer) {
            expect(array_keys($transfer))->toBe(['id', 'reference', 'status']);
        }
    }

    expect(collect($data)->pluck('open_transfers')->flatten(1))->not->toBeEmpty();
});

it('renders a view with fixed children of a transfer, lines and depot', function () use ($prefix): void {
    bindExampleViewResolver();
    ExampleSchema::seedTransfers(2);
    $transfer = ExampleTransfer::query()->orderBy('id')->firstOrFail();

    $data = $this->getJson("{$prefix}/summary-transfers/{$transfer->id}?for=transfer.pick")->assertOk()->json('data');

    expect(array_keys($data))->toBe(['id', 'reference', 'source_depot', 'lines'])
        ->and(array_keys($data['source_depot']))->toBe(['id', 'name', 'badge'])
        ->and(array_keys($data['lines'][0]))->toBe(['id', 'sku']);
});

it('keeps the queries of a view independent of the number of rows', function () use ($prefix): void {
    bindExampleViewResolver();

    $this->assertBoundedQueries(
        fn (int $count, int $alreadySeeded) => ExampleSchema::seedTransfers($count, $alreadySeeded),
        fn () => $this->getJson("{$prefix}/summary-transfers?for=transfer.pick")->assertOk(),
        small: 4,
        large: 12,
    );
});

it('gives ApiResponse::purpose() while the action runs and null once the request is over', function () use ($prefix): void {
    bindExampleViewResolver();
    ExampleSchema::seedTransfers(1);

    $this->getJson("{$prefix}/depots?for=depot.pick")->assertOk();

    expect(ExampleTransferController::$purposeSeen)->toBe('depot.pick')
        ->and(ApiResponse::purpose())->toBeNull();

    $this->getJson("{$prefix}/depots")->assertOk();

    expect(ExampleTransferController::$purposeSeen)->toBeNull();
});

it('answers 422 keyed by each selection parameter sent with for', function (string $query, array $keys) use ($prefix): void {
    bindExampleViewResolver();
    ExampleSchema::seedTransfers(1);

    $response = $this->getJson("{$prefix}/depots?for=depot.pick&{$query}")->assertUnprocessable();

    expect(array_keys($response->json('errors')))->toBe($keys);

    foreach ($keys as $key) {
        expect($response->json("errors.{$key}.0"))->toBe("The {$key} parameter cannot be used together with for.");
    }
})->with([
    'fields'  => ['fields=name', ['fields']],
    'include' => ['include=open_transfers', ['include']],
    'both'    => ['fields=name&include=open_transfers', ['fields', 'include']],
]);

it('answers 422 keyed for when the resolver does not accept the purpose', function () use ($prefix): void {
    bindExampleViewResolver();

    $this->getJson("{$prefix}/depots?for=depot.other")
        ->assertUnprocessable()
        ->assertExactJson(['message' => 'The purpose "depot.other" is not accepted here.', 'errors' => ['for' => ['The purpose "depot.other" is not accepted here.']]]);
});

it('checks the value of for before asking the resolver, and the resolver before the selection', function () use ($prefix): void {
    bindExampleViewResolver();

    $this->getJson("{$prefix}/depots?for=bad%20value&fields=name")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['for'])
        ->assertJsonMissingValidationErrors(['fields']);

    $this->getJson("{$prefix}/depots?for=depot.other&fields=name")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['for'])
        ->assertJsonMissingValidationErrors(['fields']);
});

it('answers 422 keyed for when the value of for is invalid', function (string $query) use ($prefix): void {
    bindExampleViewResolver();

    $this->getJson("{$prefix}/depots?{$query}")
        ->assertUnprocessable()
        ->assertExactJson(['message' => 'The for parameter must be a string of 1 to 100 letters, digits, underscores, dots or hyphens.', 'errors' => [
            'for' => ['The for parameter must be a string of 1 to 100 letters, digits, underscores, dots or hyphens.'],
        ]]);
})->with([
    'array'               => ['for[]=depot.pick'],
    'keyed array'         => ['for[a]=depot.pick'],
    'empty'               => ['for='],
    '101 characters'      => [fn () => 'for='.str_repeat('a', 101)],
    'forbidden character' => ['for=depot:pick'],
    'space'               => ['for=depot%20pick'],
]);

it('accepts a value of exactly 100 characters made of every allowed kind', function () use ($prefix): void {
    app()->bind(ViewResolver::class, fn (): ViewResolver => new class implements ViewResolver
    {
        public function viewFor(string $purpose, Request $request): ?string
        {
            return strlen($purpose) === 100 ? 'reference' : null;
        }
    });

    $this->getJson("{$prefix}/depots?for=".str_repeat('aZ9_.-', 16).'abcd')->assertOk();
});

it('answers 422 keyed for in mode none', function () use ($prefix): void {
    bindExampleViewResolver();

    $this->postJson("{$prefix}/depots?for=depot.pick")
        ->assertUnprocessable()
        ->assertExactJson(['message' => 'The for parameter cannot be used when the response mode is none.', 'errors' => [
            'for' => ['The for parameter cannot be used when the response mode is none.'],
        ]]);
});

it('accepts for on a write that answers with its resource', function () use ($prefix): void {
    bindExampleViewResolver();
    ExampleSchema::seedTransfers(1);
    $depot = ExampleDepot::query()->orderBy('id')->firstOrFail();

    $this->postJson("{$prefix}/depots?response=resource&for=depot.pick")
        ->assertCreated()
        ->assertExactJson(['data' => ['id' => $depot->id, 'name' => $depot->name, 'badge' => "[{$depot->code}] {$depot->name}"]]);

    $this->postJson("{$prefix}/depots?response=resource&for=depot.pick&fields=name")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['fields']);
});

it('raises a programming error, not a 422, when the view is not declared by the Resource', function () use ($prefix): void {
    bindExampleViewResolver();
    app()->instance(ExceptionHandler::class, new class implements ExceptionHandler
    {
        public function report(Throwable $e): void {}

        public function shouldReport(Throwable $e): bool
        {
            return false;
        }

        public function render($request, Throwable $e): never
        {
            throw $e;
        }

        public function renderForConsole($output, Throwable $e): void {}
    });

    $this->getJson("{$prefix}/depots?for=depot.undeclared");
})->throws(ResponseSelectionException::class);

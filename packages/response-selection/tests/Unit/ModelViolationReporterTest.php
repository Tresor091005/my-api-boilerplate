<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Unit;

use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\Exceptions;
use Lahatre\ResponseSelection\Rendering\ModelViolationReporter;

/**
 * Hydrated without a query: `exists` is true, so Eloquent applies its protections.
 */
final class ModelViolationTestLot extends Model
{
    public $timestamps = false;

    /** @return BelongsTo<ModelViolationTestLot, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'vendor_id');
    }

    public function violateLazyLoading(string $relation): mixed
    {
        return $this->handleLazyLoadingViolation($relation);
    }
}

beforeEach(function (): void {
    $this->preventedMissing = Model::preventsAccessingMissingAttributes();
    app()->instance(ModelViolationReporter::class, new ModelViolationReporter);
    Exceptions::fake();
});

afterEach(function (): void {
    Model::preventAccessingMissingAttributes($this->preventedMissing);
    Model::handleMissingAttributeViolationUsing(null);
    Model::handleLazyLoadingViolationUsing(null);
});

it('throws on a missing attribute without the production handlers', function (): void {
    Model::preventAccessingMissingAttributes();
    $lot = (new ModelViolationTestLot)->newFromBuilder(['id' => 1]);

    expect(fn () => $lot->cost)->toThrow(MissingAttributeException::class);
});

it('renders null for a missing attribute in production and reports it once per model class and key', function (): void {
    ModelViolationReporter::tolerate();
    Model::preventAccessingMissingAttributes();
    $first = (new ModelViolationTestLot)->newFromBuilder(['id' => 1]);
    $second = (new ModelViolationTestLot)->newFromBuilder(['id' => 2]);

    expect($first->cost)->toBeNull()
        ->and($second->cost)->toBeNull()
        ->and($first->quantity)->toBeNull();

    Exceptions::assertReportedCount(2);
    Exceptions::assertReported(fn (MissingAttributeException $violation): bool => str_contains($violation->getMessage(), '[cost]'));
    Exceptions::assertReported(fn (MissingAttributeException $violation): bool => str_contains($violation->getMessage(), '[quantity]'));
});

it('reports a lazy-loading violation once in production and lets the relation load', function (): void {
    ModelViolationReporter::tolerate();
    $lot = (new ModelViolationTestLot)->newFromBuilder(['id' => 1]);

    expect($lot->violateLazyLoading('vendor'))->toBeNull()
        ->and($lot->violateLazyLoading('vendor'))->toBeNull();

    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(LazyLoadingViolationException::class);
});

it('reports the same violation again in the next request', function (): void {
    $lot = (new ModelViolationTestLot)->newFromBuilder(['id' => 1]);

    (new ModelViolationReporter)->report(new LazyLoadingViolationException($lot, 'vendor'));
    (new ModelViolationReporter)->report(new LazyLoadingViolationException($lot, 'vendor'));

    Exceptions::assertReportedCount(2);
});

<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Lahatre\Tax\Contracts\TaxProfileUsageInterface;
use Lahatre\Tax\Services\CombinedTaxProfileUsage;

/** @param array<string, list<string>> $usage */
function profileUsageSource(array $usage): TaxProfileUsageInterface
{
    return new class($usage) implements TaxProfileUsageInterface
    {
        /** @param array<string, list<string>> $usage */
        public function __construct(private readonly array $usage) {}

        public function unitGroupIdsByProfile(string $organizationId, Collection $taxProfileIds): Collection
        {
            return collect($this->usage)->only($taxProfileIds->all())->map(fn (array $unitGroupIds): Collection => collect($unitGroupIds));
        }
    };
}

it('merges the usage reported by every source', function (): void {
    $usage = new CombinedTaxProfileUsage([
        profileUsageSource(['first' => ['mass', 'volume'], 'second' => ['mass']]),
        profileUsageSource(['first' => ['volume', 'length'], 'third' => ['count']]),
    ]);

    $merged = $usage->unitGroupIdsByProfile('organization', collect(['first', 'second', 'unused']))
        ->map(fn (Collection $unitGroupIds): array => $unitGroupIds->all())
        ->all();

    expect($merged)->toBe(['first' => ['mass', 'volume', 'length'], 'second' => ['mass']]);
});

it('reports no usage without a source', function (): void {
    expect(new CombinedTaxProfileUsage([])->unitGroupIdsByProfile('organization', collect(['first'])))->toBeEmpty();
});

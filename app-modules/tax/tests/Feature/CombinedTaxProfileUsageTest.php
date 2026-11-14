<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Lahatre\Tax\Contracts\TaxProfileUsageInterface;
use Lahatre\Tax\Services\CombinedTaxProfileUsage;

/**
 * @param  array<string, list<string>>  $usage  unit group ids by profile id
 * @param  array<string, list<list<string>>>  $includedTaxIdSets  sets of tax ids by profile id
 */
function profileUsageSource(array $usage, array $includedTaxIdSets = []): TaxProfileUsageInterface
{
    return new class($usage, $includedTaxIdSets) implements TaxProfileUsageInterface
    {
        /**
         * @param  array<string, list<string>>  $usage
         * @param  array<string, list<list<string>>>  $includedTaxIdSets
         */
        public function __construct(private readonly array $usage, private readonly array $includedTaxIdSets) {}

        public function unitGroupIdsByProfile(string $organizationId, Collection $taxProfileIds): Collection
        {
            return collect($this->usage)->only($taxProfileIds->all())->map(fn (array $unitGroupIds): Collection => collect($unitGroupIds));
        }

        public function includedTaxIdSetsByProfile(string $organizationId, Collection $taxProfileIds): Collection
        {
            return collect($this->includedTaxIdSets)->only($taxProfileIds->all())->map(fn (array $taxIdSets): Collection => collect($taxIdSets));
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

it('merges the sets of included taxes reported by every source, each set once whatever its order', function (): void {
    $usage = new CombinedTaxProfileUsage([
        profileUsageSource([], ['first' => [['vat'], ['eco', 'vat']], 'second' => [['vat']]]),
        profileUsageSource([], ['first' => [['vat', 'eco'], ['levy']], 'third' => [['vat']]]),
    ]);

    $merged = $usage->includedTaxIdSetsByProfile('organization', collect(['first', 'second', 'unused']))
        ->map(fn (Collection $taxIdSets): array => $taxIdSets->all())
        ->all();

    expect($merged)->toBe(['first' => [['vat'], ['eco', 'vat'], ['levy']], 'second' => [['vat']]]);
});

it('reports no usage without a source', function (): void {
    $usage = new CombinedTaxProfileUsage([]);

    expect($usage->unitGroupIdsByProfile('organization', collect(['first'])))->toBeEmpty()
        ->and($usage->includedTaxIdSetsByProfile('organization', collect(['first'])))->toBeEmpty();
});

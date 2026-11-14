<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Catalog\Assertions\PriceLevelAssertion;
use Lahatre\Catalog\Data\PriceLevelData;
use Lahatre\Catalog\Data\PriceLevelFilterData;
use Lahatre\Catalog\Exceptions\PriceLevelException;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Organization\Contracts\OrganizationInterface;

use function Lahatre\Shared\Data\required;

/**
 * The price levels an organization sells at, each in one currency the
 * organization has enabled. Names are kept normalized (trimmed, single-spaced,
 * lowercase) and unique within the organization and currency, so "retail" may
 * exist in XOF and in EUR.
 */
final readonly class PriceLevelService
{
    public function __construct(
        private PriceLevelAssertion $priceLevelAssertion,
        private OrganizationInterface $organizationInterface,
    ) {}

    public function paginate(PriceLevelFilterData $filters): CursorPaginator
    {
        $query = PriceLevel::query()
            ->where('organization_id', currentOrganizationId())
            ->when($filters->currencyCode, fn (Builder $query, string $currencyCode): Builder => $query->where('currency_code', Str::toUpper($currencyCode)))
            ->when($filters->name, fn (Builder $query, string $name): Builder => $query->where('name', 'like', Str::normalize($name).'%'));

        return stableCursorPaginate($query, $filters);
    }

    public function retrieve(PriceLevel $priceLevel): PriceLevel
    {
        return $priceLevel;
    }

    /**
     * @throws PriceLevelException If the currency is not enabled for the organization, or a level of this currency already has the name.
     */
    public function create(PriceLevelData $data): PriceLevel
    {
        $organizationId = currentOrganizationId();
        $name = Str::normalize($data->name);
        $currencyCode = Str::toUpper(required($data->currencyCode));

        if (!$this->organizationInterface->isCurrencyEnabled($organizationId, $currencyCode)) {
            throw PriceLevelException::currencyNotEnabled($currencyCode);
        }

        try {
            return PriceLevel::query()->create([
                'organization_id' => $organizationId,
                'name'            => $name,
                'currency_code'   => $currencyCode,
            ]);
        } catch (QueryException $exception) {
            throw $this->nameTakenOr($exception, $name, $currencyCode);
        }
    }

    /**
     * Renames the level; its currency never changes. Prices at this level
     * follow, past sales keep the name they copied.
     *
     * @throws PriceLevelException If a level of the same currency already has the name.
     */
    public function update(PriceLevel $priceLevel, PriceLevelData $data): PriceLevel
    {
        $name = Str::normalize($data->name);

        try {
            return DB::transaction(function () use ($priceLevel, $name): PriceLevel {
                $lockedLevel = $this->lock($priceLevel);
                $lockedLevel->name = $name;
                $lockedLevel->save();

                return $lockedLevel;
            });
        } catch (QueryException $exception) {
            throw $this->nameTakenOr($exception, $name, $priceLevel->currency_code);
        }
    }

    /**
     * Deletes a level no item is priced at; the lock keeps prices from being added to it meanwhile.
     *
     * @throws PriceLevelException If an item still has a price at this level.
     */
    public function delete(PriceLevel $priceLevel): void
    {
        DB::transaction(function () use ($priceLevel): void {
            $lockedLevel = $this->lock($priceLevel);
            $this->priceLevelAssertion->assertCanBeDeleted($lockedLevel);
            $lockedLevel->delete();
        });
    }

    private function lock(PriceLevel $priceLevel): PriceLevel
    {
        return PriceLevel::query()
            ->where('organization_id', currentOrganizationId())
            ->whereKey($priceLevel->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function nameTakenOr(QueryException $exception, string $name, string $currencyCode): QueryException|PriceLevelException
    {
        return $exception->getCode() === '23505' ? PriceLevelException::nameTaken($name, $currencyCode) : $exception;
    }
}

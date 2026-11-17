<?php

declare(strict_types=1);

namespace Lahatre\Sale\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Organization\Contracts\OrganizationInterface;
use Lahatre\Sale\Assertions\SaleAssertion;
use Lahatre\Sale\Data\SaleData;
use Lahatre\Sale\Data\SaleFilterData;
use Lahatre\Sale\Enums\SaleEvent;
use Lahatre\Sale\Enums\SaleStatus;
use Lahatre\Sale\Exceptions\SaleException;
use Lahatre\Sale\Models\Sale;
use Lahatre\Sale\Models\SaleLine;
use Lahatre\Sale\Models\SaleTransition;
use Lahatre\Shared\Services\BusinessNumberService;
use Lahatre\Tax\Exceptions\TaxException;

final readonly class SaleService
{
    public function __construct(
        private SaleAssertion $saleAssertion,
        private SaleCalculationService $saleCalculationService,
        private OrganizationInterface $organizationInterface,
    ) {}

    public function paginate(SaleFilterData $filters): CursorPaginator
    {
        $organizationId = currentOrganizationId();
        // a day starts at midnight in the organization's timezone
        $timezone = $this->organizationInterface->today($organizationId)->getTimezone();
        $dayStart = fn (string $date): CarbonImmutable => CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);

        $query = Sale::query()
            ->where('organization_id', $organizationId)
            ->when($filters->fromDate, fn (Builder $query, string $date): Builder => $query->where('created_at', '>=', $dayStart($date)->utc()))
            ->when($filters->toDate, fn (Builder $query, string $date): Builder => $query->where('created_at', '<', $dayStart($date)->addDay()->utc()))
            ->when($filters->statuses !== [], fn (Builder $query): Builder => $query->whereIn('status', $filters->statuses))
            ->when($filters->customerId, fn (Builder $query, string $customerId): Builder => $query->where('customer_id', $customerId))
            ->when($filters->number, fn (Builder $query, string $number): Builder => $query->where(
                'number',
                'like',
                addcslashes(Str::toUpper(Str::sanitize($number)), '%_\\').'%',
            ));

        return stableCursorPaginate($query, $filters);
    }

    public function retrieve(Sale $sale): Sale
    {
        return $sale;
    }

    /**
     * Creates a draft with its number and a `create` transition, in one transaction.
     *
     * @throws SaleException If the customer, the price level, a credit to the walk-in customer or a line is refused.
     * @throws TaxException If the taxes of a line cannot be calculated.
     */
    public function create(SaleData $data): Sale
    {
        $organizationId = currentOrganizationId();
        $memberId = $this->currentMemberId();

        return DB::transaction(function () use ($data, $organizationId, $memberId): Sale {
            $draft = $this->saleCalculationService->draft($data, $organizationId);

            $sale = Sale::query()->create([
                ...$draft['header'],
                'organization_id' => $organizationId,
                'number'          => BusinessNumberService::next('sale'),
                'status'          => SaleStatus::Draft,
                'version'         => 1,
            ]);
            $this->saleCalculationService->insertLines($sale, $draft['lines']);
            SaleTransition::query()->create([
                'organization_id' => $organizationId,
                'sale_id'         => $sale->id,
                'event'           => SaleEvent::Create,
                'from_status'     => null,
                'to_status'       => SaleStatus::Draft,
                'member_id'       => $memberId,
            ]);

            return $sale;
        });
    }

    /**
     * Replaces a draft whole and bumps its version, with the sale row locked.
     *
     * @throws SaleException If the sale is not a draft, its version changed since it was read, or something sent is refused.
     * @throws TaxException If the taxes of a line cannot be calculated.
     */
    public function replace(Sale $sale, SaleData $data): Sale
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(function () use ($sale, $data, $organizationId): Sale {
            /** @var Sale $lockedSale */
            $lockedSale = Sale::query()
                ->where('organization_id', $organizationId)
                ->whereKey($sale->id)
                ->lockForUpdate()
                ->firstOrFail();
            $this->saleAssertion->assertDraftReplaceable($lockedSale, (int) $data->version);

            $draft = $this->saleCalculationService->draft($data, $organizationId);

            $lockedSale->fill([...$draft['header'], 'version' => $lockedSale->version + 1]);
            $lockedSale->save();
            SaleLine::query()
                ->where('organization_id', $organizationId)
                ->where('sale_id', $lockedSale->id)
                ->delete();
            $this->saleCalculationService->insertLines($lockedSale, $draft['lines']);

            return $lockedSale;
        });
    }

    private function currentMemberId(): ?string
    {
        $memberId = authContext()->member()?->getKey();

        return $memberId === null ? null : (string) $memberId;
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Organization\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Master\Models\Currency;
use Lahatre\Organization\Data\ExchangeRateData;
use Lahatre\Organization\Data\ExchangeRateFilterData;
use Lahatre\Organization\Data\ExchangeRateUpdateData;
use Lahatre\Organization\Enums\ExchangeRateContext;
use Lahatre\Organization\Exceptions\OrganizationException;
use Lahatre\Organization\Models\ExchangeRate;
use Lahatre\Organization\Models\Organization;
use Lahatre\Organization\Models\OrganizationSetting;

class ExchangeRateService
{
    public function __construct(
        protected MasterInterface $masterInterface,
    ) {}

    public function paginate(ExchangeRateFilterData $filters): CursorPaginator
    {
        $organization = $this->currentOrganization();
        $query = ExchangeRate::query()
            ->where('organization_id', $organization->id)
            ->when($filters->sourceCurrencyCode, fn (Builder $query, string $code): Builder => $query->where('source_currency_code', $code))
            ->when($filters->targetCurrencyCode, fn (Builder $query, string $code): Builder => $query->where('target_currency_code', $code))
            ->when($filters->context, fn (Builder $query, ExchangeRateContext $context): Builder => $query->where('context', $context->value))
            ->when($filters->effectiveFrom, fn (Builder $query, CarbonImmutable $date): Builder => $query->where('effective_at', '>=', $date->startOfDay()))
            ->when($filters->effectiveTo, fn (Builder $query, CarbonImmutable $date): Builder => $query->where('effective_at', '<=', $date->endOfDay()))
            ->orderByDesc('effective_at')
            ->orderByDesc('id');

        return $query->cursorPaginate($filters->perPage, ['*'], 'cursor', $filters->cursor);
    }

    public function retrieve(ExchangeRate $exchangeRate): ExchangeRate
    {
        $organization = $this->currentOrganization();
        $this->assertBelongsToOrganization($organization, $exchangeRate);

        return $exchangeRate;
    }

    public function create(ExchangeRateData $data): ExchangeRate
    {
        $organization = $this->currentOrganization();
        $sourceCode = Str::toUpper($data->sourceCurrencyCode);
        $targetCode = Str::toUpper($data->targetCurrencyCode);

        $this->assertCurrenciesEnabled($organization, [$sourceCode, $targetCode]);
        $this->assertCurrencyPair($sourceCode, $targetCode);
        $this->assertRate($data->rate);

        try {
            return DB::transaction(fn (): ExchangeRate => ExchangeRate::create([
                'organization_id'      => $organization->id,
                'source_currency_code' => $sourceCode,
                'target_currency_code' => $targetCode,
                'context'              => $data->context->value,
                'effective_at'         => $data->effectiveAt,
                'rate'                 => $data->rate,
            ]));
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                throw OrganizationException::duplicateRate();
            }

            throw $exception;
        }
    }

    public function update(
        ExchangeRate $exchangeRate,
        ExchangeRateUpdateData $data,
    ): ExchangeRate {
        $organization = $this->currentOrganization();
        $this->assertBelongsToOrganization($organization, $exchangeRate);
        $this->assertRate($data->rate);

        try {
            return DB::transaction(function () use ($organization, $exchangeRate, $data): ExchangeRate {
                $lockedRate = ExchangeRate::query()
                    ->where('organization_id', $organization->id)
                    ->whereKey($exchangeRate->id)
                    ->lockForUpdate()
                    ->first();

                if (!$lockedRate instanceof ExchangeRate) {
                    throw OrganizationException::organizationMismatch();
                }

                $this->assertFutureRate($lockedRate);
                $this->assertFutureEffectiveAt($data->effectiveAt);
                $lockedRate->update([
                    'rate'         => $data->rate,
                    'effective_at' => $data->effectiveAt,
                ]);

                return $lockedRate->refresh();
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                throw OrganizationException::duplicateRate();
            }

            throw $exception;
        }
    }

    public function delete(ExchangeRate $exchangeRate): void
    {
        $organization = $this->currentOrganization();
        $this->assertBelongsToOrganization($organization, $exchangeRate);

        DB::transaction(function () use ($organization, $exchangeRate): void {
            $lockedRate = ExchangeRate::query()
                ->where('organization_id', $organization->id)
                ->whereKey($exchangeRate->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedRate instanceof ExchangeRate) {
                throw OrganizationException::organizationMismatch();
            }

            $this->assertFutureRate($lockedRate);
            $lockedRate->delete();
        });
    }

    /**
     * Resolve a transaction amount into the organization's functional currency.
     *
     * @return array{currency_code: string, functional_currency_code: string, amount_in_transaction_currency: string, amount_in_functional_currency: string, exchange_rate: string, exchange_rate_effective_at: CarbonImmutable|null, requested_exchange_context: string, exchange_context: string}
     */
    public function resolveMinorConversion(
        string $amountMinor,
        string $transactionCurrencyCode,
        ExchangeRateContext $context = ExchangeRateContext::Default,
        ?CarbonImmutable $at = null,
    ): array {
        $organization = $this->currentOrganization();
        $sourceCode = Str::toUpper($transactionCurrencyCode);
        $targetCode = Str::toUpper($organization->functional_currency_code);
        $normalizedContext = $context->value;
        $this->assertCurrenciesEnabled($organization, [$sourceCode, $targetCode]);
        $currencies = $this->currencies([$sourceCode, $targetCode]);

        if ($sourceCode === $targetCode) {
            return [
                'currency_code'                  => $sourceCode,
                'functional_currency_code'       => $targetCode,
                'amount_in_transaction_currency' => $amountMinor,
                'amount_in_functional_currency'  => $amountMinor,
                'exchange_rate'                  => '1',
                'exchange_rate_effective_at'     => null,
                'requested_exchange_context'     => $normalizedContext,
                'exchange_context'               => $normalizedContext,
            ];
        }

        $source = $currencies->get($sourceCode);
        $target = $currencies->get($targetCode);
        $rateQuery = ExchangeRate::query()
            ->where('organization_id', $organization->id)
            ->where('source_currency_code', $sourceCode)
            ->where('target_currency_code', $targetCode)
            ->where('effective_at', '<=', $at ?? now())
            ->orderByDesc('effective_at')
            ->orderByDesc('id');
        $rate = (clone $rateQuery)
            ->where('context', $normalizedContext)
            ->first();
        $resolvedContext = $normalizedContext;

        if (!$rate instanceof ExchangeRate) {
            $rate = (clone $rateQuery)
                ->where('context', ExchangeRateContext::Default->value)
                ->first();
            $resolvedContext = ExchangeRateContext::Default->value;
        }

        if (!$rate instanceof ExchangeRate) {
            throw OrganizationException::rateUnavailable($sourceCode, $targetCode, $normalizedContext);
        }

        $sourceMajor = bcdiv($amountMinor, bcpow('10', (string) $source->precision, 0), 24);
        $targetMajor = bcmul($sourceMajor, (string) $rate->rate, 24);
        $targetMinor = bcmul($targetMajor, bcpow('10', (string) $target->precision, 0), 12);

        return [
            'currency_code'                  => $sourceCode,
            'functional_currency_code'       => $targetCode,
            'amount_in_transaction_currency' => $amountMinor,
            'amount_in_functional_currency'  => $this->roundHalfUp($targetMinor),
            'exchange_rate'                  => (string) $rate->rate,
            'exchange_rate_effective_at'     => $rate->effective_at,
            'requested_exchange_context'     => $normalizedContext,
            'exchange_context'               => $resolvedContext,
        ];
    }

    /**
     * @param  array<int, string>  $codes
     * @return Collection<string, Currency>
     */
    private function currencies(array $codes): Collection
    {
        $currencies = $this->masterInterface->currencies(collect($codes));

        foreach ($codes as $code) {
            if (!$currencies->has($code)) {
                throw OrganizationException::currencyNotFound($code);
            }
        }

        return $currencies;
    }

    private function currentOrganization(): Organization
    {
        /** @var Organization $organization */
        $organization = Organization::query()->findOrFail(currentOrganizationId());

        return $organization;
    }

    private function assertCurrencyPair(string $sourceCode, string $targetCode): void
    {
        if ($sourceCode === $targetCode) {
            throw OrganizationException::sameCurrencyPair();
        }

        $this->currencies([$sourceCode, $targetCode]);
    }

    /** @param array<int, string> $codes */
    private function assertCurrenciesEnabled(Organization $organization, array $codes): void
    {
        $settings = $organization->settings()->first();
        $enabledCurrencies = $settings instanceof OrganizationSetting
            ? $settings->enable_currencies
            : [$organization->functional_currency_code];

        foreach ($codes as $code) {
            if (!in_array($code, $enabledCurrencies, true)) {
                throw OrganizationException::currencyNotEnabled($code);
            }
        }
    }

    private function assertRate(string $rate): void
    {
        if (!preg_match('/^[0-9]+(?:\.[0-9]{1,12})?$/', $rate) || bccomp($rate, '0', 12) <= 0) {
            throw OrganizationException::invalidRate();
        }
    }

    private function assertBelongsToOrganization(Organization $organization, ExchangeRate $exchangeRate): void
    {
        if ($exchangeRate->organization_id !== $organization->id) {
            throw OrganizationException::organizationMismatch();
        }
    }

    private function assertFutureRate(ExchangeRate $exchangeRate): void
    {
        if ($exchangeRate->effective_at->lessThanOrEqualTo(now())) {
            throw OrganizationException::effectiveRateImmutable();
        }
    }

    private function assertFutureEffectiveAt(CarbonImmutable $effectiveAt): void
    {
        if ($effectiveAt->lessThanOrEqualTo(now())) {
            throw OrganizationException::effectiveRateImmutable();
        }
    }

    private function roundHalfUp(string $amount): string
    {
        return bcadd($amount, str_starts_with($amount, '-') ? '-0.5' : '0.5', 0);
    }
}

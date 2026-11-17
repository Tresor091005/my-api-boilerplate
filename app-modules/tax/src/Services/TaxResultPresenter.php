<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services;

use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Shared\Support\Decimal;
use Lahatre\Tax\Enums\TaxCalculationType;
use Lahatre\Tax\Enums\TaxFixedMode;
use Lahatre\Tax\ViewData\TaxResultViewData;
use Lahatre\Tax\ViewData\TaxSimulationViewData;

/**
 * Renders a tax result, kept in minor units, with its amounts as decimals of
 * its currency, like every other amount of the API. Rates and quantities are
 * left as they are; a per-unit value may be finer than the currency and keeps
 * its decimals.
 *
 * @phpstan-import-type TaxResult from TaxResultViewData
 * @phpstan-import-type TaxSimulation from TaxSimulationViewData
 */
final readonly class TaxResultPresenter
{
    private const array RESULT_AMOUNTS = ['amount', 'base_amount', 'included_tax_amount', 'added_tax_amount', 'total_tax_amount', 'total_amount'];

    private const array TAX_AMOUNTS = ['input_base_amount', 'dependency_amount', 'taxable_base_amount', 'rounding_adjustment', 'tax_amount'];

    private const array BRACKET_AMOUNTS = ['base_min', 'base_max', 'tax_amount_min', 'tax_amount_max', 'taxable_portion', 'tax_amount'];

    public function __construct(
        private MasterInterface $masterInterface,
    ) {}

    /**
     * @param  TaxResult  $taxResult
     * @return TaxResult
     */
    public function present(array $taxResult): array
    {
        $currencyCode = $taxResult['currency_code'];
        $taxResult = $this->decimals($taxResult, self::RESULT_AMOUNTS, $currencyCode);
        $taxResult['taxes'] = array_map(function (array $tax) use ($currencyCode): array {
            $tax = $this->decimals($tax, self::TAX_AMOUNTS, $currencyCode);
            $tax['brackets'] = array_map(fn (array $bracket): array => [
                ...$this->decimals($bracket, self::BRACKET_AMOUNTS, $currencyCode),
                'value' => $this->bracketValue($tax, $bracket['value'], $currencyCode),
            ], $tax['brackets']);
            $tax['dependencies'] = array_map(fn (array $dependency): array => $this->decimals($dependency, ['tax_amount'], $currencyCode), $tax['dependencies']);

            return $tax;
        }, $taxResult['taxes']);

        return $taxResult;
    }

    /** @return TaxSimulation */
    public function presentSimulation(TaxSimulationViewData $simulation, string $currencyCode): array
    {
        $presented = $simulation->toArray();

        return [
            'lines'            => array_map($this->present(...), $presented['lines']),
            'total_tax_amount' => $this->masterInterface->fromMinor($presented['total_tax_amount'], $currencyCode),
            'total_amount'     => $this->masterInterface->fromMinor($presented['total_amount'], $currencyCode),
        ];
    }

    /**
     * @template TValues of array<string, mixed>
     *
     * @param  TValues  $values
     * @param  list<string>  $keys
     * @return TValues
     */
    private function decimals(array $values, array $keys, string $currencyCode): array
    {
        foreach ($keys as $key) {
            if (isset($values[$key])) {
                $values[$key] = $this->masterInterface->fromMinor((string) $values[$key], $currencyCode);
            }
        }

        return $values;
    }

    /** @param array<string, mixed> $tax */
    private function bracketValue(array $tax, string $value, string $currencyCode): string
    {
        if ($tax['calculation_type'] === TaxCalculationType::Percentage->value) {
            return $value;
        }

        if ($tax['fixed_mode'] === TaxFixedMode::PerTaxUnit->value) {
            $precision = $this->masterInterface->currency($currencyCode)->precision;

            return Decimal::normalize(bcdiv($value, bcpow('10', (string) $precision), Decimal::places($value) + $precision));
        }

        return $this->masterInterface->fromMinor($value, $currencyCode);
    }
}

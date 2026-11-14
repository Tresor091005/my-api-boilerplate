<?php

declare(strict_types=1);

use Lahatre\Tax\Enums\TaxBaseType;
use Lahatre\Tax\Enums\TaxBracketMode;
use Lahatre\Tax\Enums\TaxCalculationType;
use Lahatre\Tax\Enums\TaxDependencyOperator;
use Lahatre\Tax\Enums\TaxFixedMode;
use Lahatre\Tax\Enums\TaxKind;
use Lahatre\Tax\Enums\TaxRoundingMode;
use Lahatre\Tax\Enums\TaxTreatment;
use Lahatre\Tax\Exceptions\TaxException;
use Lahatre\Tax\Services\Engine\CompiledBracket;
use Lahatre\Tax\Services\Engine\CompiledDependency;
use Lahatre\Tax\Services\Engine\CompiledTax;
use Lahatre\Tax\Services\Engine\CompiledTaxProfile;
use Lahatre\Tax\Services\Engine\IncludedTaxSolver;
use Lahatre\Tax\Services\Engine\TaxCalculator;
use Lahatre\Tax\ViewData\TaxLineResultViewData;

/**
 * A compiled tax built by hand: a single open bracket at `rate` percent unless
 * the overrides say otherwise. Its id is its code.
 *
 * @param  array<string, mixed>  $overrides
 */
function solverTax(string $code, string $rate = '18', array $overrides = []): CompiledTax
{
    return new CompiledTax(
        id: $code,
        code: $code,
        name: $code,
        kind: $overrides['kind'] ?? TaxKind::Other,
        treatment: $overrides['treatment'] ?? TaxTreatment::Standard,
        calculationType: $overrides['calculationType'] ?? TaxCalculationType::Percentage,
        fixedMode: $overrides['fixedMode'] ?? null,
        taxUnitCode: null,
        taxUnitGroupId: null,
        baseType: $overrides['baseType'] ?? TaxBaseType::AmountWithDependencies,
        isProgressive: $overrides['isProgressive'] ?? false,
        bracketMode: $overrides['bracketMode'] ?? null,
        roundingMode: $overrides['roundingMode'] ?? TaxRoundingMode::HalfUp,
        isActive: true,
        brackets: $overrides['brackets'] ?? [new CompiledBracket('0', null, $rate, null, null)],
        dependencies: array_map(
            fn (string $dependencyCode): CompiledDependency => new CompiledDependency($dependencyCode, TaxDependencyOperator::Add),
            $overrides['dependsOn'] ?? [],
        ),
    );
}

/** @param list<CompiledTax> $taxes in calculation order */
function solverProfile(array $taxes): CompiledTaxProfile
{
    return new CompiledTaxProfile(id: 'profile', code: 'PROFILE', isActive: true, taxes: $taxes);
}

/**
 * Solves an amount and calculates every tax on the base found.
 *
 * @param  list<string>  $includedCodes
 * @return array{base: string, adjustments: array<string, string>, taxes: array<string, TaxLineResultViewData>}
 */
function solveIncluded(CompiledTaxProfile $profile, string $amount, array $includedCodes): array
{
    $calculator = new TaxCalculator;
    $includedTaxes = array_values(array_filter($profile->taxes, fn (CompiledTax $tax): bool => in_array($tax->id, $includedCodes, true)));
    $solution = (new IncludedTaxSolver($calculator))->solve($profile, $amount, $includedTaxes, []);

    return [
        'base'        => $solution->baseAmount,
        'adjustments' => $solution->roundingAdjustments,
        'taxes'       => $calculator->calculate($profile, $solution->baseAmount, [], $includedCodes, $solution->roundingAdjustments)->keyBy('code')->all(),
    ];
}

it('takes a single rate out of an amount like amount × rate / (100 + rate)', function (string $amount, string $base, string $tax, array $adjustments): void {
    $result = solveIncluded(solverProfile([solverTax('VAT', '18', ['kind' => TaxKind::Vat])]), $amount, ['VAT']);

    expect($result['base'])->toBe($base)
        ->and($result['taxes']['VAT']->taxAmount)->toBe($tax)
        ->and($result['adjustments'])->toBe($adjustments);
})->with([
    'a whole base exists'                  => ['11800', '10000', '1800', []],
    'another whole base'                   => ['500', '424', '76', []],
    '102.54 before tax rounds up to 103'   => ['121', '103', '18', ['VAT' => '-1']],
    '8.47 before tax rounds down to 8'     => ['10', '8', '2', ['VAT' => '1']],
    '847.46 before tax rounds down to 847' => ['1000', '847', '153', ['VAT' => '1']],
    'a tax below half a unit is no tax'    => ['3', '3', '0', ['VAT' => '-1']],
    'nothing to tax'                       => ['0', '0', '0', []],
]);

it('rounds the contained tax with the rounding mode of the tax, for every amount', function (TaxRoundingMode $roundingMode, string $rate): void {
    $profile = solverProfile([solverTax('VAT', $rate, ['roundingMode' => $roundingMode])]);

    foreach (range(0, 1500) as $amount) {
        $exactTax = bcdiv(bcmul((string) $amount, $rate, 10), bcadd('100', $rate, 10), 20);
        $expectedTax = bcround($exactTax, 0, $roundingMode->nativeMode());
        $result = solveIncluded($profile, (string) $amount, ['VAT']);

        expect($result['taxes']['VAT']->taxAmount)->toBe($expectedTax, "tax of {$amount} at {$rate}% rounded {$roundingMode->value}")
            ->and(bcadd($result['base'], $result['taxes']['VAT']->taxAmount, 0))->toBe((string) $amount);
    }
})->with([
    'half up, 18%'  => [TaxRoundingMode::HalfUp, '18'],
    'up, 18%'       => [TaxRoundingMode::Up, '18'],
    'down, 18%'     => [TaxRoundingMode::Down, '18'],
    'half up, 7.5%' => [TaxRoundingMode::HalfUp, '7.5'],
    'half up, 100%' => [TaxRoundingMode::HalfUp, '100'],
    'half up, 250%' => [TaxRoundingMode::HalfUp, '250'],
    'down, 19.25%'  => [TaxRoundingMode::Down, '19.25'],
    'up, 0.5%'      => [TaxRoundingMode::Up, '0.5'],
]);

it('keeps the amount to the unit on one of the two nearest whole bases, whatever the taxes', function (array $taxes, array $includedCodes): void {
    $profile = solverProfile($taxes);
    $calculator = new TaxCalculator;
    $includedIds = array_fill_keys($includedCodes, true);
    $grossAt = fn (int $base): string => bcadd((string) $base, $calculator->totalOf($profile, $includedIds, (string) $base, [])['rounded'], 0);
    $lastBaseAtMost = 0;

    foreach (range((int) $grossAt(0), 1200) as $amount) {
        // the gross grows with the base: walk the last base whose gross is at most the amount
        while (bccomp($grossAt($lastBaseAtMost + 1), (string) $amount, 0) <= 0) {
            $lastBaseAtMost++;
        }

        $result = solveIncluded($profile, (string) $amount, $includedCodes);
        $includedTaxAmount = array_reduce(
            array_filter($result['taxes'], fn (TaxLineResultViewData $tax): bool => $tax->isIncluded),
            fn (string $sum, TaxLineResultViewData $tax): string => bcadd($sum, $tax->taxAmount, 0),
            '0',
        );

        expect(bcadd($result['base'], $includedTaxAmount, 0))->toBe((string) $amount, "amount {$amount} is kept")
            ->and((int) $result['base'])->toBeIn([$lastBaseAtMost, $lastBaseAtMost + 1], "base of {$amount}");

        foreach ($result['taxes'] as $tax) {
            expect(bccomp($tax->taxAmount, '0', 0))->toBeGreaterThanOrEqual(0, "tax {$tax->code} of {$amount} is not negative");
        }

        if ($grossAt($lastBaseAtMost) === (string) $amount) {
            expect($result['base'])->toBe((string) $lastBaseAtMost)
                ->and($result['adjustments'])->toBe([]);
        } else {
            expect($result['adjustments'])->toHaveCount(1)
                ->and(abs((int) array_values($result['adjustments'])[0]))->toBeLessThanOrEqual(count($includedCodes));
        }
    }
})->with([
    'two independent rates' => [
        [solverTax('LEVY', '5'), solverTax('VAT', '18', ['kind' => TaxKind::Vat])],
        ['LEVY', 'VAT'],
    ],
    'a rate on a rate' => [
        [solverTax('LEVY', '5'), solverTax('VAT', '18', ['kind' => TaxKind::Vat, 'dependsOn' => ['LEVY']])],
        ['LEVY', 'VAT'],
    ],
    'a fixed amount and a rate on it' => [
        [
            solverTax('ECO', '0', ['calculationType' => TaxCalculationType::Fixed, 'fixedMode' => TaxFixedMode::PerLine, 'brackets' => [new CompiledBracket('0', null, '250', null, null)]]),
            solverTax('VAT', '18', ['kind' => TaxKind::Vat, 'dependsOn' => ['ECO']]),
        ],
        ['ECO', 'VAT'],
    ],
    'marginal brackets with a ceiling' => [
        [solverTax('VAT', '0', ['kind' => TaxKind::Vat, 'isProgressive' => true, 'bracketMode' => TaxBracketMode::Marginal, 'brackets' => [
            new CompiledBracket('0', '300', '0', null, null),
            new CompiledBracket('300', '900', '10', null, '40'),
            new CompiledBracket('900', null, '20', null, null),
        ]])],
        ['VAT'],
    ],
    'a tax on the tax only' => [
        [
            solverTax('VAT', '18', ['kind' => TaxKind::Vat]),
            solverTax('COUNCIL', '10', ['baseType' => TaxBaseType::DependenciesOnly, 'dependsOn' => ['VAT']]),
        ],
        ['VAT', 'COUNCIL'],
    ],
    'only one of two rates is contained' => [
        [solverTax('LEVY', '5'), solverTax('VAT', '18', ['kind' => TaxKind::Vat])],
        ['VAT'],
    ],
    'rates rounded in opposite directions' => [
        [solverTax('LEVY', '7', ['roundingMode' => TaxRoundingMode::Down]), solverTax('VAT', '18', ['kind' => TaxKind::Vat, 'roundingMode' => TaxRoundingMode::Up])],
        ['LEVY', 'VAT'],
    ],
    'an exempt tax is contained for nothing' => [
        [solverTax('LEVY', '5', ['treatment' => TaxTreatment::Exempt]), solverTax('VAT', '18', ['kind' => TaxKind::Vat, 'dependsOn' => ['LEVY']])],
        ['LEVY', 'VAT'],
    ],
]);

it('gives the rounding difference to the value-added tax, else to the last rate calculated, never to a fixed amount', function (array $taxes, array $includedCodes, string $amount, string $absorberCode): void {
    $result = solveIncluded(solverProfile($taxes), $amount, $includedCodes);

    expect(array_keys($result['adjustments']))->toBe([$absorberCode]);
})->with([
    'the value-added tax, though calculated first' => [
        [solverTax('AAA-VAT', '18', ['kind' => TaxKind::Vat]), solverTax('LEVY', '5')],
        ['AAA-VAT', 'LEVY'],
        '10',
        'AAA-VAT',
    ],
    'the last rate when there is no value-added tax' => [
        [solverTax('FIRST', '18'), solverTax('SECOND', '5')],
        ['FIRST', 'SECOND'],
        '10',
        'SECOND',
    ],
    'the rate, not the fixed amount calculated after it' => [
        [
            solverTax('RATE', '18'),
            solverTax('ZFIXED', '0', ['calculationType' => TaxCalculationType::Fixed, 'fixedMode' => TaxFixedMode::PerLine, 'brackets' => [new CompiledBracket('0', null, '5', null, null)]]),
        ],
        ['RATE', 'ZFIXED'],
        '15',
        'RATE',
    ],
    'the tax on the value-added tax, so the value-added tax stays what it was calculated on' => [
        [
            solverTax('VAT', '18', ['kind' => TaxKind::Vat]),
            solverTax('COUNCIL', '10', ['baseType' => TaxBaseType::DependenciesOnly, 'dependsOn' => ['VAT']]),
        ],
        ['VAT', 'COUNCIL'],
        '123',
        'COUNCIL',
    ],
]);

it('lets a tax added on top see the contained tax as it is kept, rounding difference included', function (): void {
    $profile = solverProfile([
        solverTax('VAT', '18', ['kind' => TaxKind::Vat]),
        solverTax('LEVY', '5', ['dependsOn' => ['VAT']]),
    ]);

    $result = solveIncluded($profile, '121', ['VAT']);

    // 103 before tax and 18 of value-added tax: the levy is calculated on the 121 displayed
    expect($result['base'])->toBe('103')
        ->and($result['taxes']['VAT']->taxAmount)->toBe('18')
        ->and($result['taxes']['VAT']->roundingAdjustment)->toBe('-1')
        ->and($result['taxes']['VAT']->isIncluded)->toBeTrue()
        ->and($result['taxes']['LEVY']->taxableBaseAmount)->toBe('121')
        ->and($result['taxes']['LEVY']->taxAmount)->toBe('6')
        ->and($result['taxes']['LEVY']->roundingAdjustment)->toBe('0')
        ->and($result['taxes']['LEVY']->isIncluded)->toBeFalse();
});

it('refuses an amount lower than the fixed taxes it contains', function (): void {
    $profile = solverProfile([
        solverTax('ECO', '0', ['calculationType' => TaxCalculationType::Fixed, 'fixedMode' => TaxFixedMode::PerLine, 'brackets' => [new CompiledBracket('0', null, '250', null, null)]]),
    ]);

    expect(solveIncluded($profile, '250', ['ECO'])['base'])->toBe('0')
        ->and(fn () => solveIncluded($profile, '249', ['ECO']))->toThrow(TaxException::class, __('tax::exceptions.amount_below_included_taxes'));
});

it('finds the base of the largest amounts in a handful of calculations', function (): void {
    $profile = solverProfile([solverTax('VAT', '18', ['kind' => TaxKind::Vat])]);
    $amount = '999999999999999999';

    $result = solveIncluded($profile, $amount, ['VAT']);
    $expectedTax = bcround(bcdiv(bcmul($amount, '18', 0), '118', 20), 0, RoundingMode::HalfAwayFromZero);

    expect($result['taxes']['VAT']->taxAmount)->toBe($expectedTax)
        ->and(bcadd($result['base'], $expectedTax, 0))->toBe($amount);
});

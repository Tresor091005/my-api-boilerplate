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
use Lahatre\Tax\Services\Engine\IncludedTaxRules;

/**
 * A compiled tax built by hand, 18 percent on a single open bracket unless the
 * overrides say otherwise. Its id is its code.
 *
 * @param  array<string, mixed>  $overrides
 */
function ruleTax(string $code, array $overrides = []): CompiledTax
{
    return new CompiledTax(
        id: $code,
        code: $code,
        name: $code,
        kind: TaxKind::Other,
        treatment: $overrides['treatment'] ?? TaxTreatment::Standard,
        calculationType: $overrides['calculationType'] ?? TaxCalculationType::Percentage,
        fixedMode: $overrides['fixedMode'] ?? null,
        taxUnitCode: null,
        taxUnitGroupId: null,
        baseType: TaxBaseType::AmountWithDependencies,
        isProgressive: $overrides['isProgressive'] ?? false,
        bracketMode: $overrides['bracketMode'] ?? null,
        roundingMode: TaxRoundingMode::HalfUp,
        isActive: true,
        brackets: $overrides['brackets'] ?? [new CompiledBracket('0', null, '18', null, null)],
        dependencies: $overrides['dependencies'] ?? [],
    );
}

/**
 * @param  list<CompiledTax>  $taxes
 * @param  list<string>  $includedCodes
 * @return list<string> codes of the taxes kept
 */
function includedCodes(array $taxes, array $includedCodes): array
{
    $profile = new CompiledTaxProfile(id: 'profile', code: 'PROFILE', isActive: true, taxes: $taxes);

    return array_map(
        fn (CompiledTax $tax): string => $tax->code,
        (new IncludedTaxRules)->includedTaxes($profile, $includedCodes),
    );
}

it('keeps the taxes of the profile in calculation order and leaves the others out', function (): void {
    $taxes = [ruleTax('LEVY'), ruleTax('VAT'), ruleTax('ECO')];

    expect(includedCodes($taxes, ['ECO', 'UNKNOWN', 'LEVY']))->toBe(['LEVY', 'ECO'])
        ->and(includedCodes($taxes, []))->toBe([]);
});

it('accepts the taxes that grow smoothly with their base', function (array $overrides): void {
    expect(includedCodes([ruleTax('TAX', $overrides)], ['TAX']))->toBe(['TAX']);
})->with([
    'a single rate'                => [[]],
    'a single rate with a ceiling' => [['brackets' => [new CompiledBracket('0', null, '18', null, '5000')]]],
    'marginal brackets'            => [['isProgressive' => true, 'bracketMode' => TaxBracketMode::Marginal, 'brackets' => [
        new CompiledBracket('0', '1000', '10', null, null),
        new CompiledBracket('1000', null, '5', null, null),
    ]]],
    'a fixed amount per line'                                  => [['calculationType' => TaxCalculationType::Fixed, 'fixedMode' => TaxFixedMode::PerLine, 'brackets' => [new CompiledBracket('0', null, '250', null, null)]]],
    'a fixed amount per unit, even with a floor and a ceiling' => [['calculationType' => TaxCalculationType::Fixed, 'fixedMode' => TaxFixedMode::PerTaxUnit, 'brackets' => [new CompiledBracket('0', null, '60.69', '100', '9000')]]],
    'a zero rate that never reaches its minimum'               => [['brackets' => [new CompiledBracket('0', null, '0', '500', null)]]],
]);

it('refuses a tax whose amount jumps', function (array $overrides, string $messageKey): void {
    expect(fn () => includedCodes([ruleTax('TAX', $overrides)], ['TAX']))
        ->toThrow(TaxException::class, __($messageKey, ['code' => 'TAX']));
})->with([
    'a rate applied to the whole amount past a threshold' => [
        ['isProgressive' => true, 'bracketMode' => TaxBracketMode::WholeAmount, 'brackets' => [
            new CompiledBracket('0', '1000', '10', null, null),
            new CompiledBracket('1000', null, '20', null, null),
        ]],
        'tax::exceptions.included_tax_whole_amount',
    ],
    'a fixed amount changing by bracket' => [
        ['calculationType' => TaxCalculationType::Fixed, 'fixedMode' => TaxFixedMode::PerLine, 'isProgressive' => true, 'brackets' => [
            new CompiledBracket('0', '1000', '0', null, null),
            new CompiledBracket('1000', null, '500', null, null),
        ]],
        'tax::exceptions.included_tax_fixed_by_bracket',
    ],
    'a minimum amount' => [
        ['brackets' => [new CompiledBracket('0', null, '1', '500', null)]],
        'tax::exceptions.included_tax_has_minimum',
    ],
    'a minimum amount on one marginal bracket' => [
        ['isProgressive' => true, 'bracketMode' => TaxBracketMode::Marginal, 'brackets' => [
            new CompiledBracket('0', '1000', '10', null, null),
            new CompiledBracket('1000', null, '20', '50', null),
        ]],
        'tax::exceptions.included_tax_has_minimum',
    ],
]);

it('accepts any shape for a tax that does not apply', function (): void {
    $wholeAmount = ['isProgressive' => true, 'bracketMode' => TaxBracketMode::WholeAmount, 'brackets' => [
        new CompiledBracket('0', '1000', '10', null, null),
        new CompiledBracket('1000', null, '20', null, null),
    ]];

    expect(includedCodes([ruleTax('TAX', [...$wholeAmount, 'treatment' => TaxTreatment::Exempt])], ['TAX']))->toBe(['TAX'])
        ->and(includedCodes([ruleTax('TAX', [...$wholeAmount, 'treatment' => TaxTreatment::Zero])], ['TAX']))->toBe(['TAX']);
});

it('requires a contained tax to be calculated only on contained taxes', function (): void {
    $taxes = [
        ruleTax('ECO'),
        ruleTax('VAT', ['dependencies' => [new CompiledDependency('ECO', TaxDependencyOperator::Add)]]),
    ];

    expect(includedCodes($taxes, ['ECO', 'VAT']))->toBe(['ECO', 'VAT'])
        // the tax added on top may be calculated on the contained one
        ->and(includedCodes($taxes, ['ECO']))->toBe(['ECO'])
        ->and(fn () => includedCodes($taxes, ['VAT']))
        ->toThrow(TaxException::class, __('tax::exceptions.included_tax_depends_on_added_tax', ['code' => 'VAT', 'dependency' => 'ECO']));
});

it('refuses a contained tax that subtracts another tax', function (): void {
    $taxes = [
        ruleTax('ECO'),
        ruleTax('VAT', ['dependencies' => [new CompiledDependency('ECO', TaxDependencyOperator::Subtract)]]),
    ];

    expect(fn () => includedCodes($taxes, ['ECO', 'VAT']))
        ->toThrow(TaxException::class, __('tax::exceptions.included_tax_subtracts_dependency', ['code' => 'VAT', 'dependency' => 'ECO']));
});

it('ignores a dependency that does not apply, added or subtracted', function (TaxDependencyOperator $operator): void {
    $taxes = [
        ruleTax('ECO', ['treatment' => TaxTreatment::Exempt]),
        ruleTax('VAT', ['dependencies' => [new CompiledDependency('ECO', $operator)]]),
    ];

    expect(includedCodes($taxes, ['VAT']))->toBe(['VAT']);
})->with([
    'added'      => [TaxDependencyOperator::Add],
    'subtracted' => [TaxDependencyOperator::Subtract],
]);

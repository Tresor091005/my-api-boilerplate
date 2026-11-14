<?php

declare(strict_types=1);

namespace Lahatre\Tax\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class TaxException extends AssertionException
{
    public static function duplicateTaxCode(string $code): self
    {
        return new self(__('tax::exceptions.duplicate_tax_code', ['code' => $code]), ['code' => $code]);
    }

    public static function fixedModeRequired(): self
    {
        return new self(__('tax::exceptions.fixed_mode_required'));
    }

    public static function fixedModeNotAllowed(): self
    {
        return new self(__('tax::exceptions.fixed_mode_not_allowed'));
    }

    public static function taxUnitRequired(): self
    {
        return new self(__('tax::exceptions.tax_unit_required'));
    }

    public static function taxUnitNotAllowed(): self
    {
        return new self(__('tax::exceptions.tax_unit_not_allowed'));
    }

    public static function taxUnitUnavailable(string $unitCode): self
    {
        return new self(__('tax::exceptions.tax_unit_unavailable', ['unit' => $unitCode]), ['tax_unit_code' => $unitCode]);
    }

    public static function bracketModeRequired(): self
    {
        return new self(__('tax::exceptions.bracket_mode_required'));
    }

    public static function bracketModeNotAllowed(): self
    {
        return new self(__('tax::exceptions.bracket_mode_not_allowed'));
    }

    public static function singleOpenBracketRequired(): self
    {
        return new self(__('tax::exceptions.single_open_bracket_required'));
    }

    public static function progressiveBracketsRequired(): self
    {
        return new self(__('tax::exceptions.progressive_brackets_required'));
    }

    public static function bracketsMustStartAtZero(): self
    {
        return new self(__('tax::exceptions.brackets_must_start_at_zero'), ['bracket_index' => 0]);
    }

    public static function bracketsNotContiguous(int $index): self
    {
        return new self(__('tax::exceptions.brackets_not_contiguous'), ['bracket_index' => $index]);
    }

    public static function bracketBoundsInverted(int $index): self
    {
        return new self(__('tax::exceptions.bracket_bounds_inverted'), ['bracket_index' => $index]);
    }

    public static function onlyLastBracketMayBeOpen(int $index): self
    {
        return new self(__('tax::exceptions.only_last_bracket_may_be_open'), ['bracket_index' => $index]);
    }

    public static function lastBracketMustBeOpen(int $index): self
    {
        return new self(__('tax::exceptions.last_bracket_must_be_open'), ['bracket_index' => $index]);
    }

    public static function bracketAmountLimitsInverted(int $index): self
    {
        return new self(__('tax::exceptions.bracket_amount_limits_inverted'), ['bracket_index' => $index]);
    }

    public static function bracketAmountLimitsNotAllowed(int $index): self
    {
        return new self(__('tax::exceptions.bracket_amount_limits_not_allowed'), ['bracket_index' => $index]);
    }

    public static function monetaryPrecisionExceeded(string $field, int $index, int $precision): self
    {
        return new self(__('tax::exceptions.monetary_precision_exceeded', ['precision' => $precision]), [
            'field'         => $field,
            'bracket_index' => $index,
            'precision'     => $precision,
        ]);
    }

    public static function dependencyRequired(): self
    {
        return new self(__('tax::exceptions.dependency_required'));
    }

    public static function addedDependencyRequired(): self
    {
        return new self(__('tax::exceptions.added_dependency_required'));
    }

    public static function selfDependency(): self
    {
        return new self(__('tax::exceptions.self_dependency'));
    }

    public static function duplicateDependency(string $taxId): self
    {
        return new self(__('tax::exceptions.duplicate_dependency'), ['depends_on_tax_id' => $taxId]);
    }

    /** @param list<string> $taxIds */
    public static function dependencyTaxesNotFound(array $taxIds): self
    {
        return new self(__('tax::exceptions.dependency_taxes_not_found'), ['depends_on_tax_ids' => $taxIds]);
    }

    /** @param list<string> $taxCodes */
    public static function dependencyCycle(array $taxCodes): self
    {
        return new self(__('tax::exceptions.dependency_cycle', ['cycle' => implode(' → ', $taxCodes)]), ['tax_codes' => $taxCodes]);
    }

    /** @param list<string> $profileCodes */
    public static function taxProgressionLocked(array $profileCodes): self
    {
        return new self(__('tax::exceptions.tax_progression_locked'), ['tax_profile_codes' => $profileCodes]);
    }

    /** @param list<string> $profileCodes */
    public static function taxStructureLocked(array $profileCodes): self
    {
        return new self(__('tax::exceptions.tax_structure_locked'), ['tax_profile_codes' => $profileCodes]);
    }

    /** @param list<string> $profileCodes */
    public static function taxUsedByActiveProfiles(array $profileCodes): self
    {
        return new self(__('tax::exceptions.tax_used_by_active_profiles'), ['tax_profile_codes' => $profileCodes]);
    }

    /** @param list<string> $profileCodes */
    public static function taxUsedByProfiles(array $profileCodes): self
    {
        return new self(__('tax::exceptions.tax_used_by_profiles'), ['tax_profile_codes' => $profileCodes]);
    }

    /** @param list<string> $taxCodes */
    public static function taxHasDependents(array $taxCodes): self
    {
        return new self(__('tax::exceptions.tax_has_dependents'), ['tax_codes' => $taxCodes]);
    }

    public static function duplicateProfileCode(string $code): self
    {
        return new self(__('tax::exceptions.duplicate_profile_code', ['code' => $code]), ['code' => $code]);
    }

    /** @param list<string> $taxIds */
    public static function profileTaxesNotFound(array $taxIds): self
    {
        return new self(__('tax::exceptions.profile_taxes_not_found'), ['tax_ids' => $taxIds]);
    }

    /** @param list<string> $taxCodes */
    public static function profileTaxesInactive(array $taxCodes): self
    {
        return new self(__('tax::exceptions.profile_taxes_inactive'), ['tax_codes' => $taxCodes]);
    }

    public static function duplicateProfileTax(string $taxId): self
    {
        return new self(__('tax::exceptions.duplicate_profile_tax'), ['tax_id' => $taxId]);
    }

    public static function customBracketsRequired(string $taxCode): self
    {
        return new self(__('tax::exceptions.custom_brackets_required', ['code' => $taxCode]), ['tax_code' => $taxCode]);
    }

    public static function customBracketsNotAllowed(string $taxCode): self
    {
        return new self(__('tax::exceptions.custom_brackets_not_allowed', ['code' => $taxCode]), ['tax_code' => $taxCode]);
    }

    /** @param list<string> $taxCodes */
    public static function multipleVatTaxes(array $taxCodes): self
    {
        return new self(__('tax::exceptions.multiple_vat_taxes'), ['tax_codes' => $taxCodes]);
    }

    /** @param list<string> $missingTaxCodes */
    public static function dependenciesMissingFromProfile(string $taxCode, array $missingTaxCodes): self
    {
        return new self(__('tax::exceptions.dependencies_missing_from_profile', ['code' => $taxCode]), [
            'tax_code'                 => $taxCode,
            'missing_dependency_codes' => $missingTaxCodes,
        ]);
    }

    /** @param list<string> $profileCodes */
    public static function dependenciesMissingFromProfiles(string $taxCode, array $profileCodes): self
    {
        return new self(__('tax::exceptions.dependencies_missing_from_profiles', ['code' => $taxCode]), [
            'tax_code'          => $taxCode,
            'tax_profile_codes' => $profileCodes,
        ]);
    }

    public static function taxUnitIncompatible(string $taxCode, string $unitGroupId): self
    {
        return new self(__('tax::exceptions.tax_unit_incompatible', ['code' => $taxCode]), [
            'tax_code'      => $taxCode,
            'unit_group_id' => $unitGroupId,
        ]);
    }

    /** @param list<string> $taxIds */
    public static function includedTaxesOutsideProfile(string $profileCode, array $taxIds): self
    {
        return new self(__('tax::exceptions.included_taxes_outside_profile', ['code' => $profileCode]), [
            'tax_profile_code' => $profileCode,
            'tax_ids'          => $taxIds,
        ]);
    }

    public static function includedTaxAppliesToWholeAmount(string $taxCode): self
    {
        return new self(__('tax::exceptions.included_tax_whole_amount', ['code' => $taxCode]), ['tax_code' => $taxCode]);
    }

    public static function includedTaxFixedByBracket(string $taxCode): self
    {
        return new self(__('tax::exceptions.included_tax_fixed_by_bracket', ['code' => $taxCode]), ['tax_code' => $taxCode]);
    }

    public static function includedTaxHasMinimum(string $taxCode): self
    {
        return new self(__('tax::exceptions.included_tax_has_minimum', ['code' => $taxCode]), ['tax_code' => $taxCode]);
    }

    public static function includedTaxSubtractsDependency(string $taxCode, string $dependencyCode): self
    {
        return new self(__('tax::exceptions.included_tax_subtracts_dependency', ['code' => $taxCode, 'dependency' => $dependencyCode]), [
            'tax_code'            => $taxCode,
            'dependency_tax_code' => $dependencyCode,
        ]);
    }

    public static function includedTaxDependsOnAddedTax(string $taxCode, string $dependencyCode): self
    {
        return new self(__('tax::exceptions.included_tax_depends_on_added_tax', ['code' => $taxCode, 'dependency' => $dependencyCode]), [
            'tax_code'            => $taxCode,
            'dependency_tax_code' => $dependencyCode,
        ]);
    }

    /** The obstacle names the tax and why an amount can no longer contain it. */
    public static function includedTaxesInUse(string $profileCode, self $obstacle): self
    {
        return new self(__('tax::exceptions.included_taxes_in_use', ['profile' => $profileCode, 'reason' => $obstacle->getMessage()]), [
            ...$obstacle->context(),
            'tax_profile_code' => $profileCode,
        ]);
    }

    public static function amountBelowIncludedTaxes(string $amount): self
    {
        return new self(__('tax::exceptions.amount_below_included_taxes'), ['amount' => $amount]);
    }

    public static function profileInUse(string $profileCode): self
    {
        return new self(__('tax::exceptions.profile_in_use', ['code' => $profileCode]), ['tax_profile_code' => $profileCode]);
    }

    public static function profileNotFound(string $profileId): self
    {
        return new self(__('tax::exceptions.profile_not_found'), ['tax_profile_id' => $profileId]);
    }

    public static function profileInactive(string $profileCode): self
    {
        return new self(__('tax::exceptions.profile_inactive', ['code' => $profileCode]), ['tax_profile_code' => $profileCode]);
    }

    public static function currencyMismatch(string $currencyCode, string $functionalCurrencyCode): self
    {
        return new self(__('tax::exceptions.currency_mismatch', [
            'currency'   => $currencyCode,
            'functional' => $functionalCurrencyCode,
        ]), [
            'currency_code'            => $currencyCode,
            'functional_currency_code' => $functionalCurrencyCode,
        ]);
    }

    public static function invalidAmount(string $amount): self
    {
        return new self(__('tax::exceptions.invalid_amount'), ['amount' => $amount]);
    }

    public static function invalidQuantity(string $quantity): self
    {
        return new self(__('tax::exceptions.invalid_quantity'), ['quantity' => $quantity]);
    }

    public static function saleUnitRequired(string $taxCode): self
    {
        return new self(__('tax::exceptions.sale_unit_required', ['code' => $taxCode]), ['tax_code' => $taxCode]);
    }

    public static function saleUnitUnavailable(string $unitCode): self
    {
        return new self(__('tax::exceptions.sale_unit_unavailable', ['unit' => $unitCode]), ['unit_code' => $unitCode]);
    }

    public static function negativeTaxableBase(string $taxCode): self
    {
        return new self(__('tax::exceptions.negative_taxable_base', ['code' => $taxCode]), ['tax_code' => $taxCode]);
    }

    /** @param array<string, mixed> $context */
    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}

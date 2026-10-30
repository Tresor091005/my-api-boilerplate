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

    public static function taxUnitUnavailable(string $unitId): self
    {
        return new self(__('tax::exceptions.tax_unit_unavailable'), ['tax_unit_id' => $unitId]);
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

    public static function monetaryPrecisionExceeded(string $field, int $index, int $precision): self
    {
        return new self(__('tax::exceptions.monetary_precision_exceeded', ['precision' => $precision]), [
            'field'         => $field,
            'bracket_index' => $index,
            'precision'     => $precision,
        ]);
    }

    public static function bracketNotOwnedByTax(string $bracketId): self
    {
        return new self(__('tax::exceptions.bracket_not_owned_by_tax'), ['tax_bracket_id' => $bracketId]);
    }

    /** @param list<string> $bracketIds */
    public static function bracketsOverriddenByProfiles(array $bracketIds): self
    {
        return new self(__('tax::exceptions.brackets_overridden_by_profiles'), ['tax_bracket_ids' => $bracketIds]);
    }

    public static function dependencyRequired(): self
    {
        return new self(__('tax::exceptions.dependency_required'));
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

    public static function bracketOverrideRequired(string $taxCode): self
    {
        return new self(__('tax::exceptions.bracket_override_required', ['code' => $taxCode]), ['tax_code' => $taxCode]);
    }

    public static function bracketOverrideNotAllowed(string $taxCode): self
    {
        return new self(__('tax::exceptions.bracket_override_not_allowed', ['code' => $taxCode]), ['tax_code' => $taxCode]);
    }

    public static function duplicateBracketOverride(string $bracketId): self
    {
        return new self(__('tax::exceptions.duplicate_bracket_override'), ['tax_bracket_id' => $bracketId]);
    }

    public static function overridePrecisionExceeded(string $taxCode, int $precision): self
    {
        return new self(__('tax::exceptions.override_precision_exceeded', ['code' => $taxCode, 'precision' => $precision]), [
            'tax_code'  => $taxCode,
            'precision' => $precision,
        ]);
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

    public static function invalidBaseAmount(string $baseAmount): self
    {
        return new self(__('tax::exceptions.invalid_base_amount'), ['base_amount' => $baseAmount]);
    }

    public static function invalidQuantity(string $quantity): self
    {
        return new self(__('tax::exceptions.invalid_quantity'), ['quantity' => $quantity]);
    }

    public static function saleUnitRequired(string $taxCode): self
    {
        return new self(__('tax::exceptions.sale_unit_required', ['code' => $taxCode]), ['tax_code' => $taxCode]);
    }

    public static function saleUnitIncompatible(string $unitCode): self
    {
        return new self(__('tax::exceptions.sale_unit_incompatible', ['unit' => $unitCode]), ['unit_code' => $unitCode]);
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

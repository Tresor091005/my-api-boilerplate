<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\Validator;

/**
 * Attached to a list of prices: checks the `included_tax_ids` of all of them
 * with one lookup. Each id is a non-deleted tax of the organization and is
 * listed once per price. Errors land on the exact id, such as
 * `prices.1.included_tax_ids.0`.
 */
final class ValidIncludedTaxes implements ValidationRule, ValidatorAwareRule
{
    private ?Validator $validator = null;

    public function __construct(
        private readonly string $organizationId,
    ) {}

    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    /** @param Closure(string): PotentiallyTranslatedString $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value)) {
            return;
        }

        $taxIdsByPrice = collect($value)
            ->filter(fn (mixed $price): bool => is_array($price) && is_array($price['included_tax_ids'] ?? null))
            ->map(fn (array $price): array => $price['included_tax_ids']);
        $requestedTaxIds = $taxIdsByPrice
            ->flatten(1)
            ->filter(fn (mixed $taxId): bool => is_string($taxId) && Str::isUuid($taxId))
            ->unique()
            ->values();

        if ($requestedTaxIds->isEmpty()) {
            return;
        }

        $availableTaxIds = DB::table('tax_taxes')
            ->where('organization_id', $this->organizationId)
            ->whereNull('deleted_at')
            ->whereIn('id', $requestedTaxIds->all())
            ->pluck('id')
            ->flip();

        foreach ($taxIdsByPrice as $priceIndex => $taxIds) {
            $seenTaxIds = [];

            foreach ($taxIds as $taxIndex => $taxId) {
                // a malformed id is reported by the uuid rule
                if (!is_string($taxId) || !Str::isUuid($taxId)) {
                    continue;
                }

                $path = "{$attribute}.{$priceIndex}.included_tax_ids.{$taxIndex}";

                if (isset($seenTaxIds[$taxId])) {
                    $this->addError($path, __('catalog::validation.included_tax_duplicate'), $fail);
                } elseif (!$availableTaxIds->has($taxId)) {
                    $this->addError($path, __('catalog::validation.included_tax_unavailable'), $fail);
                }

                $seenTaxIds[$taxId] = true;
            }
        }
    }

    /** @param Closure(string): PotentiallyTranslatedString $fail */
    private function addError(string $path, string $message, Closure $fail): void
    {
        if ($this->validator instanceof Validator) {
            $this->validator->errors()->add($path, $message);

            return;
        }

        $fail($message);
    }
}

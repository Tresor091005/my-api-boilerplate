<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\Validator;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Master\Contracts\MasterInterface;

final class ValidBundleItems implements ValidationRule, ValidatorAwareRule
{
    private ?Validator $validator = null;

    public function __construct(
        private readonly string $organizationId,
        private readonly MasterInterface $masterInterface,
        private readonly ?string $bundleId = null,
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

        $items = collect($value)->filter(fn (mixed $item): bool => is_array($item));
        $itemIds = $items->pluck('item_id')->filter(fn (mixed $id): bool => is_string($id) && Str::isUuid($id));
        /** @var Collection<int, string> $unitCodes */
        $unitCodes = $items->pluck('unit_code')->filter(fn (mixed $code): bool => is_string($code) && $code !== '');
        $catalogItems = DB::table('catalog_items')
            ->select(['id', 'item_type', 'unit_group_id', 'is_active'])
            ->where('organization_id', $this->organizationId)
            ->whereNull('deleted_at')
            ->whereIn('id', $itemIds->unique()->values()->all())
            ->get()
            ->keyBy('id');

        $units = $this->masterInterface->units($unitCodes->unique()->values());

        $attachedItemIds = $this->bundleId === null
            ? collect()
            : DB::table('catalog_bundle_items')
                ->where('organization_id', $this->organizationId)
                ->where('bundle_id', $this->bundleId)
                ->whereNull('deleted_at')
                ->whereIn('item_id', $itemIds->unique()->values()->all())
                ->pluck('item_id')
                ->flip();

        $duplicateIds = $itemIds->countBy()->filter(fn (int $count): bool => $count > 1);
        $allowedTypes = collect(CatalogItemType::allowedBundleComponentTypes())->map->value;

        foreach ($items as $index => $item) {
            $itemId = $item['item_id'] ?? null;
            $itemType = $item['item_type'] ?? null;
            $unitCode = $item['unit_code'] ?? null;

            if (!is_string($itemId) || !Str::isUuid($itemId)) {
                continue;
            }

            if ($duplicateIds->has($itemId)) {
                $this->addError("{$attribute}.{$index}.item_id", __('catalog::validation.duplicate_bundle_item'), $fail);
            }

            if ($attachedItemIds->has($itemId)) {
                $this->addError("{$attribute}.{$index}.item_id", __('catalog::validation.bundle_item_already_attached'), $fail);
            }

            $catalogItem = $catalogItems->get($itemId);
            if ($catalogItem === null) {
                $this->addError("{$attribute}.{$index}.item_id", __('catalog::validation.bundle_item_unavailable'), $fail);

                continue;
            }

            if (!$catalogItem->is_active) {
                $this->addError("{$attribute}.{$index}.item_id", __('catalog::validation.bundle_item_inactive'), $fail);
            }

            if (!is_string($itemType) || !$allowedTypes->contains($itemType) || $catalogItem->item_type !== $itemType) {
                $this->addError("{$attribute}.{$index}.item_type", __('catalog::validation.bundle_item_type_mismatch'), $fail);
            }

            $unit = is_string($unitCode) ? $units->get($unitCode) : null;
            if ($unit === null || $unit->group_id !== $catalogItem->unit_group_id) {
                $this->addError("{$attribute}.{$index}.unit_code", __('catalog::validation.bundle_item_unit_mismatch'), $fail);
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

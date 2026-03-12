<?php

declare(strict_types=1);

namespace Lahatre\Catalog\DTO;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lahatre\Shared\DTO\LahatreDTO;
use Lahatre\Shared\Rules\BulkExists;

class ProductDTO extends LahatreDTO
{
    public string $name;

    public ?string $description = null;

    public bool $is_active;

    /** @var array<int, string>|null */
    public ?array $categories = null;

    /** @var Collection<int, ProductVariantDataDTO>|null */
    public ?Collection $variants = null;

    protected function casts(): array
    {
        return [
            'is_active'  => 'bool',
            'categories' => 'array:string',
            'variants'   => 'collection:'.ProductVariantDataDTO::class,
        ];
    }

    protected function defaults(): array
    {
        return [
            'is_active' => false,
        ];
    }

    protected function beforeValidation(array $data): array
    {
        if (isset($data['name'])) {
            $data['name'] = Str::sanitize($data['name']);
        }

        return $data;
    }

    protected function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
            'categories'  => ['nullable', 'array', new BulkExists('catalog_categories')],
            'variants'    => [
                $this->modelId ? 'prohibited' : 'nullable',
                'array',
                new BulkExists('catalog_unit_groups', 'id', 'unit_group_id', 'uuid', true),
            ],
        ];
    }
}

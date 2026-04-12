<?php

declare(strict_types=1);

namespace Lahatre\Catalog\DTO;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lahatre\Shared\DTO\LahatreDTO;

class OptionDTO extends LahatreDTO
{
    public string $name;

    /** @var array<int, string>|null */
    public ?array $values = null;

    protected function casts(): array
    {
        return [
            'values' => 'array:string',
        ];
    }

    protected function defaults(): array
    {
        return [
            'values' => null,
        ];
    }

    protected function beforeValidation(array $data): array
    {
        if (isset($data['name'])) {
            $data['name'] = Str::normalize($data['name']);
        }

        if (isset($data['values']) && is_array($data['values'])) {
            $data['values'] = Arr::where($data['values'], fn (mixed $value): bool => is_string($value));
            $data['values'] = array_map(
                fn (string $value): string => Str::normalize($value),
                $data['values']
            );
            $data['values'] = array_values(array_unique($data['values']));
        }

        return $data;
    }

    protected function rules(): array
    {
        $uniqueName = Rule::unique('catalog_options', 'name')
            ->where('organization_id', getPermissionsTeamId());

        if ($this->modelId !== null) {
            $uniqueName->ignore($this->modelId);
        }

        return [
            'name'     => ['required', 'string', 'max:255', $uniqueName],
            'values'   => ['nullable', 'array'],
            'values.*' => ['string', 'max:255'],
        ];
    }
}

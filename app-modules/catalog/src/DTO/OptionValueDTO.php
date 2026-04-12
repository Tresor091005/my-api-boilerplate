<?php

declare(strict_types=1);

namespace Lahatre\Catalog\DTO;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lahatre\Shared\DTO\LahatreDTO;

class OptionValueDTO extends LahatreDTO
{
    public string $option_id;

    public ?string $value = null;

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
            'value'  => null,
            'values' => null,
        ];
    }

    protected function beforeValidation(array $data): array
    {
        if (isset($data['value'])) {
            $data['value'] = Str::normalize($data['value']);
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
        if ($this->modelId === null) {
            return [
                'option_id' => ['required', 'string'],
                'value'     => ['prohibited'],
                'values'    => ['required', 'array', 'min:1'],
                'values.*'  => ['string', 'max:255'],
            ];
        }

        $uniqueValue = Rule::unique('catalog_option_values', 'value')
            ->where(fn ($query) => $query->where('option_id', $this->dtoData['option_id'] ?? null)->where('organization_id', getPermissionsTeamId()))
            ->ignore($this->modelId);

        return [
            'option_id' => ['required', 'string'],
            'value'     => ['required', 'string', 'max:255', $uniqueValue],
            'values'    => ['prohibited'],
        ];
    }
}

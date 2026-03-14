<?php

declare(strict_types=1);

namespace Lahatre\Master\DTO;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lahatre\Shared\DTO\LahatreDTO;
use Lahatre\Shared\Rules\BulkExists;

class UnitSyncDTO extends LahatreDTO
{
    public ?string $group_id = null;

    public ?string $group_name = null;

    /** @var Collection<int, UnitDataDTO>|null */
    public ?Collection $units = null;

    protected function casts(): array
    {
        return [
            'units' => 'collection:'.UnitDataDTO::class,
        ];
    }

    protected function defaults(): array
    {
        return [];
    }

    protected function beforeValidation(array $data): array
    {
        if (isset($data['group_name'])) {
            $data['group_name'] = Str::normalize($data['group_name']);
        }

        return $data;
    }

    protected function rules(): array
    {
        $groupExists = Rule::exists('master_unit_groups', 'id')
            ->whereNull('deleted_at');

        $uniqueGroupName = Rule::unique('master_unit_groups', 'name')
            ->whereNull('deleted_at');

        if (isset($this->dtoData['group_id'])) {
            $uniqueGroupName->ignore($this->dtoData['group_id']);
        }

        return [
            'group_id'   => ['nullable', 'uuid', $groupExists],
            'group_name' => ['required_without:group_id', 'string', 'max:255', $uniqueGroupName],
            'units'      => [
                'required_without:group_id',
                'nullable',
                'array',
                new BulkExists('master_units', 'id', 'id', 'uuid', true),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        //
    }
}

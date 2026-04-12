<?php

declare(strict_types=1);

namespace Lahatre\Master\DTO;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lahatre\Shared\DTO\LahatreDTO;

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
        $organizationId = getPermissionsTeamId();

        // Strict tenancy check: must belong to the current organization AND must NOT be a system record (organization_id is NULL)
        $groupExists = Rule::exists('master_unit_groups', 'id')
            ->whereNotNull('organization_id')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at');

        $uniqueGroupName = Rule::unique('master_unit_groups', 'name')
            ->whereNotNull('organization_id')
            ->where('organization_id', $organizationId)
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
                'min:1',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (empty($this->dtoData['units'])) {
                return;
            }

            $organizationId = getPermissionsTeamId();
            $unitIds = collect($this->dtoData['units'])
                ->pluck('id')
                ->filter()
                ->all();

            if ($unitIds !== []) {
                $existingCount = DB::table('master_units')
                    ->whereIn('id', $unitIds)
                    ->whereNotNull('organization_id')
                    ->where('organization_id', $organizationId)
                    ->whereNull('deleted_at')
                    ->count();

                if ($existingCount !== count($unitIds)) {
                    $validator->errors()->add('units', __('shared::validation.bulk_exists', ['attribute' => 'units']));
                }
            }
        });
    }
}

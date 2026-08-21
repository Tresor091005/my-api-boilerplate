<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Services\Location;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Inventory\Contracts\HasInventoryLocation;
use Lahatre\Inventory\Exceptions\OrganizationScopeException;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Shared\Support\OrganizationScopeResolver;

class ManageInventoryLocationService
{
    public function __construct(
        protected OrganizationScopeResolver $organizationScopeResolver,
    ) {}

    public function create(HasInventoryLocation $model): InventoryLocation
    {
        return DB::transaction(
            fn (): InventoryLocation => $this->ensure(collect([$model]))->firstOrFail()
        );
    }

    /**
     * @param  array<int, HasInventoryLocation>|Collection<int, HasInventoryLocation>  $models
     */
    public function createMany(array|Collection $models): Collection
    {
        return DB::transaction(
            fn (): Collection => $this->ensure(collect($models))
        );
    }

    /**
     * @param  array{is_active?: bool}  $data
     */
    public function update(HasInventoryLocation $model, array $data): InventoryLocation
    {
        $validated = validator($data, [
            'is_active' => ['boolean'],
        ])->validate();

        return DB::transaction(function () use ($model, $validated): InventoryLocation {
            $location = $this->resolve($model);
            $location->fill([
                'is_active' => $validated['is_active'] ?? $location->is_active,
            ])->save();

            return $location;
        });
    }

    public function delete(HasInventoryLocation $model): void
    {
        DB::transaction(function () use ($model): void {
            $this->resolve($model)->delete();
        });
    }

    public function resolve(HasInventoryLocation $model): InventoryLocation
    {
        $organizationId = currentOrganizationId();
        $model = $this->resolveAndValidateModel($model);

        return InventoryLocation::query()
            ->where('organization_id', $organizationId)
            ->where('external_type', $model->getMorphClass())
            ->where('external_id', (string) $model->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @param  Collection<int, HasInventoryLocation>  $models
     * @return Collection<int, InventoryLocation>
     */
    public function ensure(Collection $models): Collection
    {
        $organizationId = currentOrganizationId();

        if ($models->isEmpty()) {
            return collect();
        }

        $models = $models
            ->map(fn (HasInventoryLocation $model): HasInventoryLocation => $this->resolveAndValidateModel($model));

        $groupedModels = $models
            ->unique(fn (HasInventoryLocation $model): string => $model->getMorphClass().':'.(string) $model->getKey())
            ->groupBy(fn (HasInventoryLocation $model): string => $model->getMorphClass());

        $resolvedLocations = collect();

        foreach ($groupedModels as $morphClass => $typedModels) {
            $externalIds = $typedModels
                ->map(fn (HasInventoryLocation $model): string => (string) $model->getKey())
                ->values();

            $existingLocations = InventoryLocation::query()
                ->where('organization_id', $organizationId)
                ->where('external_type', $morphClass)
                ->whereIn('external_id', $externalIds)
                ->get()
                ->keyBy('external_id');

            $missingModels = $typedModels
                ->reject(fn (HasInventoryLocation $model): bool => $existingLocations->has((string) $model->getKey()))
                ->values();

            if ($missingModels->isNotEmpty()) {
                $now = now();

                InventoryLocation::query()->insert(
                    $missingModels
                        ->map(fn (HasInventoryLocation $model): array => [
                            'id'              => (string) Str::uuid7(),
                            'organization_id' => $organizationId,
                            'external_type'   => $model->getMorphClass(),
                            'external_id'     => (string) $model->getKey(),
                            'is_active'       => true,
                            'created_at'      => $now,
                            'updated_at'      => $now,
                        ])
                        ->all()
                );

                $existingLocations = InventoryLocation::query()
                    ->where('organization_id', $organizationId)
                    ->where('external_type', $morphClass)
                    ->whereIn('external_id', $externalIds)
                    ->get()
                    ->keyBy('external_id');
            }

            $resolvedLocations = $resolvedLocations->merge(
                $typedModels->map(fn (HasInventoryLocation $model): InventoryLocation => $existingLocations->get((string) $model->getKey()))
            );
        }

        return $resolvedLocations->values();
    }

    protected function resolveAndValidateModel(HasInventoryLocation $model): HasInventoryLocation
    {
        $resolution = $this->organizationScopeResolver->resolve($model);

        if (is_string($resolution)) {
            throw match ($resolution) {
                OrganizationScopeResolver::NoModelKey,
                OrganizationScopeResolver::NoOrganizationContext,
                OrganizationScopeResolver::NoOrganizationId     => OrganizationScopeException::resolutionFailed(),
                OrganizationScopeResolver::OrganizationMismatch => OrganizationScopeException::mismatch(),
                default                                         => OrganizationScopeException::resolutionFailed(),
            };
        }

        return $resolution;
    }
}

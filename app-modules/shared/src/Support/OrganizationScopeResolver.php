<?php

declare(strict_types=1);

namespace Lahatre\Shared\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class OrganizationScopeResolver
{
    public const string NoModelKey = 'no_model_key';

    public const string NoOrganizationContext = 'no_organization_context';

    public const string NoOrganizationId = 'no_organization_id';

    public const string OrganizationMismatch = 'organization_mismatch';

    /**
     * Resolve the persisted model and validate its organization scope.
     *
     * @template TModel of object
     *
     * @param  TModel  $model
     * @return TModel|string
     */
    public function resolve(object $model): object|string
    {
        if (!$model instanceof Model) {
            throw new \InvalidArgumentException('The model must extend '.Model::class.'.');
        }

        if (!Schema::hasColumn($model->getTable(), 'organization_id')) {
            return self::NoOrganizationId;
        }

        try {
            $organizationId = currentOrganizationId();
        } catch (AuthorizationException) {
            return self::NoOrganizationContext;
        }

        if ($model->getKey() === null) {
            return self::NoModelKey;
        }

        $persistedAttributes = $model->newQuery()
            ->whereKey($model->getKey())
            ->firstOrFail()
            ->getAttributes();
        $persistedModel = clone $model;
        $persistedModel->setRawAttributes($persistedAttributes, true);
        $persistedOrganizationId = (string) $persistedModel->getAttribute('organization_id');

        if ($persistedOrganizationId === '') {
            return self::NoOrganizationId;
        }

        if ($persistedOrganizationId !== $organizationId) {
            return self::OrganizationMismatch;
        }

        return $persistedModel;
    }

    /**
     * Resolve many persisted models and validate their organization scope,
     * with one read per model class whatever their count. The checks are those
     * of resolve(); the first failure met is returned.
     *
     * @template TModel of object
     *
     * @param  Collection<int, TModel>  $models
     * @return Collection<int, TModel>|string the persisted models in the given order, or the failure
     */
    public function resolveMany(Collection $models): Collection|string
    {
        foreach ($models as $model) {
            if (!$model instanceof Model) {
                throw new \InvalidArgumentException('The model must extend '.Model::class.'.');
            }
        }

        /** @var Collection<int, Model> $models */
        $modelsByClass = $models->groupBy(fn (Model $model): string => $model::class);

        foreach ($modelsByClass as $classModels) {
            if (!Schema::hasColumn($classModels->first()->getTable(), 'organization_id')) {
                return self::NoOrganizationId;
            }
        }

        try {
            $organizationId = currentOrganizationId();
        } catch (AuthorizationException) {
            return self::NoOrganizationContext;
        }

        if ($models->contains(fn (Model $model): bool => $model->getKey() === null)) {
            return self::NoModelKey;
        }

        $persistedAttributes = $modelsByClass->map(function (Collection $classModels): Collection {
            $prototype = $classModels->first();
            $keys = $classModels->map(fn (Model $model): mixed => $model->getKey())->unique()->values();
            $rows = $prototype->newQuery()->whereKey($keys->all())->get()->keyBy($prototype->getKeyName());
            $missingKeys = $keys->reject(fn (mixed $key): bool => $rows->has($key))->values()->all();

            if ($missingKeys !== []) {
                throw new ModelNotFoundException()->setModel($prototype::class, $missingKeys);
            }

            return $rows->map(fn (Model $row): array => $row->getAttributes());
        });

        $persistedModels = collect();

        foreach ($models as $model) {
            $persistedModel = clone $model;
            $persistedModel->setRawAttributes($persistedAttributes->get($model::class)->get($model->getKey()), true);
            $persistedOrganizationId = (string) $persistedModel->getAttribute('organization_id');

            if ($persistedOrganizationId === '') {
                return self::NoOrganizationId;
            }

            if ($persistedOrganizationId !== $organizationId) {
                return self::OrganizationMismatch;
            }

            $persistedModels->push($persistedModel);
        }

        return $persistedModels;
    }
}

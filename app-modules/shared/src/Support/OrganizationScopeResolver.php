<?php

declare(strict_types=1);

namespace Lahatre\Shared\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
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
}

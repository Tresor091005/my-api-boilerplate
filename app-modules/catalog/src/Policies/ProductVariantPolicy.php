<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Catalog\Models\ProductVariant;

class ProductVariantPolicy
{
    public function list(Authorizable $user): bool
    {
        return authContext()->memberRole()->hasPermissionTo('product_variants.list');
    }

    public function retrieve(Authorizable $user, ProductVariant $model): bool
    {
        return $model->organization_id === getPermissionsTeamId()
            && authContext()->memberRole()->hasPermissionTo('product_variants.retrieve');
    }

    public function create(Authorizable $user): bool
    {
        return authContext()->memberRole()->hasPermissionTo('product_variants.create');
    }

    public function update(Authorizable $user, ProductVariant $model): bool
    {
        return $model->organization_id === getPermissionsTeamId()
            && authContext()->memberRole()->hasPermissionTo('product_variants.update');
    }

    public function delete(Authorizable $user, ProductVariant $model): bool
    {
        return $model->organization_id === getPermissionsTeamId()
            && authContext()->memberRole()->hasPermissionTo('product_variants.delete');
    }

    public function restore(Authorizable $user, ProductVariant $model): bool
    {
        return false;
    }

    public function forceDelete(Authorizable $user, ProductVariant $model): bool
    {
        return false;
    }
}

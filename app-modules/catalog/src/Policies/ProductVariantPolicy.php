<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Shared\Policies\BasePolicy;

class ProductVariantPolicy extends BasePolicy
{
    public function list(Authorizable $user): bool
    {
        return $this->canModel('list', ProductVariant::class);
    }

    public function retrieve(Authorizable $user, ProductVariant $model): bool
    {
        return $this->canOnModel('retrieve', $model);
    }

    public function create(Authorizable $user): bool
    {
        return $this->canModel('create', ProductVariant::class);
    }

    public function update(Authorizable $user, ProductVariant $model): bool
    {
        return $this->canOnModel('update', $model);
    }

    public function delete(Authorizable $user, ProductVariant $model): bool
    {
        return $this->canOnModel('delete', $model);
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

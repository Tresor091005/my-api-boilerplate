<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\CatalogItemPrice;
use Lahatre\Shared\Policies\BasePolicy;

/**
 * Prices have their own permissions: who sets prices is often not who
 * describes the items. Collection abilities receive the priced item.
 */
class CatalogItemPricePolicy extends BasePolicy
{
    public function list(Authorizable $user, CatalogItem $catalogItem): bool
    {
        return $this->isCurrentOrganizationItem($catalogItem) && $this->canModel('list', CatalogItemPrice::class);
    }

    public function create(Authorizable $user, CatalogItem $catalogItem): bool
    {
        return $this->isCurrentOrganizationItem($catalogItem) && $this->canModel('create', CatalogItemPrice::class);
    }

    public function update(Authorizable $user, CatalogItemPrice $model): bool
    {
        return $this->canOnModel('update', $model);
    }

    public function delete(Authorizable $user, CatalogItem $catalogItem): bool
    {
        return $this->isCurrentOrganizationItem($catalogItem) && $this->canModel('delete', CatalogItemPrice::class);
    }

    public function restore(Authorizable $user, CatalogItemPrice $model): bool
    {
        return false;
    }

    public function forceDelete(Authorizable $user, CatalogItemPrice $model): bool
    {
        return false;
    }

    private function isCurrentOrganizationItem(CatalogItem $catalogItem): bool
    {
        return $catalogItem->organization_id === currentOrganizationId();
    }
}

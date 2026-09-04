<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Catalog\Models\StockTransfer;
use Lahatre\Shared\Policies\BasePolicy;

class StockTransferPolicy extends BasePolicy
{
    public function list(Authorizable $user): bool
    {
        return $this->canModel('list', StockTransfer::class);
    }

    public function retrieve(Authorizable $user, StockTransfer $model): bool
    {
        return $this->canOnModel('retrieve', $model);
    }

    public function create(Authorizable $user): bool
    {
        return $this->canModel('create', StockTransfer::class);
    }

    public function update(Authorizable $user, StockTransfer $model): bool
    {
        return $this->canOnModel('update', $model);
    }

    public function delete(Authorizable $user, StockTransfer $model): bool
    {
        return $this->canOnModel('delete', $model);
    }

    public function complete(Authorizable $user, StockTransfer $model): bool
    {
        return $this->canOnModel('complete', $model);
    }

    public function cancel(Authorizable $user, StockTransfer $model): bool
    {
        return $this->canOnModel('cancel', $model);
    }
}

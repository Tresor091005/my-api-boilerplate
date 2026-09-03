<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

class BundleStockOperationCollection extends BaseCollection
{
    public $collects = BundleStockOperationResource::class;
}

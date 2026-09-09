<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Contracts;

use Illuminate\Support\Collection;

interface CatalogInterface
{
    /**
     * @return Collection<int, array{id: string, name: string, position: int}>
     */
    public function serviceCommitmentTemplates(string $serviceId): Collection;
}

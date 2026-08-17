<?php

declare(strict_types=1);

namespace Lahatre\Master\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @phpstan-require-extends Model
 */
interface HasTags
{
    public function tags(): MorphToMany;

    public function getOrganizationId(): string;
}

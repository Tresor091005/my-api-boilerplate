<?php

declare(strict_types=1);

namespace Lahatre\Master\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Lahatre\Master\Models\Tag;

/**
 * @phpstan-require-extends Model
 */
interface HasTags
{
    /**
     * @return MorphToMany<Tag, Model>
     */
    public function tags(): MorphToMany;

    public function getOrganizationId(): string;
}

<?php

declare(strict_types=1);

namespace Lahatre\Library\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Library\Database\Factories\FolderFactory;
use Lahatre\Shared\Traits\SharedTraits;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string|null $parent_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, Folder> $children
 * @property-read Collection<int, File> $files
 * @property-read Folder|null $parent
 *
 * @method static Builder<static>|Folder newModelQuery()
 * @method static Builder<static>|Folder newQuery()
 * @method static Builder<static>|Folder query()
 * @method static FolderFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class Folder extends Model
{
    use HasRecursiveRelationships;
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'library_folders';

    protected $fillable = [
        'organization_id',
        'name',
        'parent_id',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'name'            => 'string',
        'parent_id'       => 'string',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    public function files(): HasMany
    {
        return $this->hasMany(File::class, 'folder_id');
    }
}

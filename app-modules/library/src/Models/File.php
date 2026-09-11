<?php

declare(strict_types=1);

namespace Lahatre\Library\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Library\Database\Factories\FileFactory;
use Lahatre\Library\Enums\FileKind;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string|null $folder_id
 * @property string $name
 * @property string $original_name
 * @property string $mime_type
 * @property string|null $extension
 * @property int $size
 * @property string $storage_disk
 * @property string $storage_key
 * @property string $checksum
 * @property string $uploaded_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Folder|null $folder
 *
 * @method static Builder<static>|File newModelQuery()
 * @method static Builder<static>|File newQuery()
 * @method static Builder<static>|File query()
 * @method static FileFactory factory($count = null, $state = [])
 * @method static Builder<static>|File onlyTrashed()
 * @method static Builder<static>|File withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|File withoutTrashed()
 *
 * @mixin \Eloquent
 */
class File extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'library_files';

    protected $fillable = [
        'organization_id',
        'folder_id',
        'name',
        'mime_type',
        'extension',
        'size',
        'storage_disk',
        'storage_key',
        'checksum',
        'uploaded_by',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'folder_id'       => 'string',
        'name'            => 'string',
        'original_name'   => 'string',
        'mime_type'       => 'string',
        'extension'       => 'string',
        'size'            => 'integer',
        'storage_disk'    => 'string',
        'storage_key'     => 'string',
        'checksum'        => 'string',
        'uploaded_by'     => 'string',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'folder_id');
    }

    public function kind(): FileKind
    {
        return FileKind::fromMimeType($this->mime_type);
    }

    public function isImage(): bool
    {
        return $this->kind() === FileKind::Image;
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function isVideo(): bool
    {
        return $this->kind() === FileKind::Video;
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Library\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $file_id
 * @property string $attachable_type
 * @property string $attachable_id
 * @property string $slot
 * @property int $position
 * @property-read File|null $file
 */
class FileAttachment extends Model
{
    use SharedTraits;

    protected $table = 'library_file_attachments';

    protected $fillable = [
        'organization_id', 'file_id', 'attachable_type', 'attachable_id', 'slot', 'position',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'file_id'         => 'string',
        'attachable_type' => 'string',
        'attachable_id'   => 'string',
        'slot'            => 'string',
        'position'        => 'integer',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id')
            ->where('library_files.organization_id', currentOrganizationId());
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function linkedFile(): File
    {
        return $this->file ?? throw (new ModelNotFoundException)->setModel(File::class, [$this->file_id]);
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Catalog\Database\Factories\ServiceFactory;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Library\Contracts\HasFileSlots;
use Lahatre\Library\Models\FileAttachment;
use Lahatre\Library\Traits\InteractsWithFile;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $handle
 * @property string $name
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read CatalogItem $catalogItem
 * @property-read Collection<int, ServiceDeliverableTemplate> $deliverableTemplates
 * @property-read int|null $deliverable_templates_count
 * @property-read Collection<int, FileAttachment> $fileAttachments
 * @property-read int|null $file_attachments_count
 * @property-read Collection<int, FileAttachment> $galleryFileAttachments
 * @property-read int|null $gallery_file_attachments_count
 * @property-read Collection<int, FileAttachment> $mainFileAttachments
 * @property-read int|null $main_file_attachments_count
 *
 * @method static ServiceFactory factory($count = null, $state = [])
 * @method static Builder<static>|Service newModelQuery()
 * @method static Builder<static>|Service newQuery()
 * @method static Builder<static>|Service onlyTrashed()
 * @method static Builder<static>|Service query()
 * @method static Builder<static>|Service whereCreatedAt($value)
 * @method static Builder<static>|Service whereDeletedAt($value)
 * @method static Builder<static>|Service whereHandle($value)
 * @method static Builder<static>|Service whereId($value)
 * @method static Builder<static>|Service whereName($value)
 * @method static Builder<static>|Service whereOrganizationId($value)
 * @method static Builder<static>|Service whereUpdatedAt($value)
 * @method static Builder<static>|Service withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Service withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Service extends Model implements HasFileSlots
{
    use InteractsWithFile;
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'catalog_services';

    protected $fillable = ['organization_id', 'handle', 'name'];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'handle'          => 'string',
        'name'            => 'string',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    /** @return array<string, array{max_files: int, mime_types: list<string>}> */
    public function fileSlots(): array
    {
        $images = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        return [
            'main'    => ['max_files' => 1, 'mime_types' => $images],
            'gallery' => ['max_files' => 20, 'mime_types' => $images],
        ];
    }

    /** @return MorphMany<FileAttachment, $this> */
    public function mainFileAttachments(): MorphMany
    {
        return $this->fileAttachmentsForSlot('main');
    }

    /** @return MorphMany<FileAttachment, $this> */
    public function galleryFileAttachments(): MorphMany
    {
        return $this->fileAttachmentsForSlot('gallery');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'id', 'id')
            ->where('catalog_items.item_type', CatalogItemType::Service->value)
            ->where('catalog_items.organization_id', currentOrganizationId());
    }

    /** @return HasMany<ServiceDeliverableTemplate, $this> */
    public function deliverableTemplates(): HasMany
    {
        return $this->hasMany(ServiceDeliverableTemplate::class, 'service_id', 'id')
            ->where('catalog_service_deliverable_templates.organization_id', currentOrganizationId())
            ->orderBy('position');
    }
}

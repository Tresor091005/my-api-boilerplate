<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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

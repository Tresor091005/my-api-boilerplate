<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Exceptions\CatalogFileException;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\Service;
use Lahatre\Library\Contracts\LibraryInterface;
use Lahatre\Library\Models\FileAttachment;

final readonly class CatalogFileService
{
    public function __construct(private LibraryInterface $attachments) {}

    /** @param list<string> $fileIds */
    public function setMain(Product|Service $parent, array $fileIds): Product|Service
    {
        DB::transaction(function () use ($parent, $fileIds): void {
            $this->assertServiceActive($parent);
            $this->attachments->replaceAttachments($parent, 'main', $fileIds);
        });

        return $parent->load(responseRelationsToLoad());
    }

    /** @param list<string> $fileIds */
    public function addGallery(Product|Service $parent, array $fileIds): Product|Service
    {
        DB::transaction(function () use ($parent, $fileIds): void {
            $this->assertServiceActive($parent);
            $this->attachments->addAttachments($parent, 'gallery', $fileIds);
        });

        return $parent->load(responseRelationsToLoad());
    }

    /** @param list<string> $attachmentIds */
    public function reorderGallery(Product|Service $parent, array $attachmentIds): Product|Service
    {
        DB::transaction(function () use ($parent, $attachmentIds): void {
            $this->assertServiceActive($parent);
            $this->attachments->reorderAttachments($parent, 'gallery', $attachmentIds);
        });

        return $parent->load(responseRelationsToLoad());
    }

    /** @param list<string> $attachmentIds */
    public function removeGallery(Product|Service $parent, array $attachmentIds): void
    {
        DB::transaction(function () use ($parent, $attachmentIds): void {
            $this->assertServiceActive($parent);
            $this->attachments->removeAttachments($parent, 'gallery', $attachmentIds);
        });
    }

    public function find(Product|Service $parent, string $attachmentId): FileAttachment
    {
        return $this->attachments->find($parent, $attachmentId);
    }

    private function assertServiceActive(Product|Service $parent): void
    {
        if ($parent instanceof Product) {
            return;
        }

        $catalogItem = CatalogItem::query()
            ->where('organization_id', currentOrganizationId())
            ->whereKey($parent->getKey())
            ->where('item_type', CatalogItemType::Service->value)
            ->lockForUpdate()
            ->firstOrFail();

        if (!$catalogItem->is_active) {
            throw CatalogFileException::serviceInactive($parent->id);
        }
    }
}

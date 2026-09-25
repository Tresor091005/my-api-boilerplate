<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Catalog\Http\Resources\Concerns\RendersCatalogItem;
use Lahatre\Catalog\Models\Service;
use Lahatre\Library\Http\Resources\FileAttachmentResource;

/** @mixin Service */
final class ServiceResource extends JsonResource
{
    use RendersCatalogItem;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'handle' => $this->handle,
            'name'   => $this->name,
            ...$this->catalogItemFields(),
            'created_at'            => $this->created_at,
            'updated_at'            => $this->updated_at,
            'deliverable_templates' => $this->whenLoaded(
                'deliverableTemplates',
                fn ($templates): JsonResource => ServiceDeliverableTemplateResource::collection($templates),
            ),
            'files' => $this->when(
                $this->resource->relationLoaded('mainFileAttachments') || $this->resource->relationLoaded('galleryFileAttachments'),
                function (): array {
                    $render = fn ($attachment): FileAttachmentResource => new FileAttachmentResource(
                        $attachment, 'lahatre.catalog.services.files.content', 'service', $this->id,
                    );

                    return [
                        'main'    => $this->whenLoaded('mainFileAttachments', fn ($attachments): array => $attachments->map($render)->all()),
                        'gallery' => $this->whenLoaded('galleryFileAttachments', fn ($attachments): array => $attachments->map($render)->all()),
                    ];
                },
            ),
            ...$this->catalogItemRelations(),
        ];
    }
}

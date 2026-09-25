<?php

declare(strict_types=1);

namespace Lahatre\Library\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Library\Contracts\HasFileSlots;
use Lahatre\Library\Models\File;
use Lahatre\Library\Models\FileAttachment;
use Lahatre\Library\Traits\InteractsWithFile;
use LogicException;

/**
 * @extends Factory<FileAttachment>
 */
class FileAttachmentFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (FileAttachment $attachment): void {
            if (!$attachment->getAttribute('attachable_type') || !$attachment->getAttribute('attachable_id')) {
                throw new LogicException('FileAttachment::factory() requires forParent() with a persisted model using InteractsWithFile.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => currentOrganizationId(),
            'file_id'         => null,
            'attachable_type' => null,
            'attachable_id'   => null,
            'slot'            => 'main',
            'position'        => 1,
        ];
    }

    public function forParent(HasFileSlots $parent): static
    {
        if (!in_array(InteractsWithFile::class, class_uses_recursive($parent::class), true)) {
            throw new LogicException('The attachment parent must use InteractsWithFile.');
        }

        if (!$parent->exists) {
            throw new LogicException('The attachment parent must be persisted before calling forParent().');
        }

        return $this->state(fn (): array => [
            'organization_id' => $parent->getAttribute('organization_id'),
            'file_id'         => File::factory()->state(['organization_id' => $parent->getAttribute('organization_id')]),
            'attachable_type' => $parent->getMorphClass(),
            'attachable_id'   => $parent->getKey(),
            'slot'            => array_key_first($parent->fileSlots()),
        ]);
    }
}

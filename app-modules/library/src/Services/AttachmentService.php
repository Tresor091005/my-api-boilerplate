<?php

declare(strict_types=1);

namespace Lahatre\Library\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Library\Contracts\HasFileSlots;
use Lahatre\Library\Exceptions\LibraryException;
use Lahatre\Library\Models\File;
use Lahatre\Library\Models\FileAttachment;
use Lahatre\Library\Traits\InteractsWithFile;
use LogicException;

final readonly class AttachmentService
{
    /** @param array<int, string> $fileIds */
    public function replaceAttachments(HasFileSlots $parent, string $slot, array $fileIds): void
    {
        $fileIds = array_values($fileIds);
        $this->assertFileIds($slot, $fileIds);

        DB::transaction(function () use ($parent, $slot, $fileIds): void {
            $files = $this->lockFiles($fileIds);
            $lockedParent = $this->lockParent($parent);
            $definition = $this->slotDefinition($lockedParent, $slot);
            $this->assertFilesAllowed($slot, $fileIds, $files, $definition, count($fileIds));
            $attachments = $this->parentAttachments($lockedParent)->where('slot', $slot);
            $existing = (clone $attachments)->lockForUpdate()->pluck('file_id')->all();

            if ($existing === $fileIds) {
                return;
            }

            $attachments->delete();
            $this->insertAttachments($lockedParent, $slot, $fileIds, 1);
        });
    }

    /** @param list<string> $fileIds */
    public function addAttachments(HasFileSlots $parent, string $slot, array $fileIds): void
    {
        if ($fileIds === []) {
            throw LibraryException::attachmentSelectionInvalid($slot);
        }

        $this->assertFileIds($slot, $fileIds);

        DB::transaction(function () use ($parent, $slot, $fileIds): void {
            $files = $this->lockFiles($fileIds);
            $lockedParent = $this->lockParent($parent);
            $definition = $this->slotDefinition($lockedParent, $slot);
            $existing = $this->parentAttachments($lockedParent)->where('slot', $slot)
                ->lockForUpdate()->get(['file_id', 'position']);
            $this->assertFilesAllowed($slot, $fileIds, $files, $definition, $existing->count() + count($fileIds));
            $existingFileIds = $existing->pluck('file_id')->flip();

            foreach ($fileIds as $fileId) {
                if ($existingFileIds->has($fileId)) {
                    throw LibraryException::attachmentDuplicate($slot, $fileId);
                }
            }

            $this->insertAttachments($lockedParent, $slot, $fileIds, (int) $existing->max('position') + 1);
        });
    }

    /** @param list<string> $attachmentIds */
    public function reorderAttachments(HasFileSlots $parent, string $slot, array $attachmentIds): void
    {
        DB::transaction(function () use ($parent, $slot, $attachmentIds): void {
            $lockedParent = $this->lockParent($parent);
            $this->slotDefinition($lockedParent, $slot);
            $attachments = $this->parentAttachments($lockedParent)
                ->where('slot', $slot)->lockForUpdate()->get()->keyBy('id');

            if (count($attachmentIds) !== $attachments->count()
                || count(array_unique($attachmentIds)) !== count($attachmentIds)
                || array_diff($attachmentIds, $attachments->keys()->all()) !== []) {
                throw LibraryException::attachmentOrderInvalid($slot);
            }

            $this->updateMultiplePositions($lockedParent, $slot, $attachmentIds);
        });
    }

    /** @param list<string> $attachmentIds */
    public function removeAttachments(HasFileSlots $parent, string $slot, array $attachmentIds): void
    {
        if ($attachmentIds === [] || count(array_unique($attachmentIds)) !== count($attachmentIds)) {
            throw LibraryException::attachmentSelectionInvalid($slot);
        }

        foreach ($attachmentIds as $attachmentId) {
            $this->assertAttachmentId($attachmentId);
        }

        DB::transaction(function () use ($parent, $slot, $attachmentIds): void {
            $lockedParent = $this->lockParent($parent);
            $this->slotDefinition($lockedParent, $slot);
            $attachments = $this->parentAttachments($lockedParent)->where('slot', $slot);
            $foundIds = (clone $attachments)->whereIn('id', $attachmentIds)->lockForUpdate()->pluck('id')->all();

            if (count($foundIds) !== count($attachmentIds)) {
                throw (new ModelNotFoundException)->setModel(FileAttachment::class, array_values(array_diff($attachmentIds, $foundIds)));
            }

            (clone $attachments)->whereIn('id', $attachmentIds)->delete();
            $this->updateMultiplePositions($lockedParent, $slot, $attachments->pluck('id')->all());
        });
    }

    public function find(HasFileSlots $parent, string $attachmentId): FileAttachment
    {
        $this->assertAttachmentId($attachmentId);

        return $this->parentAttachments($parent)
            ->whereKey($attachmentId)
            ->with('file')
            ->firstOrFail();
    }

    public function detachAll(HasFileSlots $parent): void
    {
        DB::transaction(function () use ($parent): void {
            $this->parentAttachments($parent)->delete();
        });
    }

    /** @param list<string> $fileIds */
    private function assertFileIds(string $slot, array $fileIds): void
    {
        $seen = [];

        foreach ($fileIds as $fileId) {
            if (!Str::isUuid($fileId)) {
                throw (new ModelNotFoundException)->setModel(File::class, [$fileId]);
            }

            if (isset($seen[$fileId])) {
                throw LibraryException::attachmentDuplicate($slot, $fileId);
            }

            $seen[$fileId] = true;
        }
    }

    /** @return array{max_files: int, mime_types: list<string>} */
    private function slotDefinition(HasFileSlots $parent, string $slot): array
    {
        return $parent->fileSlots()[$slot] ?? throw LibraryException::attachmentSlotUnavailable($slot);
    }

    /**
     * @param  list<string>  $fileIds
     * @param  Collection<string, File>  $files
     * @param  array{max_files: int, mime_types: list<string>}  $definition
     */
    private function assertFilesAllowed(string $slot, array $fileIds, Collection $files, array $definition, int $finalCount): void
    {
        if ($finalCount > $definition['max_files']) {
            throw LibraryException::attachmentLimitExceeded($slot, $definition['max_files']);
        }

        foreach ($fileIds as $fileId) {
            $file = $files->get($fileId) ?? throw (new ModelNotFoundException)->setModel(File::class, [$fileId]);

            if (!in_array($file->mime_type, $definition['mime_types'], true)) {
                throw LibraryException::attachmentMimeTypeNotAllowed($slot, $file->mime_type);
            }
        }
    }

    /** @param list<string> $fileIds */
    private function insertAttachments(HasFileSlots $parent, string $slot, array $fileIds, int $firstPosition): void
    {
        if ($fileIds === []) {
            return;
        }

        $timestamp = now();
        $rows = [];

        foreach ($fileIds as $fileId) {
            $rows[] = [
                'id'              => (string) Str::uuid7(),
                'organization_id' => $parent->getAttribute('organization_id'),
                'file_id'         => $fileId,
                'attachable_type' => $parent->getMorphClass(),
                'attachable_id'   => $parent->getKey(),
                'slot'            => $slot,
                'position'        => $firstPosition++,
                'created_at'      => $timestamp,
                'updated_at'      => $timestamp,
            ];
        }

        DB::table('library_file_attachments')->insert($rows);
    }

    private function assertAttachmentId(string $attachmentId): void
    {
        if (!Str::isUuid($attachmentId)) {
            throw (new ModelNotFoundException)->setModel(FileAttachment::class, [$attachmentId]);
        }
    }

    /** @param list<string> $attachmentIds */
    private function updateMultiplePositions(HasFileSlots $parent, string $slot, array $attachmentIds): void
    {
        foreach (array_chunk($attachmentIds, 500, true) as $positions) {
            $values = [];
            $bindings = [];

            foreach ($positions as $index => $attachmentId) {
                $values[] = '(?::uuid, ?::integer)';
                $bindings[] = $attachmentId;
                $bindings[] = $index + 1;
            }

            DB::update(
                'UPDATE library_file_attachments AS attachment '
                .'SET position = positions.position, updated_at = ? '
                .'FROM (VALUES '.implode(', ', $values).') AS positions(id, position) '
                .'WHERE attachment.id = positions.id '
                .'AND attachment.organization_id = ? '
                .'AND attachment.attachable_type = ? '
                .'AND attachment.attachable_id = ? '
                .'AND attachment.slot = ?',
                [now(), ...$bindings, $parent->getAttribute('organization_id'), $parent->getMorphClass(), $parent->getKey(), $slot],
            );
        }
    }

    /**
     * @param  list<string>  $fileIds
     * @return Collection<string, File>
     */
    private function lockFiles(array $fileIds): Collection
    {
        return File::query()
            ->where('organization_id', currentOrganizationId())
            ->whereIn('id', $fileIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'mime_type'])
            ->keyBy('id');
    }

    private function lockParent(HasFileSlots $parent): HasFileSlots
    {
        $locked = $parent::query()
            ->where('organization_id', currentOrganizationId())
            ->whereKey($parent->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        if (!$locked instanceof HasFileSlots) {
            throw new LogicException('The attachment parent must implement HasFileSlots.');
        }

        return $locked;
    }

    /** @return MorphMany<FileAttachment, Model> */
    private function parentAttachments(HasFileSlots $parent): MorphMany
    {
        if (!in_array(InteractsWithFile::class, class_uses_recursive($parent::class), true)
            || !method_exists($parent, 'fileAttachments')) {
            throw new LogicException('The attachment parent must use InteractsWithFile.');
        }

        return $parent->fileAttachments();
    }
}

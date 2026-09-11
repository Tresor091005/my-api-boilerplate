<?php

declare(strict_types=1);

namespace Lahatre\Library\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Lahatre\Library\Models\File;
use Lahatre\Library\Models\Folder;
use Lahatre\Library\ViewData\LibraryReconciliationReport;
use Throwable;

final class LibraryMaintenanceService
{
    /**
     * Reconcile one organization, or all organizations when no identifier is supplied.
     *
     * This is an explicit system-maintenance boundary and must not be called from
     * an organization-scoped HTTP request without an organization identifier.
     */
    public function reconcile(
        ?string $organizationId,
        bool $deleteOrphans,
        bool $verifyChecksums,
        int $orphanGraceHours,
    ): LibraryReconciliationReport {
        $missingFileIds = [];
        $corruptedFileIds = [];
        $deletedObjectsRemoved = 0;
        $purgedFilesRemoved = 0;
        $purgedFoldersRemoved = 0;
        $orphanObjectsFound = 0;
        $orphanObjectsRemoved = 0;

        $this->activeFilesQuery($organizationId)
            ->orderBy('id')
            ->chunkById(200, function (Collection $files) use (&$missingFileIds, &$corruptedFileIds, $verifyChecksums): void {
                foreach ($files as $file) {
                    $disk = Storage::disk($file->storage_disk);

                    if (!$disk->exists($file->storage_key)) {
                        // TODO: Decide whether missing active file bytes should be marked, restored, or otherwise repaired automatically.
                        $missingFileIds[] = $file->id;

                        continue;
                    }

                    if ($verifyChecksums && !$this->checksumMatches($disk, $file)) {
                        // TODO: Decide how checksum mismatches should be handled beyond reporting the affected file.
                        $corruptedFileIds[] = $file->id;
                    }
                }
            });

        $purgeResult = $this->purgeExpiredFiles(
            $organizationId,
            now()->subDays((int) config('library.maintenance.trash_retention_days', 30)),
        );
        $deletedObjectsRemoved = $purgeResult['deleted_objects_removed'];
        $purgedFilesRemoved = $purgeResult['purged_files_removed'];
        $purgedFoldersRemoved = $this->purgeExpiredFolders(
            $organizationId,
            now()->subDays((int) config('library.maintenance.trash_retention_days', 30)),
        );

        foreach ($this->storageDisks($organizationId) as $diskName) {
            $disk = Storage::disk($diskName);
            $prefix = $this->organizationPrefix($organizationId);

            try {
                $physicalKeys = $disk->allFiles($prefix);
            } catch (Throwable $exception) {
                logger()->warning('Library reconciliation could not list a storage disk.', [
                    'storage_disk' => $diskName,
                    'exception'    => $exception::class,
                ]);

                continue;
            }

            foreach (array_chunk($physicalKeys, 500) as $keyChunk) {
                $knownKeys = File::withTrashed()
                    ->where('storage_disk', $diskName)
                    ->when(
                        $organizationId !== null,
                        fn (Builder $query): Builder => $query->where('organization_id', $organizationId),
                    )
                    ->whereIn('storage_key', $keyChunk)
                    ->pluck('storage_key')
                    ->all();

                foreach (array_diff($keyChunk, $knownKeys) as $orphanKey) {
                    if (!$this->isManagedStorageKey($orphanKey, $organizationId)) {
                        continue;
                    }

                    $orphanObjectsFound++;

                    if ($deleteOrphans
                        && $this->isOlderThanGracePeriod($disk, $orphanKey, $orphanGraceHours)
                        && $disk->delete($orphanKey)) {
                        $orphanObjectsRemoved++;
                    }
                }
            }
        }

        return new LibraryReconciliationReport(
            missingFileIds: $missingFileIds,
            corruptedFileIds: $corruptedFileIds,
            deletedObjectsRemoved: $deletedObjectsRemoved,
            purgedFilesRemoved: $purgedFilesRemoved,
            purgedFoldersRemoved: $purgedFoldersRemoved,
            orphanObjectsFound: $orphanObjectsFound,
            orphanObjectsRemoved: $orphanObjectsRemoved,
        );
    }

    /** @return Builder<File> */
    private function activeFilesQuery(?string $organizationId): Builder
    {
        return File::query()
            ->when(
                $organizationId !== null,
                fn (Builder $query): Builder => $query->where('organization_id', $organizationId),
            );
    }

    /** @return Builder<File> */
    private function deletedFilesQuery(?string $organizationId): Builder
    {
        return File::onlyTrashed()
            ->when(
                $organizationId !== null,
                fn (Builder $query): Builder => $query->where('organization_id', $organizationId),
            );
    }

    /** @return array{deleted_objects_removed: int, purged_files_removed: int} */
    private function purgeExpiredFiles(?string $organizationId, CarbonInterface $cutoff): array
    {
        $deletedObjectsRemoved = 0;
        $purgedFilesRemoved = 0;

        $this->deletedFilesQuery($organizationId)
            ->where('deleted_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(200, function (Collection $files) use (&$deletedObjectsRemoved, &$purgedFilesRemoved): void {
                foreach ($files as $file) {
                    $objectReadyForPurge = true;

                    try {
                        $disk = Storage::disk($file->storage_disk);
                        if ($disk->exists($file->storage_key)) {
                            $objectReadyForPurge = $disk->delete($file->storage_key);
                            if ($objectReadyForPurge) {
                                $deletedObjectsRemoved++;
                            }
                        }
                    } catch (Throwable $exception) {
                        $objectReadyForPurge = false;
                        logger()->warning('Library trash purge could not remove stored bytes.', [
                            'file_id'   => $file->id,
                            'exception' => $exception::class,
                        ]);
                    }

                    if ($objectReadyForPurge) {
                        $file->forceDelete();
                        $purgedFilesRemoved++;
                    }
                }
            });

        return [
            'deleted_objects_removed' => $deletedObjectsRemoved,
            'purged_files_removed'    => $purgedFilesRemoved,
        ];
    }

    private function purgeExpiredFolders(?string $organizationId, CarbonInterface $cutoff): int
    {
        $purgedFolders = 0;
        do {
            $purgedInPass = 0;
            Folder::onlyTrashed()
                ->when(
                    $organizationId !== null,
                    fn (Builder $query): Builder => $query->where('organization_id', $organizationId),
                )
                ->where('deleted_at', '<=', $cutoff)
                ->orderBy('id')
                ->chunkById(200, function (Collection $folders) use (&$purgedInPass): void {
                    foreach ($folders as $folder) {
                        if (File::withTrashed()->where('folder_id', $folder->id)->exists()
                            || Folder::withTrashed()->where('parent_id', $folder->id)->exists()) {
                            continue;
                        }

                        $folder->forceDelete();
                        $purgedInPass++;
                    }
                });
            $purgedFolders += $purgedInPass;
        } while ($purgedInPass > 0);

        return $purgedFolders;
    }

    /** @return list<string> */
    private function storageDisks(?string $organizationId): array
    {
        $disks = File::withTrashed()
            ->when(
                $organizationId !== null,
                fn (Builder $query): Builder => $query->where('organization_id', $organizationId),
            )
            ->distinct()
            ->pluck('storage_disk')
            ->all();
        $disks[] = (string) config('library.disk');

        return array_values(array_unique(array_filter($disks, is_string(...))));
    }

    private function organizationPrefix(?string $organizationId): string
    {
        $rootPrefix = trim((string) config('library.root_prefix', 'organizations'), '/');

        return $organizationId === null ? $rootPrefix : "{$rootPrefix}/{$organizationId}";
    }

    private function isManagedStorageKey(string $storageKey, ?string $organizationId): bool
    {
        $rootPrefix = preg_quote(trim((string) config('library.root_prefix', 'organizations'), '/'), '#');
        $organizationPattern = $organizationId === null
            ? '[0-9a-fA-F-]{36}'
            : preg_quote($organizationId, '#');

        return preg_match(
            "#^{$rootPrefix}/{$organizationPattern}/[0-9]{4}/(0[1-9]|1[0-2])/[^/]+$#",
            $storageKey,
        ) === 1;
    }

    private function isOlderThanGracePeriod(
        FilesystemAdapter $disk,
        string $storageKey,
        int $orphanGraceHours,
    ): bool {
        try {
            return $disk->lastModified($storageKey) <= now()->subHours($orphanGraceHours)->timestamp;
        } catch (Throwable) {
            return false;
        }
    }

    private function checksumMatches(FilesystemAdapter $disk, File $file): bool
    {
        $stream = $disk->readStream($file->storage_key);

        if ($stream === false) {
            return false;
        }

        $hash = hash_init('sha256');
        hash_update_stream($hash, $stream);
        fclose($stream);

        return hash_final($hash) === $file->checksum;
    }
}

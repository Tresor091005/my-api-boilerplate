<?php

declare(strict_types=1);

namespace Lahatre\Library\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lahatre\Library\Assertions\FileAssertion;
use Lahatre\Library\Assertions\FolderAssertion;
use Lahatre\Library\Data\FileFilterData;
use Lahatre\Library\Data\FileUpdateData;
use Lahatre\Library\Data\FileUploadData;
use Lahatre\Library\Enums\FileKind;
use Lahatre\Library\Exceptions\LibraryException;
use Lahatre\Library\Models\File;
use Lahatre\Library\Models\Folder;
use Lahatre\Organization\Contracts\OrganizationInterface;
use Lahatre\Shared\Data\MissingValue;

use function Lahatre\Shared\Data\withoutMissing;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final readonly class FileService
{
    private const DEFAULT_ORGANIZATION_QUOTA_BYTES = 5 * 1024 * 1024 * 1024;

    public function __construct(
        private FileAssertion $fileAssertion,
        private FolderAssertion $folderAssertion,
        private OrganizationInterface $organizationInterface,
    ) {}

    public function paginate(FileFilterData $filters): CursorPaginator
    {
        $query = File::query()
            ->where('organization_id', currentOrganizationId());

        if ($filters->folderId === null) {
            $query->whereNull('folder_id');
        } else {
            $query->where('folder_id', $filters->folderId);
        }

        if ($filters->search !== null) {
            $query->where('name', 'ilike', $filters->search.'%');
        }

        if ($filters->mimeType !== null) {
            $query->where('mime_type', $filters->mimeType);
        }

        if ($filters->kind !== null) {
            $this->applyKindFilter($query, $filters->kind);
        }

        return stableCursorPaginate($query, $filters);
    }

    public function retrieve(File $file): File
    {
        return $this->ownedFile($file->getKey());
    }

    public function paginateTrashed(FileFilterData $filters): CursorPaginator
    {
        $query = File::onlyTrashed()
            ->where('organization_id', currentOrganizationId());

        if ($filters->search !== null) {
            $query->where('name', 'ilike', $filters->search.'%');
        }

        if ($filters->mimeType !== null) {
            $query->where('mime_type', $filters->mimeType);
        }

        if ($filters->kind !== null) {
            $this->applyKindFilter($query, $filters->kind);
        }

        return stableCursorPaginate($query, $filters);
    }

    public function upload(FileUploadData $data): EloquentCollection
    {
        $organizationId = currentOrganizationId();
        $memberId = $this->currentMemberId();
        $this->fileAssertion->assertUploadAllowed($data->files);
        /** @var list<array{disk: string, key: string}> $storedObjects */
        $storedObjects = [];

        try {
            $files = DB::transaction(function () use ($data, $organizationId, $memberId, &$storedObjects) {
                $this->lockOrganization($organizationId);
                $this->resolveFolder($data->folderId, lockForUpdate: true);

                $incomingSize = array_sum(array_map(
                    static fn (UploadedFile $file): int => (int) ($file->getSize() ?: 0),
                    $data->files,
                ));
                $usedSize = (int) File::query()
                    ->where('organization_id', $organizationId)
                    ->sum('size');
                $organizationQuota = $this->organizationInterface->getLibraryQuotaBytes($organizationId)
                    ?? self::DEFAULT_ORGANIZATION_QUOTA_BYTES;
                $this->fileAssertion->assertWithinOrganizationQuota($usedSize, $incomingSize, $organizationQuota);

                $disk = (string) config('library.disk');
                $now = now();
                $rows = [];
                $ids = [];

                foreach ($data->files as $uploadedFile) {
                    $id = Str::uuid7()->toString();
                    $mimeType = (string) ($uploadedFile->getMimeType() ?: 'application/octet-stream');
                    $extension = $uploadedFile->guessExtension();
                    $storageKey = $this->storageKey($organizationId, $id, $extension, $now->format('Y/m'));
                    $originalName = $this->safeOriginalName($uploadedFile);

                    $storedPath = Storage::disk($disk)->putFileAs(
                        dirname($storageKey),
                        $uploadedFile,
                        basename($storageKey),
                    );

                    if ($storedPath === false) {
                        throw LibraryException::storageWriteFailed($originalName, $disk);
                    }

                    $storedObjects[] = ['disk' => $disk, 'key' => $storageKey];
                    $ids[] = $id;
                    $rows[] = [
                        'id'              => $id,
                        'organization_id' => $organizationId,
                        'folder_id'       => $data->folderId,
                        'name'            => $originalName,
                        'original_name'   => $originalName,
                        'mime_type'       => $mimeType,
                        'extension'       => $extension,
                        'size'            => (int) ($uploadedFile->getSize() ?: 0),
                        'storage_disk'    => $disk,
                        'storage_key'     => $storageKey,
                        'checksum'        => (string) hash_file('sha256', $uploadedFile->getPathname()),
                        'uploaded_by'     => $memberId,
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ];
                }

                File::query()->insert($rows);

                $positions = array_flip($ids);

                return File::query()
                    ->where('organization_id', $organizationId)
                    ->whereIn('id', $ids)
                    ->get()
                    ->sortBy(fn (File $file): int => $positions[$file->id])
                    ->values();
            });
        } catch (Throwable $exception) {
            foreach ($storedObjects as $storedObject) {
                try {
                    Storage::disk($storedObject['disk'])->delete($storedObject['key']);
                } catch (Throwable $cleanupException) {
                    logger()->warning('Library upload rollback could not remove stored bytes.', [
                        'storage_disk' => $storedObject['disk'],
                        'storage_key'  => $storedObject['key'],
                        'exception'    => $cleanupException::class,
                    ]);
                }
            }

            throw $exception;
        }

        return $files;
    }

    public function update(File $file, FileUpdateData $data): File
    {
        return DB::transaction(function () use ($file, $data): File {
            $this->lockOrganization(currentOrganizationId());
            $ownedFile = $this->ownedFile($file->getKey(), lockForUpdate: true);

            if (!($data->folderId instanceof MissingValue)) {
                $this->resolveFolder($data->folderId, lockForUpdate: true);
            }

            $ownedFile->fill(withoutMissing([
                'name'      => $data->name,
                'folder_id' => $data->folderId,
            ]));
            $ownedFile->save();

            return $ownedFile->fresh();
        });
    }

    public function delete(File $file): void
    {
        DB::transaction(function () use ($file): void {
            $this->lockOrganization(currentOrganizationId());
            $ownedFile = $this->ownedFile($file->getKey(), lockForUpdate: true);
            // TODO: Block deletion while active file attachments exist once the attachment table is implemented.
            $ownedFile->delete();
        });
    }

    public function restore(File $file): File
    {
        return DB::transaction(function () use ($file): File {
            $organizationId = currentOrganizationId();
            $this->lockOrganization($organizationId);
            $ownedFile = $this->ownedFile($file->getKey(), lockForUpdate: true, withTrashed: true);

            if (!$ownedFile->trashed()) {
                return $ownedFile;
            }

            $disk = Storage::disk($ownedFile->storage_disk);
            if (!$disk->exists($ownedFile->storage_key)) {
                throw new NotFoundHttpException(__('library::exceptions.file_content_missing'));
            }

            $usedSize = (int) File::query()
                ->where('organization_id', $organizationId)
                ->sum('size');
            $organizationQuota = $this->organizationInterface->getLibraryQuotaBytes($organizationId)
                ?? self::DEFAULT_ORGANIZATION_QUOTA_BYTES;
            $this->fileAssertion->assertWithinOrganizationQuota($usedSize, (int) $ownedFile->size, $organizationQuota);

            $this->restoreFolderChain($ownedFile->folder_id, $organizationId);
            $ownedFile->restore();

            return $ownedFile->fresh();
        });
    }

    private function restoreFolderChain(?string $folderId, string $organizationId): void
    {
        if ($folderId === null) {
            return;
        }

        $folders = [];
        $currentId = $folderId;
        $activeParent = null;
        while ($currentId !== null) {
            if (count($folders) >= $this->maximumFolderDepth()) {
                throw LibraryException::folderDepthExceeded($this->maximumFolderDepth());
            }

            $folder = Folder::onlyTrashed()
                ->where('organization_id', $organizationId)
                ->whereKey($currentId)
                ->lockForUpdate()
                ->first();

            if ($folder === null) {
                $activeParent = Folder::query()
                    ->where('organization_id', $organizationId)
                    ->whereKey($currentId)
                    ->first();
                break;
            }

            $folders[] = $folder;
            $currentId = $folder->parent_id;
        }

        if ($folders === []) {
            return;
        }

        $parent = $activeParent;
        foreach (array_reverse($folders) as $folder) {
            $this->folderAssertion->assertCanHaveChild($organizationId, $parent);
            $folder->name = $this->restoredFolderName($folder);
            $folder->restore();
            $parent = $folder;
        }
    }

    private function restoredFolderName(Folder $folder): string
    {
        $query = Folder::query()
            ->where('organization_id', $folder->organization_id);

        if ($folder->parent_id === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $folder->parent_id);
        }

        $siblingNames = $query->pluck('name');
        $baseNameExists = $siblingNames->contains($folder->name);

        if (!$baseNameExists) {
            return $folder->name;
        }

        $maxSuffix = 0;
        foreach ($siblingNames as $siblingName) {
            if (preg_match('/^(.*) \(([1-9]\d*)\)$/u', $siblingName, $matches) !== 1) {
                continue;
            }

            $suffix = (int) $matches[2];
            $suffixText = " ({$suffix})";
            $expectedBaseName = mb_substr($folder->name, 0, 100 - mb_strlen($suffixText));

            if ($matches[1] === $expectedBaseName) {
                $maxSuffix = max($maxSuffix, $suffix);
            }
        }

        $nextSuffix = $maxSuffix + 1;
        $suffix = " ({$nextSuffix})";

        return mb_substr($folder->name, 0, 100 - mb_strlen($suffix)).$suffix;
    }

    private function maximumFolderDepth(): int
    {
        return max(1, (int) config('library.folders.max_depth', 10));
    }

    private function ownedFile(string $id, bool $lockForUpdate = false, bool $withTrashed = false): File
    {
        $query = File::query();

        if ($withTrashed) {
            $query->withTrashed();
        }

        $query
            ->where('organization_id', currentOrganizationId())
            ->whereKey($id);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    private function resolveFolder(?string $folderId, bool $lockForUpdate = false): ?Folder
    {
        if ($folderId === null) {
            return null;
        }

        $query = Folder::query()
            ->where('organization_id', currentOrganizationId())
            ->whereKey($folderId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    /** @throws LibraryException */
    private function currentMemberId(): string
    {
        $member = authContext()->member();

        if ($member === null) {
            throw LibraryException::memberContextRequired();
        }

        return (string) $member->getKey();
    }

    /** @throws LibraryException */
    private function lockOrganization(string $organizationId): void
    {
        $organization = DB::table('organization_organizations')
            ->where('id', $organizationId)
            ->lockForUpdate()
            ->first(['id']);

        if ($organization === null) {
            throw LibraryException::organizationContextInvalid($organizationId);
        }
    }

    private function storageKey(string $organizationId, string $id, ?string $extension, string $period): string
    {
        $rootPrefix = trim((string) config('library.root_prefix', 'organizations'), '/');
        $suffix = $extension === null || $extension === '' ? '' : '.'.$extension;

        return "{$rootPrefix}/{$organizationId}/{$period}/{$id}{$suffix}";
    }

    private function safeOriginalName(UploadedFile $file): string
    {
        $basename = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $withoutControlCharacters = preg_replace('/[\x00-\x1F\x7F]/u', '', $basename) ?? $basename;
        $name = Str::sanitize($withoutControlCharacters);
        $name = mb_substr($name, 0, 255);

        return $name === '' ? 'file' : $name;
    }

    /** @param Builder<File> $query */
    private function applyKindFilter(Builder $query, FileKind $kind): void
    {
        match ($kind) {
            FileKind::Image    => $query->where('mime_type', 'like', 'image/%'),
            FileKind::Video    => $query->where('mime_type', 'like', 'video/%'),
            FileKind::Audio    => $query->where('mime_type', 'like', 'audio/%'),
            FileKind::Archive  => $query->whereIn('mime_type', FileKind::archiveMimeTypes()),
            FileKind::Document => $query->where(function (Builder $query): void {
                $query->where('mime_type', 'like', 'text/%')
                    ->orWhereIn('mime_type', FileKind::documentMimeTypes());
            }),
            FileKind::Other => $query
                ->where('mime_type', 'not like', 'image/%')
                ->where('mime_type', 'not like', 'video/%')
                ->where('mime_type', 'not like', 'audio/%')
                ->where('mime_type', 'not like', 'text/%')
                ->whereNotIn('mime_type', [
                    ...FileKind::archiveMimeTypes(),
                    ...FileKind::documentMimeTypes(),
                ]),
        };
    }
}

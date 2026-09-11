<?php

declare(strict_types=1);

namespace Lahatre\Library\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lahatre\Library\Assertions\FileAssertion;
use Lahatre\Library\Data\FileFilterData;
use Lahatre\Library\Data\FileUpdateData;
use Lahatre\Library\Data\FileUploadData;
use Lahatre\Library\Enums\FileKind;
use Lahatre\Library\Exceptions\LibraryException;
use Lahatre\Library\Http\Resources\FileCollection;
use Lahatre\Library\Http\Resources\FileResource;
use Lahatre\Library\Http\Resources\TrashFileCollection;
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
        private OrganizationInterface $organizationInterface,
    ) {}

    public function paginate(FileFilterData $filters): FileCollection
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

        return new FileCollection(stableCursorPaginate($query, $filters));
    }

    public function retrieve(File $file): FileResource
    {
        return new FileResource($this->ownedFile($file->getKey()));
    }

    public function paginateTrashed(FileFilterData $filters): TrashFileCollection
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

        return new TrashFileCollection(stableCursorPaginate($query, $filters));
    }

    public function upload(FileUploadData $data): FileCollection
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
                $organizationQuota = $this->organizationInterface->quotaBytes($organizationId)
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

        return new FileCollection($files);
    }

    public function update(File $file, FileUpdateData $data): FileResource
    {
        return DB::transaction(function () use ($file, $data): FileResource {
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

            return new FileResource($ownedFile->fresh());
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

    public function restore(File $file): FileResource
    {
        return DB::transaction(function () use ($file): FileResource {
            $organizationId = currentOrganizationId();
            $this->lockOrganization($organizationId);
            $ownedFile = $this->ownedFile($file->getKey(), lockForUpdate: true, withTrashed: true);

            if (!$ownedFile->trashed()) {
                return new FileResource($ownedFile);
            }

            $disk = Storage::disk($ownedFile->storage_disk);
            if (!$disk->exists($ownedFile->storage_key)) {
                throw new NotFoundHttpException(__('library::exceptions.file_content_missing'));
            }

            $usedSize = (int) File::query()
                ->where('organization_id', $organizationId)
                ->sum('size');
            $organizationQuota = $this->organizationInterface->quotaBytes($organizationId)
                ?? self::DEFAULT_ORGANIZATION_QUOTA_BYTES;
            $this->fileAssertion->assertWithinOrganizationQuota($usedSize, (int) $ownedFile->size, $organizationQuota);

            $this->restoreFolderChain($ownedFile->folder_id);
            $ownedFile->restore();

            return new FileResource($ownedFile->fresh());
        });
    }

    private function restoreFolderChain(?string $folderId): void
    {
        if ($folderId === null) {
            return;
        }

        $folders = [];
        $currentId = $folderId;
        while ($currentId !== null) {
            $folder = Folder::withTrashed()
                ->where('organization_id', currentOrganizationId())
                ->whereKey($currentId)
                ->lockForUpdate()
                ->firstOrFail();
            $folders[] = $folder;
            $currentId = $folder->parent_id;
        }

        foreach (array_reverse($folders) as $folder) {
            if (!$folder->trashed()) {
                continue;
            }

            $folder->name = $this->restoredFolderName($folder);
            $folder->restore();
        }
    }

    private function restoredFolderName(Folder $folder): string
    {
        $counter = 0;
        do {
            $suffix = $counter === 0 ? '' : ($counter === 1 ? ' (restored)' : " (restored {$counter})");
            $candidate = mb_substr($folder->name, 0, 100 - mb_strlen($suffix)).$suffix;
            $query = Folder::query()
                ->where('organization_id', $folder->organization_id)
                ->where('name', $candidate);

            if ($folder->parent_id === null) {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $folder->parent_id);
            }

            $counter++;
        } while ($query->exists());

        return $candidate;
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

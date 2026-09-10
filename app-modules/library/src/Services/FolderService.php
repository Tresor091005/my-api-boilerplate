<?php

declare(strict_types=1);

namespace Lahatre\Library\Services;

use Illuminate\Support\Facades\DB;
use Lahatre\Library\Assertions\FolderAssertion;
use Lahatre\Library\Data\FolderCreateData;
use Lahatre\Library\Data\FolderFilterData;
use Lahatre\Library\Data\FolderUpdateData;
use Lahatre\Library\Http\Resources\FolderCollection;
use Lahatre\Library\Http\Resources\FolderResource;
use Lahatre\Library\Models\Folder;
use Lahatre\Shared\Data\MissingValue;

use function Lahatre\Shared\Data\withoutMissing;

final readonly class FolderService
{
    public function __construct(private FolderAssertion $folderAssertion) {}

    public function paginate(FolderFilterData $filters): FolderCollection
    {
        $query = Folder::query()
            ->where('organization_id', currentOrganizationId());

        if ($filters->parentId === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $filters->parentId);
        }

        if ($filters->search !== null) {
            $query->where('name', 'ilike', $filters->search.'%');
        }

        return new FolderCollection(stableCursorPaginate($query, $filters));
    }

    public function retrieve(Folder $folder): FolderResource
    {
        return new FolderResource($this->ownedFolder($folder->getKey()));
    }

    public function create(FolderCreateData $data): FolderResource
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(function () use ($data, $organizationId): FolderResource {
            $parent = $this->resolveParent($data->parentId, lockForUpdate: true);
            $this->folderAssertion->assertNameAvailable($organizationId, $data->name, $parent?->getKey());

            $folder = Folder::query()->create([
                'organization_id' => $organizationId,
                'name'            => $data->name,
                'parent_id'       => $parent?->getKey(),
            ]);

            return new FolderResource($folder);
        });
    }

    public function update(Folder $folder, FolderUpdateData $data): FolderResource
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(function () use ($folder, $data, $organizationId): FolderResource {
            $ownedFolder = $this->ownedFolder($folder->getKey(), lockForUpdate: true);
            $parent = $data->parentId instanceof MissingValue
                ? null
                : $this->resolveParent($data->parentId, lockForUpdate: true);
            $parentId = $data->parentId instanceof MissingValue
                ? $ownedFolder->parent_id
                : $parent?->getKey();
            $name = $data->name instanceof MissingValue ? $ownedFolder->name : $data->name;

            if (!($data->parentId instanceof MissingValue)) {
                $this->folderAssertion->assertCanMoveTo($ownedFolder, $parent);
            }

            $this->folderAssertion->assertNameAvailable(
                $organizationId,
                $name,
                $parentId,
                $ownedFolder->getKey(),
            );

            $ownedFolder->fill(withoutMissing([
                'name'      => $data->name,
                'parent_id' => $data->parentId,
            ]));
            $ownedFolder->save();

            return new FolderResource($ownedFolder->fresh());
        });
    }

    public function delete(Folder $folder): void
    {
        DB::transaction(function () use ($folder): void {
            $ownedFolder = $this->ownedFolder($folder->getKey(), lockForUpdate: true);
            $this->folderAssertion->assertCanDelete($ownedFolder);
            $ownedFolder->delete();
        });
    }

    private function resolveParent(?string $parentId, bool $lockForUpdate = false): ?Folder
    {
        if ($parentId === null) {
            return null;
        }

        return $this->ownedFolder($parentId, $lockForUpdate);
    }

    private function ownedFolder(string $id, bool $lockForUpdate = false): Folder
    {
        $query = Folder::query()
            ->where('organization_id', currentOrganizationId())
            ->whereKey($id);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }
}

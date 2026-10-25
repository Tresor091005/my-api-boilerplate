<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Library\Http\Resources\FileAttachmentResource;
use Lahatre\Library\Http\Resources\FileResource;
use Lahatre\Library\Http\Resources\FolderResource;
use Lahatre\Library\Http\Resources\TrashFileResource;
use Lahatre\Library\Models\File;
use Lahatre\Library\Models\FileAttachment;
use Lahatre\Library\Models\Folder;
use Lahatre\Organization\Models\Organization;
use Lahatre\ResponseSelection\Testing\AssertsBoundedQueries;
use Lahatre\ResponseSelection\Testing\AssertsResponseFieldIsolation;
use Lahatre\ResponseSelection\Testing\RendersApiResources;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class, AssertsBoundedQueries::class, AssertsResponseFieldIsolation::class, RendersApiResources::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->organization = Organization::factory()->create(['name' => 'Library Selection Organization']);
    setPermissionsTeamId($this->organization->id);

    $user = User::factory()->create();
    $this->member = OrganizationMember::create(['user_id' => $user->id, 'organization_id' => $this->organization->id]);
    $role = Role::query()->firstOrCreate(['name' => 'library-selection-reader', 'guard_name' => 'sanctum']);
    $memberRole = MemberRole::create([
        'organization_id' => $this->organization->id,
        'member_id'       => $this->member->id,
        'role_id'         => $role->id,
    ]);

    $permissions = ['library_file.list', 'library_file.retrieve', 'library_folder.list', 'library_folder.retrieve'];
    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    $memberRole->givePermissionTo($permissions);

    $token = $user->createToken('library-selection-token');
    $token->accessToken->update(['metadata' => [
        'organization_id' => $this->organization->id,
        'member_id'       => $this->member->id,
        'member_role_id'  => $memberRole->id,
        'role_id'         => $role->id,
    ]]);
    $this->withToken($token->plainTextToken);
});

/**
 * A file uploaded by the member, at the root unless the attributes give a folder.
 */
function librarySelectionFile(string $memberId, array $attributes = []): File
{
    return File::factory()->create([
        'organization_id' => currentOrganizationId(),
        'uploaded_by'     => $memberId,
        'mime_type'       => 'image/png',
        'extension'       => 'png',
        ...$attributes,
    ]);
}

it('renders every field of the library routes alone as in the full selection', function (): void {
    $folder = Folder::factory()->create(['organization_id' => $this->organization->id]);
    Folder::factory()->create(['organization_id' => $this->organization->id, 'parent_id' => $folder->id]);
    $file = librarySelectionFile($this->member->id);
    $folderFile = librarySelectionFile($this->member->id, ['folder_id' => $folder->id]);
    librarySelectionFile($this->member->id, ['folder_id' => $folder->id])->delete();

    $this->assertFieldsRenderInIsolation('/v1/library/files', FileResource::class, 1);
    // a file in a folder, so folder_id is checked with a value
    $this->assertFieldsRenderInIsolation("/v1/library/files?folder_id={$folder->id}", FileResource::class, 1);
    $this->assertFieldsRenderInIsolation("/v1/library/files/{$folderFile->id}", FileResource::class, 1);
    $this->assertFieldsRenderInIsolation("/v1/library/files/{$file->id}", FileResource::class, 1);
    $this->assertFieldsRenderInIsolation('/v1/library/trash/files', TrashFileResource::class, 1);
    $this->assertFieldsRenderInIsolation('/v1/library/folders', FolderResource::class, 1);
    $this->assertFieldsRenderInIsolation("/v1/library/folders/{$folder->id}", FolderResource::class, 1);
});

it('runs as many queries for more library rows', function (string $uri, Closure $seed): void {
    $this->assertBoundedQueries(
        fn (int $count) => collect(range(1, $count))->each(fn () => $seed($this->member->id)),
        fn () => $this->getJson($uri)->assertOk(),
    );
})->with([
    'files'       => ['/v1/library/files', fn (string $memberId): File => librarySelectionFile($memberId)],
    'trash files' => ['/v1/library/trash/files', fn (string $memberId): bool => (bool) librarySelectionFile($memberId)->delete()],
    'folders'     => ['/v1/library/folders', fn (): Folder => Folder::factory()->create(['organization_id' => currentOrganizationId()])],
]);

it('renders the file and trash file keys in the legacy order', function (): void {
    $file = librarySelectionFile($this->member->id);

    $rendered = $this->renderApiResource($file, FileResource::class);

    expect(array_keys($rendered))->toBe([
        'id', 'folder_id', 'name', 'mime_type', 'kind', 'extension', 'size', 'uploaded_by', 'content_url', 'created_at', 'updated_at',
    ])
        ->and($rendered['kind'])->toBe('image')
        ->and($rendered['content_url'])->toBe(route('lahatre.library.files.content', ['file' => $file->id]));

    $file->delete();

    expect(array_keys($this->getJson('/v1/library/trash/files')->assertOk()->json('data.0')))->toBe([
        'id', 'folder_id', 'name', 'mime_type', 'kind', 'extension', 'size', 'uploaded_by', 'created_at', 'updated_at', 'deleted_at',
    ]);
});

it('keeps the legacy file attachment rendering its file without the file content url', function (): void {
    $file = librarySelectionFile($this->member->id);
    $attachment = (new FileAttachment)->forceFill(['id' => Str::uuid7()->toString(), 'slot' => 'main', 'position' => 1]);
    $attachment->setRelation('file', $file);

    $rendered = json_decode((new FileAttachmentResource($attachment, 'lahatre.library.files.content', 'file', $file->id))->toJson(), true);

    expect(array_keys($rendered))->toBe(['id', 'slot', 'position', 'file', 'content_url'])
        ->and($rendered['file'])->toBe([
            'id'          => $file->id,
            'folder_id'   => null,
            'name'        => $file->name,
            'mime_type'   => 'image/png',
            'kind'        => 'image',
            'extension'   => 'png',
            'size'        => $file->size,
            'uploaded_by' => $this->member->id,
            'created_at'  => $file->created_at->toJSON(),
            'updated_at'  => $file->updated_at->toJSON(),
        ]);
});

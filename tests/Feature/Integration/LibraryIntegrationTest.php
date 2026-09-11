<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Library\Models\File;
use Lahatre\Library\Models\Folder;
use Lahatre\Library\Services\LibraryMaintenanceService;
use Lahatre\Organization\Models\Organization;
use Lahatre\Organization\Models\OrganizationSetting;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('library-test');
    config()->set('library.disk', 'library-test');
    config()->set('library.upload.allowed_mimes', [
        'image/jpeg',
        'image/svg+xml',
        'text/plain',
        'application/pdf',
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->organization = Organization::factory()->create(['name' => 'Library Test Organization']);
    setPermissionsTeamId($this->organization->id);

    $this->user = User::factory()->create();
    $this->member = OrganizationMember::query()->create([
        'user_id'         => $this->user->id,
        'organization_id' => $this->organization->id,
    ]);
    $this->role = Role::query()->firstOrCreate([
        'name'       => 'library-integration-admin',
        'guard_name' => 'sanctum',
    ]);
    $this->memberRole = MemberRole::query()->create([
        'organization_id' => $this->organization->id,
        'member_id'       => $this->member->id,
        'role_id'         => $this->role->id,
    ]);

    $permissions = [
        'library_file.list',
        'library_file.retrieve',
        'library_file.create',
        'library_file.update',
        'library_file.delete',
        'library_folder.list',
        'library_folder.retrieve',
        'library_folder.create',
        'library_folder.update',
        'library_folder.delete',
    ];

    foreach ($permissions as $permissionName) {
        Permission::query()->firstOrCreate([
            'name'       => $permissionName,
            'guard_name' => 'sanctum',
        ]);
    }

    $this->memberRole->givePermissionTo($permissions);
    $token = $this->user->createToken('library-integration-token');
    $token->accessToken->update([
        'metadata' => [
            'organization_id' => $this->organization->id,
            'member_id'       => $this->member->id,
            'member_role_id'  => $this->memberRole->id,
            'role_id'         => $this->role->id,
        ],
    ]);
    $this->withToken($token->plainTextToken);
});

it('uploads multiple private files and exposes only safe library metadata', function (): void {
    $response = $this->post('/v1/library/files?response=resource', [
        'files' => [
            UploadedFile::fake()->image('cover.jpg'),
            UploadedFile::fake()->createWithContent('notes.txt', 'private notes'),
        ],
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.kind', 'image')
        ->assertJsonPath('data.1.kind', 'document')
        ->assertJsonMissingPath('data.0.organization_id')
        ->assertJsonMissingPath('data.0.storage_disk')
        ->assertJsonMissingPath('data.0.storage_key')
        ->assertJsonMissingPath('data.0.checksum');

    $fileIds = collect($response->json('data'))->pluck('id')->all();
    $files = File::query()->whereIn('id', $fileIds)->get();

    expect($files)->toHaveCount(2)
        ->and($files->pluck('uploaded_by')->unique()->all())->toBe([$this->member->id]);

    foreach ($files as $file) {
        expect($file->storage_key)->toStartWith("organizations/{$this->organization->id}/")
            ->and($file->storage_key)->not->toContain($file->original_name);
        Storage::disk('library-test')->assertExists($file->storage_key);
    }
});

it('organizes folders and files without moving stored bytes', function (): void {
    $folderId = (string) $this->postJson('/v1/library/folders?response=resource', [
        'name' => ' Marketing ',
    ])->assertCreated()->json('data.id');

    $fileId = (string) $this->post('/v1/library/files?response=resource', [
        'files' => [UploadedFile::fake()->createWithContent('brief.txt', 'campaign brief')],
    ], ['Accept' => 'application/json'])->assertCreated()->json('data.0.id');

    $file = File::query()->findOrFail($fileId);
    $storageKey = $file->storage_key;

    $this->patchJson("/v1/library/files/{$fileId}?response=resource", [
        'name'      => 'Campaign brief.txt',
        'folder_id' => $folderId,
    ])->assertOk()
        ->assertJsonPath('data.name', 'Campaign brief.txt')
        ->assertJsonPath('data.folder_id', $folderId);

    $file->refresh();
    expect($file->name)->toBe('Campaign brief.txt')
        ->and($file->original_name)->toBe('brief.txt')
        ->and($file->storage_key)->toBe($storageKey);
    Storage::disk('library-test')->assertExists($storageKey);

    $this->getJson("/v1/library/files?folder_id={$folderId}")
        ->assertOk()
        ->assertJsonPath('data.0.id', $fileId);
    $this->getJson('/v1/library/files')->assertOk()->assertJsonCount(0, 'data');
});

it('supports folder CRUD and file metadata retrieval', function (): void {
    $folderId = (string) $this->postJson('/v1/library/folders?response=resource', [
        'name' => 'Drafts',
    ])->assertCreated()->json('data.id');

    $this->getJson('/v1/library/folders')
        ->assertOk()
        ->assertJsonPath('data.0.id', $folderId);
    $this->getJson("/v1/library/folders/{$folderId}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Drafts');
    $this->patchJson("/v1/library/folders/{$folderId}?response=resource", [
        'name' => 'Published',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Published');

    $fileId = (string) $this->post('/v1/library/files?response=resource', [
        'files' => [UploadedFile::fake()->createWithContent('article.txt', 'article')],
    ], ['Accept' => 'application/json'])->assertCreated()->json('data.0.id');

    $this->getJson("/v1/library/files/{$fileId}")
        ->assertOk()
        ->assertJsonPath('data.id', $fileId)
        ->assertJsonPath('data.name', 'article.txt');

    $this->deleteJson("/v1/library/folders/{$folderId}")->assertNoContent();
    $this->getJson("/v1/library/folders/{$folderId}")->assertNotFound();
    expect(Folder::withTrashed()->whereKey($folderId)->value('deleted_at'))->not->toBeNull();
});

it('rejects duplicate folder names, cycles, and non-empty folder deletion', function (): void {
    $parentId = (string) $this->postJson('/v1/library/folders?response=resource', [
        'name' => 'Projects',
    ])->assertCreated()->json('data.id');
    $childId = (string) $this->postJson('/v1/library/folders?response=resource', [
        'name'      => 'Current',
        'parent_id' => $parentId,
    ])->assertCreated()->json('data.id');

    $this->postJson('/v1/library/folders?response=resource', ['name' => 'Projects'])
        ->assertUnprocessable();
    $this->patchJson("/v1/library/folders/{$parentId}?response=resource", ['parent_id' => $parentId])
        ->assertUnprocessable();
    $this->patchJson("/v1/library/folders/{$parentId}?response=resource", ['parent_id' => $childId])
        ->assertUnprocessable();
    $this->deleteJson("/v1/library/folders/{$parentId}")
        ->assertUnprocessable();
});

it('soft deletes empty folders but blocks folders with active files', function (): void {
    $folderId = (string) $this->postJson('/v1/library/folders?response=resource', [
        'name' => 'Attachments',
    ])->assertCreated()->json('data.id');

    $fileId = (string) $this->post('/v1/library/files?response=resource', [
        'folder_id' => $folderId,
        'files'     => [UploadedFile::fake()->createWithContent('attachment.txt', 'attachment')],
    ], ['Accept' => 'application/json'])->assertCreated()->json('data.0.id');

    $this->deleteJson("/v1/library/folders/{$folderId}")->assertUnprocessable();
    expect(Folder::withTrashed()->whereKey($folderId)->value('deleted_at'))->toBeNull();

    $this->deleteJson("/v1/library/files/{$fileId}")->assertNoContent();
    $this->deleteJson("/v1/library/folders/{$folderId}")->assertNoContent();

    expect(Folder::withTrashed()->whereKey($folderId)->value('deleted_at'))->not->toBeNull();

    $this->postJson('/v1/library/folders?response=resource', [
        'name' => 'Attachments',
    ])->assertCreated();
});

it('rejects deleted folders as parents and file destinations', function (): void {
    $folderId = (string) $this->postJson('/v1/library/folders?response=resource', [
        'name' => 'Archived',
    ])->assertCreated()->json('data.id');

    $this->deleteJson("/v1/library/folders/{$folderId}")->assertNoContent();

    $this->postJson('/v1/library/folders?response=resource', [
        'name'      => 'Child',
        'parent_id' => $folderId,
    ])->assertUnprocessable()->assertJsonValidationErrors('parent_id');

    $this->post('/v1/library/files', [
        'folder_id' => $folderId,
        'files'     => [UploadedFile::fake()->createWithContent('file.txt', 'file')],
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('folder_id');
});

it('streams safe content privately and returns a conditional 304 response', function (): void {
    $fileId = (string) $this->post('/v1/library/files?response=resource', [
        'files' => [UploadedFile::fake()->createWithContent('readme.txt', 'hello library')],
    ], ['Accept' => 'application/json'])->assertCreated()->json('data.0.id');
    $file = File::query()->findOrFail($fileId);

    $response = $this->get("/v1/library/files/{$fileId}/content")
        ->assertOk()
        ->assertHeader('Cache-Control', 'private, no-cache')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('ETag', '"'.$file->checksum.'"');

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline;')
        ->and($response->streamedContent())->toBe('hello library');

    $this->withHeader('If-None-Match', '"'.$file->checksum.'"')
        ->get("/v1/library/files/{$fileId}/content")
        ->assertStatus(304)
        ->assertHeader('ETag', '"'.$file->checksum.'"');
});

it('forces potentially executable image formats to download', function (): void {
    $file = File::factory()->create([
        'organization_id' => $this->organization->id,
        'uploaded_by'     => $this->member->id,
        'original_name'   => 'diagram.svg',
        'mime_type'       => 'image/svg+xml',
        'extension'       => 'svg',
        'size'            => 11,
        'checksum'        => hash('sha256', '<svg></svg>'),
        'storage_disk'    => 'library-test',
    ]);
    Storage::disk('library-test')->put($file->storage_key, '<svg></svg>');

    $response = $this->get("/v1/library/files/{$file->id}/content")->assertOk();

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;');
});

it('returns not found when file metadata exists but private content is missing', function (): void {
    $file = File::factory()->create([
        'organization_id' => $this->organization->id,
        'uploaded_by'     => $this->member->id,
        'storage_disk'    => 'library-test',
    ]);

    $this->get("/v1/library/files/{$file->id}/content")->assertNotFound();
});

it('enforces organization quota before storing a batch', function (): void {
    OrganizationSetting::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    OrganizationSetting::query()
        ->where('organization_id', $this->organization->id)
        ->update(['storage_quota_bytes' => 4]);

    $this->post('/v1/library/files?response=resource', [
        'files' => [UploadedFile::fake()->createWithContent('large.txt', '12345')],
    ], ['Accept' => 'application/json'])->assertUnprocessable();

    expect(File::query()->where('organization_id', $this->organization->id)->exists())->toBeFalse()
        ->and(Storage::disk('library-test')->allFiles())->toBe([]);
});

it('validates the configured maximum file count', function (): void {
    config()->set('library.upload.max_files', 1);

    $this->post('/v1/library/files', [
        'files' => [
            UploadedFile::fake()->createWithContent('one.txt', 'one'),
            UploadedFile::fake()->createWithContent('two.txt', 'two'),
        ],
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('files');
});

it('validates the configured per-file size limit', function (): void {
    config()->set('library.upload.max_file_size', 1024);

    $this->post('/v1/library/files', [
        'files' => [UploadedFile::fake()->create('large.txt', 2, 'text/plain')],
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('files.0');

    expect(Storage::disk('library-test')->allFiles())->toBe([]);
});

it('validates the configured batch size limit', function (): void {
    config()->set('library.upload.max_batch_size', 4);

    $this->post('/v1/library/files', [
        'files' => [UploadedFile::fake()->createWithContent('batch.txt', '12345')],
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('files');

    expect(Storage::disk('library-test')->allFiles())->toBe([]);
});

it('validates the configured mime allowlist', function (): void {
    config()->set('library.upload.allowed_mimes', ['application/pdf']);

    $this->post('/v1/library/files', [
        'files' => [UploadedFile::fake()->createWithContent('blocked.txt', 'blocked')],
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('files.0');

    expect(Storage::disk('library-test')->allFiles())->toBe([]);
});

it('requires the matching library permission', function (): void {
    $this->memberRole->revokePermissionTo('library_file.list');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->getJson('/v1/library/files')->assertForbidden();
});

it('soft deletes files while retaining bytes for restoration', function (): void {
    $fileId = (string) $this->post('/v1/library/files?response=resource', [
        'files' => [UploadedFile::fake()->createWithContent('contract.txt', 'contract')],
    ], ['Accept' => 'application/json'])->assertCreated()->json('data.0.id');
    $file = File::query()->findOrFail($fileId);

    $this->deleteJson("/v1/library/files/{$fileId}")->assertNoContent();
    expect(File::query()->whereKey($fileId)->exists())->toBeFalse()
        ->and(File::withTrashed()->whereKey($fileId)->whereNotNull('deleted_at')->exists())->toBeTrue();
    Storage::disk('library-test')->assertExists($file->storage_key);
});

it('lists trashed file metadata and restores the file and its deleted folder chain', function (): void {
    $folder = Folder::factory()->create(['organization_id' => $this->organization->id]);
    $fileId = (string) $this->post('/v1/library/files?response=resource', [
        'folder_id' => $folder->id,
        'files'     => [UploadedFile::fake()->createWithContent('restore.txt', 'restore me')],
    ], ['Accept' => 'application/json'])->assertCreated()->json('data.0.id');
    $file = File::query()->findOrFail($fileId);

    $this->deleteJson("/v1/library/files/{$fileId}")->assertNoContent();
    $this->deleteJson("/v1/library/folders/{$folder->id}")->assertNoContent();

    $this->getJson('/v1/library/trash/files')
        ->assertOk()
        ->assertJsonPath('data.0.id', $fileId)
        ->assertJsonPath('data.0.name', 'restore.txt')
        ->assertJsonStructure(['data' => [['deleted_at']]])
        ->assertJsonMissingPath('data.0.content_url');

    $this->postJson("/v1/library/files/{$fileId}/restore?response=resource")
        ->assertOk()
        ->assertJsonPath('data.id', $fileId)
        ->assertJsonPath('data.folder_id', $folder->id);

    expect(File::query()->whereKey($fileId)->exists())->toBeTrue()
        ->and(Folder::query()->whereKey($folder->id)->exists())->toBeTrue();
    Storage::disk('library-test')->assertExists($file->storage_key);
});

it('renames a restored folder instead of replacing a newer folder with the same name', function (): void {
    $folder = Folder::factory()->create([
        'organization_id' => $this->organization->id,
        'name'            => 'Documents',
    ]);
    $fileId = (string) $this->post('/v1/library/files?response=resource', [
        'folder_id' => $folder->id,
        'files'     => [UploadedFile::fake()->createWithContent('restore.txt', 'restore me')],
    ], ['Accept' => 'application/json'])->assertCreated()->json('data.0.id');
    $this->deleteJson("/v1/library/files/{$fileId}")->assertNoContent();
    $this->deleteJson("/v1/library/folders/{$folder->id}")->assertNoContent();
    Folder::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Documents']);

    $this->postJson("/v1/library/files/{$fileId}/restore?response=resource")->assertOk();

    expect(Folder::query()->findOrFail($folder->id)->name)->toBe('Documents (restored)');
});

it('keeps library reads and mutations inside the active organization', function (): void {
    $otherOrganization = Organization::factory()->create(['name' => 'Other Library Organization']);
    $otherFolder = Folder::factory()->create(['organization_id' => $otherOrganization->id]);
    $otherFile = File::factory()->create([
        'organization_id' => $otherOrganization->id,
        'uploaded_by'     => $this->member->id,
        'storage_disk'    => 'library-test',
    ]);
    $ownFile = File::factory()->create([
        'organization_id' => $this->organization->id,
        'uploaded_by'     => $this->member->id,
        'storage_disk'    => 'library-test',
    ]);

    $this->getJson('/v1/library/files')
        ->assertOk()
        ->assertJsonMissing(['id' => $otherFile->id]);
    $this->getJson("/v1/library/files/{$otherFile->id}")->assertForbidden();
    $this->get("/v1/library/files/{$otherFile->id}/content")->assertForbidden();
    $this->patchJson("/v1/library/files/{$otherFile->id}", ['name' => 'Hacked.txt'])->assertForbidden();
    $this->deleteJson("/v1/library/files/{$otherFile->id}")->assertForbidden();
    $this->patchJson("/v1/library/files/{$ownFile->id}", ['folder_id' => $otherFolder->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('folder_id');
});

it('reconciles missing, corrupted, deleted, and orphan storage objects', function (): void {
    $correctContent = 'correct';
    $corruptedFile = File::factory()->create([
        'organization_id' => $this->organization->id,
        'uploaded_by'     => $this->member->id,
        'storage_disk'    => 'library-test',
        'checksum'        => hash('sha256', $correctContent),
    ]);
    Storage::disk('library-test')->put($corruptedFile->storage_key, 'corrupted');

    $missingFile = File::factory()->create([
        'organization_id' => $this->organization->id,
        'uploaded_by'     => $this->member->id,
        'storage_disk'    => 'library-test',
    ]);
    $deletedFile = File::factory()->create([
        'organization_id' => $this->organization->id,
        'uploaded_by'     => $this->member->id,
        'storage_disk'    => 'library-test',
    ]);
    Storage::disk('library-test')->put($deletedFile->storage_key, 'deleted');
    $deletedFile->delete();
    $deletedFile->deleted_at = now()->subDays(31);
    $deletedFile->save();

    $orphanKey = sprintf(
        'organizations/%s/%s/%s.txt',
        $this->organization->id,
        now()->format('Y/m'),
        str()->uuid7(),
    );
    Storage::disk('library-test')->put($orphanKey, 'orphan');

    $report = app(LibraryMaintenanceService::class)->reconcile(
        organizationId: $this->organization->id,
        deleteOrphans: true,
        verifyChecksums: true,
        orphanGraceHours: 0,
    );

    expect($report->missingFileIds)->toContain($missingFile->id)
        ->and($report->corruptedFileIds)->toContain($corruptedFile->id)
        ->and($report->deletedObjectsRemoved)->toBe(1)
        ->and($report->purgedFilesRemoved)->toBe(1)
        ->and($report->orphanObjectsFound)->toBe(1)
        ->and($report->orphanObjectsRemoved)->toBe(1);
    Storage::disk('library-test')->assertMissing($deletedFile->storage_key);
    Storage::disk('library-test')->assertMissing($orphanKey);
});

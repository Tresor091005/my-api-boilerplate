<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Models\Category;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Master\Data\NoteCreateData;
use Lahatre\Master\Data\NoteFilterData;
use Lahatre\Master\Data\NoteUpdateData;
use Lahatre\Master\Enums\NoteVisibility;
use Lahatre\Master\Exceptions\NoteException;
use Lahatre\Master\Http\Resources\NoteCollection;
use Lahatre\Master\Http\Resources\NoteResource;
use Lahatre\Master\Models\Note;
use Lahatre\Master\Models\NoteMention;
use Lahatre\Master\Services\NoteService;
use Lahatre\Organization\Models\Organization;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->organization = Organization::factory()->create(['name' => 'Notes Organization']);
    $this->otherOrganization = Organization::factory()->create(['name' => 'Other Notes Organization']);
    setPermissionsTeamId($this->organization->id);

    $this->user = User::factory()->create();
    $this->member = OrganizationMember::create([
        'user_id'         => $this->user->id,
        'organization_id' => $this->organization->id,
    ]);

    $this->role = Role::query()->firstOrCreate([
        'name'       => 'notes-admin',
        'guard_name' => 'sanctum',
    ]);
    $this->memberRole = MemberRole::create([
        'organization_id' => $this->organization->id,
        'member_id'       => $this->member->id,
        'role_id'         => $this->role->id,
    ]);

    $permissions = [
        'master_note.list',
        'master_note.retrieve',
        'master_note.create',
        'master_note.update',
        'master_note.delete',
        'master_note.pin',
        'master_note.mention',
        'master_note.visibility_organization',
    ];

    foreach ($permissions as $permissionName) {
        Permission::query()->firstOrCreate([
            'name'       => $permissionName,
            'guard_name' => 'sanctum',
        ]);
    }

    $this->memberRole->givePermissionTo($permissions);

    $this->token = $this->user->createToken('notes-token');
    $this->token->accessToken->update([
        'metadata' => [
            'organization_id' => $this->organization->id,
            'member_id'       => $this->member->id,
            'member_role_id'  => $this->memberRole->id,
            'role_id'         => $this->role->id,
        ],
    ]);

    $this->withToken($this->token->plainTextToken);
    $this->category = Category::factory()->create(['organization_id' => $this->organization->id]);
});

it('creates a note file, preserves deleted replies, and updates edited_at', function (): void {
    $rootResponse = $this->postJson('/v1/master/notes?response=resource', [
        'notable_type' => 'catalog_category',
        'notable_id'   => $this->category->id,
        'body'         => 'Root information',
        'kind'         => 'info',
        'visibility'   => 'organization',
    ])->assertCreated();

    $rootId = $rootResponse->json('data.id');

    $replyResponse = $this->postJson('/v1/master/notes?response=resource', [
        'notable_type' => 'catalog_category',
        'notable_id'   => $this->category->id,
        'parent_id'    => $rootId,
        'body'         => 'Follow-up information',
        'kind'         => 'reminder',
        'visibility'   => 'organization',
    ])->assertCreated();

    $replyId = $replyResponse->json('data.id');

    $this->patchJson("/v1/master/notes/{$rootId}?response=resource", [
        'expires_at' => now()->addHour()->toIso8601String(),
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'A note thread root cannot have an expiration date while it has replies.');

    $this->patchJson("/v1/master/notes/{$replyId}?response=resource", [
        'body' => 'Edited follow-up information',
    ])->assertOk()->assertJsonPath('data.body', 'Edited follow-up information');

    $this->deleteJson("/v1/master/notes/{$replyId}")->assertNoContent();

    $this->getJson("/v1/master/notes/{$rootId}?include=children")
        ->assertOk()
        ->assertJsonPath('data.replies.0.id', $replyId)
        ->assertJsonPath('data.replies.0.deleted', true)
        ->assertJsonMissingPath('data.replies.0.body');

    $this->getJson('/v1/master/notes')
        ->assertOk()
        ->assertJsonFragment(['id' => $rootId])
        ->assertJsonMissing(['id' => $replyId]);

    $this->deleteJson("/v1/master/notes/{$rootId}")
        ->assertStatus(422)
        ->assertJsonPath('errors.type', 'NoteException');
});

it('rejects targets outside the current organization and expires only standalone roots', function (): void {
    $foreignCategory = Category::factory()->create(['organization_id' => $this->otherOrganization->id]);

    $this->postJson('/v1/master/notes?response=resource', [
        'notable_type' => 'catalog_category',
        'notable_id'   => $foreignCategory->id,
        'body'         => 'Invalid target',
        'kind'         => 'info',
        'visibility'   => 'organization',
    ])->assertStatus(422);

    $rootResponse = $this->postJson('/v1/master/notes?response=resource', [
        'notable_type' => 'catalog_category',
        'notable_id'   => $this->category->id,
        'body'         => 'Expiring information',
        'kind'         => 'warning',
        'visibility'   => 'organization',
        'expires_at'   => now()->addHour()->toIso8601String(),
    ])->assertCreated();

    $root = $rootResponse->json('data.id');
    expect($root)->toBeString();
    expect($root !== '')->toBeTrue();

    $this->getJson('/v1/master/notes?kind=warning&visibility=organization')
        ->assertOk()
        ->assertJsonFragment(['id' => $root]);

    $this->getJson('/v1/master/notes?kind=info')
        ->assertOk()
        ->assertJsonMissing(['id' => $root]);

    $this->postJson('/v1/master/notes?response=resource', [
        'notable_type' => 'catalog_category',
        'notable_id'   => $this->category->id,
        'parent_id'    => $root,
        'body'         => 'Not allowed',
        'kind'         => 'info',
        'visibility'   => 'organization',
    ])->assertStatus(422);
});

it('requires creation mentions to use mentioned visibility', function (): void {
    $payload = [
        'notable_type' => 'catalog_category',
        'notable_id'   => $this->category->id,
        'body'         => 'Invalid mentioned payload',
        'kind'         => 'info',
        'visibility'   => 'organization',
        'member_ids'   => [$this->member->id],
    ];

    $this->postJson('/v1/master/notes?response=resource', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('member_ids');

    expect(fn (): NoteResource => app(NoteService::class)->create(NoteCreateData::fromArray($payload)))
        ->toThrow(NoteException::class);
});

it('prunes expired notes across active organizations', function (): void {
    $expiredNote = Note::create([
        'organization_id' => $this->organization->id,
        'notable_type'    => 'catalog_category',
        'notable_id'      => $this->category->id,
        'author_id'       => $this->member->id,
        'body'            => 'Expired note',
        'kind'            => 'info',
        'visibility'      => 'organization',
        'expires_at'      => now()->subDays(31),
    ]);

    $otherExpiredNote = Note::create([
        'organization_id' => $this->otherOrganization->id,
        'notable_type'    => 'catalog_category',
        'notable_id'      => $this->category->id,
        'author_id'       => $this->member->id,
        'body'            => 'Other expired note',
        'kind'            => 'info',
        'visibility'      => 'organization',
        'expires_at'      => now()->subDays(31),
    ]);

    expect(Artisan::call('master:notes:prune', ['--retention-days' => 30]))->toBe(0);
    expect(Artisan::output())->toContain('2 expired note(s) pruned.');

    expect($expiredNote->fresh()->trashed())->toBeTrue()
        ->and($otherExpiredNote->fresh()->trashed())->toBeTrue();
});

it('persists mentions with their own identifiers', function (): void {
    $secondMember = OrganizationMember::create([
        'user_id'         => User::factory()->create()->id,
        'organization_id' => $this->organization->id,
    ]);

    $noteId = $this->postJson('/v1/master/notes?response=resource', [
        'notable_type' => 'catalog_category',
        'notable_id'   => $this->category->id,
        'body'         => 'Mentionable note',
        'kind'         => 'info',
        'visibility'   => 'mentioned',
        'member_ids'   => [$this->member->id, $secondMember->id],
    ])->assertCreated()->json('data.id');

    $mention = NoteMention::query()->where('member_id', $this->member->id)->firstOrFail();

    expect($mention->getKey())->toBeString()
        ->and($mention->note_id)->toBe($noteId)
        ->and(NoteMention::query()->where('note_id', $noteId)->count())->toBe(2);

    $this->postJson("/v1/master/notes/{$noteId}/mentions/read")->assertNoContent();
    $readAt = $mention->fresh()->read_at;

    $this->postJson("/v1/master/notes/{$noteId}/mentions/read")->assertNoContent();

    expect($readAt)->not->toBeNull()
        ->and($mention->fresh()->read_at->equalTo($readAt))->toBeTrue();

    $this->deleteJson("/v1/master/notes/{$noteId}/mentions", [
        'member_ids' => [$secondMember->id],
    ])->assertNoContent();

    expect(NoteMention::query()->where('note_id', $noteId)->where('member_id', $secondMember->id)->exists())->toBeFalse();
});

it('enforces note ownership, collective permissions, and visible reply counts', function (): void {
    $privateNoteId = $this->postJson('/v1/master/notes?response=resource', [
        'notable_type' => 'catalog_category',
        'notable_id'   => $this->category->id,
        'body'         => 'Private note',
        'kind'         => 'info',
        'visibility'   => 'private',
    ])->assertCreated()->json('data.id');

    $organizationRootId = $this->postJson('/v1/master/notes?response=resource', [
        'notable_type' => 'catalog_category',
        'notable_id'   => $this->category->id,
        'body'         => 'Organization root',
        'kind'         => 'info',
        'visibility'   => 'organization',
    ])->assertCreated()->json('data.id');

    $this->postJson('/v1/master/notes?response=resource', [
        'notable_type' => 'catalog_category',
        'notable_id'   => $this->category->id,
        'parent_id'    => $organizationRootId,
        'body'         => 'Private reply',
        'kind'         => 'info',
        'visibility'   => 'private',
    ])->assertCreated();
    $privateReplyId = Note::query()->where('body', 'Private reply')->firstOrFail()->id;

    $mentionedReplyId = $this->postJson('/v1/master/notes?response=resource', [
        'notable_type' => 'catalog_category',
        'notable_id'   => $this->category->id,
        'parent_id'    => $organizationRootId,
        'body'         => 'Mentioned reply',
        'kind'         => 'info',
        'visibility'   => 'mentioned',
        'member_ids'   => [$this->member->id],
    ])->assertCreated()->json('data.id');

    $organizationReplyId = $this->postJson('/v1/master/notes?response=resource', [
        'notable_type' => 'catalog_category',
        'notable_id'   => $this->category->id,
        'parent_id'    => $organizationRootId,
        'body'         => 'Organization reply',
        'kind'         => 'info',
        'visibility'   => 'organization',
    ])->assertCreated()->json('data.id');

    $secondUser = User::factory()->create();
    $secondMember = OrganizationMember::create([
        'user_id'         => $secondUser->id,
        'organization_id' => $this->organization->id,
    ]);
    $secondRole = Role::query()->firstOrCreate([
        'name'       => 'notes-observer-'.$secondMember->id,
        'guard_name' => 'sanctum',
    ]);
    $secondMemberRole = MemberRole::create([
        'organization_id' => $this->organization->id,
        'member_id'       => $secondMember->id,
        'role_id'         => $secondRole->id,
    ]);
    $secondToken = $secondUser->createToken('notes-observer-token');
    $secondToken->accessToken->update([
        'metadata' => [
            'organization_id' => $this->organization->id,
            'member_id'       => $secondMember->id,
            'member_role_id'  => $secondMemberRole->id,
            'role_id'         => $secondRole->id,
        ],
    ]);

    $this->withToken($secondToken->plainTextToken);
    $this->app['auth']->forgetGuards();

    $listResponse = $this->getJson('/v1/master/notes')->assertOk();
    $listResponse
        ->assertJsonFragment(['id' => $organizationRootId, 'replies_count' => 1])
        ->assertJsonMissing(['id' => $privateNoteId]);

    $this->getJson("/v1/master/notes/{$organizationRootId}?include=children")
        ->assertOk()
        ->assertJsonCount(1, 'data.replies')
        ->assertJsonPath('data.replies.0.id', $organizationReplyId)
        ->assertJsonMissing(['id' => $privateReplyId])
        ->assertJsonMissing(['id' => $mentionedReplyId]);

    $secondPrivateNoteId = $this->postJson('/v1/master/notes?response=resource', [
        'notable_type' => 'catalog_category',
        'notable_id'   => $this->category->id,
        'body'         => 'Second private note',
        'kind'         => 'info',
        'visibility'   => 'private',
    ])->assertCreated()->json('data.id');

    $this->patchJson("/v1/master/notes/{$secondPrivateNoteId}?response=resource", [
        'body' => 'Edited by its author',
    ])->assertOk();

    $this->patchJson("/v1/master/notes/{$privateNoteId}?response=resource", [
        'body' => 'Unauthorized edit',
    ])->assertForbidden();
    $this->deleteJson("/v1/master/notes/{$privateNoteId}")->assertForbidden();

    $this->withToken($this->token->plainTextToken);
    $this->app['auth']->forgetGuards();

    $privateMentionedNoteId = $this->postJson('/v1/master/notes?response=resource', [
        'notable_type' => 'catalog_category',
        'notable_id'   => $this->category->id,
        'body'         => 'Private note to promote',
        'kind'         => 'info',
        'visibility'   => 'private',
    ])->assertCreated()->json('data.id');
    $this->patchJson("/v1/master/notes/{$privateMentionedNoteId}/visibility", [
        'visibility' => 'mentioned',
        'member_ids' => [$secondMember->id],
    ])->assertNoContent();

    $this->memberRole->revokePermissionTo([
        'master_note.pin',
        'master_note.mention',
        'master_note.visibility_organization',
    ]);

    $this->postJson("/v1/master/notes/{$privateNoteId}/pin")->assertNoContent();
    $this->postJson("/v1/master/notes/{$organizationRootId}/pin")->assertForbidden();
    $this->postJson("/v1/master/notes/{$organizationRootId}/mentions", [
        'member_ids' => [$secondMember->id],
    ])->assertForbidden();
    $this->patchJson("/v1/master/notes/{$privateNoteId}/visibility", [
        'visibility' => 'organization',
    ])->assertForbidden();
    $this->postJson('/v1/master/notes?response=resource', [
        'notable_type' => 'catalog_category',
        'notable_id'   => $this->category->id,
        'body'         => 'Unauthorized organization note',
        'kind'         => 'info',
        'visibility'   => 'organization',
    ])->assertForbidden();
    $this->postJson('/v1/master/notes?response=resource', [
        'notable_type' => 'catalog_category',
        'notable_id'   => $this->category->id,
        'body'         => 'Unauthorized mentioned note',
        'kind'         => 'info',
        'visibility'   => 'mentioned',
        'member_ids'   => [$secondMember->id],
    ])->assertForbidden();

    $this->memberRole->givePermissionTo([
        'master_note.pin',
        'master_note.mention',
        'master_note.visibility_organization',
    ]);

    $this->postJson("/v1/master/notes/{$organizationRootId}/mentions", [
        'member_ids' => [$secondMember->id],
    ])->assertStatus(422);
    $mentionedNoteId = $this->postJson('/v1/master/notes?response=resource', [
        'notable_type' => 'catalog_category',
        'notable_id'   => $this->category->id,
        'body'         => 'Mentioned note',
        'kind'         => 'info',
        'visibility'   => 'mentioned',
        'member_ids'   => [$secondMember->id],
    ])->assertCreated()->json('data.id');
    $this->postJson("/v1/master/notes/{$mentionedNoteId}/mentions", [
        'member_ids' => [$this->member->id],
    ])->assertNoContent();
    $this->deleteJson("/v1/master/notes/{$mentionedNoteId}/mentions", [
        'member_ids' => [$this->member->id],
    ])->assertNoContent();
    $this->deleteJson("/v1/master/notes/{$mentionedNoteId}/mentions", [
        'member_ids' => [$secondMember->id],
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'A mentioned note must have at least one member mention.');
    expect(NoteMention::query()->where('note_id', $mentionedNoteId)->count())->toBe(1);
    $this->postJson("/v1/master/notes/{$organizationRootId}/pin")->assertNoContent();
    $this->patchJson("/v1/master/notes/{$privateNoteId}/visibility", [
        'visibility' => 'organization',
    ])->assertNoContent();
    $this->patchJson("/v1/master/notes/{$privateNoteId}/visibility", [
        'visibility' => 'private',
    ])->assertForbidden();
});

it('returns resources from the note service and reports missing member context as a module exception', function (): void {
    $this->getJson('/v1/master/notes')->assertOk();

    $service = app(NoteService::class);
    $collection = $service->paginate(NoteFilterData::fromArray([]));
    $created = $service->create(NoteCreateData::fromArray([
        'notable_type' => 'catalog_category',
        'notable_id'   => $this->category->id,
        'body'         => 'Service resource note',
        'kind'         => 'info',
        'visibility'   => 'private',
    ]));

    expect($collection)->toBeInstanceOf(NoteCollection::class)
        ->and($created)->toBeInstanceOf(NoteResource::class)
        ->and($created->resource)->toBeInstanceOf(Note::class);

    $note = $created->resource;
    assert($note instanceof Note);
    expect($service->retrieve($note))->toBeInstanceOf(NoteResource::class)
        ->and($service->update(
            $note,
            NoteUpdateData::fromArray(
                ['body' => 'Updated service resource note'],
                missingFields: ['kind', 'expires_at'],
            ),
        ))->toBeInstanceOf(NoteResource::class);

    authContext()->clear();

    expect(fn (): NoteCollection => $service->paginate(NoteFilterData::fromArray([])))
        ->toThrow(
            NoteException::class,
            'An active organization member context is required for note operations.',
        );
});

it('creates coherent notes and mentions without request context', function (): void {
    authContext()->clear();
    setPermissionsTeamId(null);

    $note = Note::factory()->create();
    $author = OrganizationMember::query()->findOrFail($note->author_id);
    $mention = NoteMention::factory()->create();
    $mentionedNote = Note::query()->findOrFail($mention->note_id);
    $mentionedMember = OrganizationMember::query()->findOrFail($mention->member_id);

    expect($author->organization_id)->toBe($note->organization_id)
        ->and($mention->organization_id)->toBe($mentionedNote->organization_id)
        ->and($mention->organization_id)->toBe($mentionedMember->organization_id)
        ->and($mentionedNote->visibility)->toBe(NoteVisibility::Mentioned);
});

it('rejects cross-organization mention rows at the database boundary', function (): void {
    $note = Note::factory()->create(['organization_id' => $this->organization->id]);
    $foreignMember = OrganizationMember::factory()->create([
        'organization_id' => $this->otherOrganization->id,
    ]);

    expect(fn (): NoteMention => NoteMention::create([
        'organization_id' => $this->organization->id,
        'note_id'         => $note->id,
        'member_id'       => $foreignMember->id,
        'mentioned_at'    => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects pinning an expired note after locking its current row state', function (): void {
    $expiredNote = Note::factory()->create([
        'organization_id' => $this->organization->id,
        'expires_at'      => now()->subMinute(),
    ]);
    authContext()->setContext($this->user, [
        'organization_id' => $this->organization->id,
        'member_id'       => $this->member->id,
        'member_role_id'  => $this->memberRole->id,
        'role_id'         => $this->role->id,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    expect(fn () => app(NoteService::class)->setPinned($expiredNote, true))
        ->toThrow(NoteException::class, 'An expired note cannot be pinned.');

    $usedRowLock = collect(DB::getQueryLog())->contains(
        fn (array $query): bool => str_contains(strtolower((string) $query['query']), 'for update'),
    );
    DB::disableQueryLog();

    expect($usedRowLock)->toBeTrue()
        ->and($expiredNote->fresh()->pinned_at)->toBeNull();
});

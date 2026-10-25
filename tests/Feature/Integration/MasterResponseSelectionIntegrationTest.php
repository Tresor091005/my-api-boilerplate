<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Master\Enums\NoteVisibility;
use Lahatre\Master\Http\Resources\CurrencyResource;
use Lahatre\Master\Http\Resources\LabelResource;
use Lahatre\Master\Http\Resources\NoteResource;
use Lahatre\Master\Http\Resources\UnitResource;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Label;
use Lahatre\Master\Models\Note;
use Lahatre\Master\Models\NoteMention;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Master\Support\UnitCache;
use Lahatre\Organization\Models\Organization;
use Lahatre\ResponseSelection\Testing\AssertsBoundedQueries;
use Lahatre\ResponseSelection\Testing\AssertsResponseFieldIsolation;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class, AssertsBoundedQueries::class, AssertsResponseFieldIsolation::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->organization = Organization::factory()->create(['name' => 'Master Selection Organization']);
    setPermissionsTeamId($this->organization->id);

    $user = User::factory()->create();
    $this->member = OrganizationMember::create(['user_id' => $user->id, 'organization_id' => $this->organization->id]);
    $role = Role::query()->firstOrCreate(['name' => 'master-selection-reader', 'guard_name' => 'sanctum']);
    $memberRole = MemberRole::create([
        'organization_id' => $this->organization->id,
        'member_id'       => $this->member->id,
        'role_id'         => $role->id,
    ]);

    $permissions = [
        'master_currency.list', 'master_label.list', 'master_label.create', 'master_note.list', 'master_note.retrieve',
        'master_note.create', 'master_note.visibility_organization', 'master_unit.list', 'master_unit.create',
    ];
    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    $memberRole->givePermissionTo($permissions);

    $token = $user->createToken('master-selection-token');
    $token->accessToken->update(['metadata' => [
        'organization_id' => $this->organization->id,
        'member_id'       => $this->member->id,
        'member_role_id'  => $memberRole->id,
        'role_id'         => $role->id,
    ]]);
    $this->withToken($token->plainTextToken);
});

/**
 * A root note and a reply both mentioning the member, plus a deleted reply, so every note include has a row.
 */
function masterSelectionNote(OrganizationMember $member): Note
{
    $root = Note::factory()->create([
        'organization_id' => $member->organization_id,
        'author_id'       => $member->id,
        'visibility'      => NoteVisibility::Mentioned,
    ]);
    NoteMention::factory()->create(['organization_id' => $member->organization_id, 'note_id' => $root->id, 'member_id' => $member->id]);

    $replyAttributes = [
        'organization_id' => $member->organization_id,
        'notable_type'    => $root->notable_type,
        'notable_id'      => $root->notable_id,
        'author_id'       => $member->id,
        'parent_id'       => $root->id,
    ];
    $reply = Note::factory()->create([...$replyAttributes, 'position' => 1, 'visibility' => NoteVisibility::Mentioned]);
    NoteMention::factory()->create(['organization_id' => $member->organization_id, 'note_id' => $reply->id, 'member_id' => $member->id]);
    Note::factory()->create([...$replyAttributes, 'position' => 2])->delete();

    // each note targets a new unit, which the unit cache does not know yet
    app(UnitCache::class)->rewarmUnits();

    return $root;
}

function masterSelectionUnit(string $organizationId): Unit
{
    $group = UnitGroup::factory()->create(['organization_id' => $organizationId]);
    $unit = Unit::factory()->create(['organization_id' => $organizationId, 'group_id' => $group->id, 'ratio' => 1]);
    app(UnitCache::class)->rewarmUnits();

    return $unit;
}

it('renders every field of the master routes alone as in the full selection', function (): void {
    Currency::factory()->create();
    Label::factory()->create(['organization_id' => $this->organization->id]);
    masterSelectionUnit($this->organization->id);
    $note = masterSelectionNote($this->member);

    $this->assertFieldsRenderInIsolation('/v1/master/currencies', CurrencyResource::class, 1);
    $this->assertFieldsRenderInIsolation('/v1/master/labels', LabelResource::class, 1);
    $this->assertFieldsRenderInIsolation('/v1/master/units', UnitResource::class, 1);
    $this->assertFieldsRenderInIsolation('/v1/master/notes', NoteResource::class, 1);
    $this->assertFieldsRenderInIsolation("/v1/master/notes/{$note->id}", NoteResource::class, 1);
});

it('runs as many queries for more master rows with their includes', function (string $uri, Closure $seed): void {
    $this->assertBoundedQueries(
        fn (int $count) => collect(range(1, $count))->each(fn () => $seed($this->member)),
        fn () => $this->getJson($uri)->assertOk(),
    );
})->with([
    'currencies' => ['/v1/master/currencies', fn (): Currency => Currency::factory()->create()],
    'labels'     => ['/v1/master/labels', fn (OrganizationMember $member): Label => Label::factory()->create(['organization_id' => $member->organization_id])],
    'units'      => ['/v1/master/units?include=group.units', fn (OrganizationMember $member): Unit => masterSelectionUnit($member->organization_id)],
    'notes'      => ['/v1/master/notes?include=replies.mentions,mentions', fn (OrganizationMember $member): Note => masterSelectionNote($member)],
]);

it('renders a deleted reply with its place in the thread only, and mentions without id', function (): void {
    $note = masterSelectionNote($this->member);

    $response = $this->getJson("/v1/master/notes/{$note->id}?include=replies,mentions")->assertOk();

    expect($response->json('data.replies.1'))->toHaveKeys(['id', 'parent_id', 'position', 'deleted', 'deleted_at'])
        ->and($response->json('data.replies.1'))->not->toHaveKey('body')
        ->and($response->json('data.replies.1.deleted'))->toBeTrue()
        ->and($response->json('data.replies.0'))->not->toHaveKey('deleted_at')
        ->and($response->json('data.mentions.0'))->toBe([
            'member_id'    => $this->member->id,
            'mentioned_at' => $response->json('data.mentions.0.mentioned_at'),
            'read_at'      => null,
        ])
        ->and($response->json('data'))->not->toHaveKey('replies_count');

    $this->getJson('/v1/master/notes')->assertOk()->assertJsonPath('data.0.id', $note->id)->assertJsonStructure(['data' => [['replies_count']]]);
});

it('answers master writes with no content unless the client asks for the resource', function (): void {
    $this->postJson('/v1/master/labels', ['labels' => ['status' => ['Active']]])->assertNoContent();
    $this->postJson('/v1/master/labels?response=resource&fields=value', ['labels' => ['status' => ['Active', 'Archived']]])
        ->assertCreated()
        ->assertExactJson(['data' => [
            ['id' => Label::query()->where('organization_id', $this->organization->id)->where('value', 'active')->value('id'), 'value' => 'active'],
            ['id' => Label::query()->where('organization_id', $this->organization->id)->where('value', 'archived')->value('id'), 'value' => 'archived'],
        ]]);

    $this->postJson('/v1/master/units/upsert', ['group_name' => 'Selection volume', 'units' => [['name' => 'Selection litre', 'ratio' => 1]]])
        ->assertNoContent();
    $this->postJson('/v1/master/units/upsert?response=resource&include=units(name),units.group(name)', [
        'group_name' => 'Selection mass',
        'units'      => [['name' => 'Selection gram', 'ratio' => 1]],
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'selection mass')
        ->assertJsonPath('data.units.0.name', 'Selection gram')
        ->assertJsonPath('data.units.0.group.name', 'selection mass');
});

it('hides another member private reply from the note list includes', function (): void {
    $root = masterSelectionNote($this->member);
    $otherMember = OrganizationMember::factory()->create(['organization_id' => $this->organization->id]);
    $privateReply = Note::factory()->create([
        'organization_id' => $this->organization->id,
        'notable_type'    => $root->notable_type,
        'notable_id'      => $root->notable_id,
        'author_id'       => $otherMember->id,
        'parent_id'       => $root->id,
        'position'        => 3,
        'visibility'      => NoteVisibility::Private,
    ]);
    app(UnitCache::class)->rewarmUnits();

    $replyIds = $this->getJson('/v1/master/notes?include=replies(id)')->assertOk()->json('data.0.replies.*.id');

    expect($replyIds)->toHaveCount(2)
        ->and(in_array($privateReply->id, $replyIds, true))->toBeFalse();
});

it('shows only builtin and own units under an included unit group', function (): void {
    $builtinGroup = UnitGroup::factory()->create(['organization_id' => null, 'is_builtin' => true]);
    $builtinUnit = Unit::factory()->create(['organization_id' => null, 'group_id' => $builtinGroup->id, 'ratio' => 1, 'name' => 'Selection builtin']);
    $ownUnit = Unit::factory()->create(['organization_id' => $this->organization->id, 'group_id' => $builtinGroup->id, 'ratio' => 10]);
    $foreignUnit = Unit::factory()->create([
        'organization_id' => Organization::factory()->create()->id,
        'group_id'        => $builtinGroup->id,
        'ratio'           => 100,
    ]);
    app(UnitCache::class)->rewarmUnits();

    $unitIds = $this->getJson('/v1/master/units?name=Selection%20builtin&include=group.units(id)')
        ->assertOk()
        ->assertJsonPath('data.0.id', $builtinUnit->id)
        ->json('data.0.group.units.*.id');

    expect($unitIds)->toContain($builtinUnit->id, $ownUnit->id)
        ->and(in_array($foreignUnit->id, $unitIds, true))->toBeFalse();
});

<?php

declare(strict_types=1);

namespace Lahatre\Shared\Tests\Feature\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

uses(RefreshDatabase::class);

final class TestOrganizationFactory extends Factory
{
    use ResolvesOrganizationId;

    public function organizationId(): string
    {
        return $this->resolveOrganizationId();
    }

    public function definition(): array
    {
        return [];
    }
}

it('reuses the active organization context', function (): void {
    $organizationId = Str::uuid7()->toString();
    setPermissionsTeamId($organizationId);

    expect((new TestOrganizationFactory())->organizationId())->toBe($organizationId)
        ->and(DB::table('organization_organizations')->count())->toBe(0);
});

it('creates a fallback organization when no context exists', function (): void {
    setPermissionsTeamId(null);

    $organizationId = (new TestOrganizationFactory())->organizationId();

    expect($organizationId)->toBeString()->not->toBeEmpty()
        ->and(DB::table('organization_organizations')->where('id', $organizationId)->exists())->toBeTrue();
});

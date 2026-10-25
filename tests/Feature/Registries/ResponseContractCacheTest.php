<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Lahatre\Shared\Http\Responses\ResponseMode;
use Lahatre\Shared\Registries\ResponseContractRegistry;

function responseContractCachePath(): string
{
    return App::bootstrapPath('cache/response-contracts.php');
}

/**
 * Discovers the fixture module instead of the real ones: every real route left
 * on contracts declares no shape.
 */
function useResponseContractFixtures(): void
{
    config(['app-modules.modules_directory' => 'tests/Fixtures/ResponseContracts']);
}

beforeEach(function (): void {
    config(['app.env' => 'testing']);

    if (File::exists(responseContractCachePath())) {
        File::delete(responseContractCachePath());
    }
});

afterEach(function (): void {
    config(['app.env' => 'testing']);

    if (File::exists(responseContractCachePath())) {
        File::delete(responseContractCachePath());
    }
});

it('discovers response contracts from the application and module config files', function (): void {
    $registry = new ResponseContractRegistry;
    $registry->discover();

    expect($registry->forRoute('lahatre.iam.auth.email-challenge-verifications.store'))->not->toBeNull();
});

it('applies one shared shape to every contract that references it', function (): void {
    useResponseContractFixtures();
    $registry = new ResponseContractRegistry;
    $registry->discover();

    $show = $registry->forRoute('fixture.customers.show');
    $store = $registry->forRoute('fixture.customers.store');
    $update = $registry->forRoute('fixture.customers.update');

    expect($show)->not->toBeNull()
        ->and($store)->not->toBeNull()
        ->and($update)->not->toBeNull();

    $showShape = $show->shapes['default'];

    expect($showShape->requiredLoads)->toBe(['organization'])
        ->and(array_keys($showShape->includes))->toBe(['addresses', 'files.profile_picture'])
        ->and($store->shapes['default']->requiredLoads)->toBe($showShape->requiredLoads)
        ->and($store->shapes['default']->includes)->toEqual($showShape->includes)
        ->and($update->shapes['default']->includes)->toEqual($showShape->includes);
});

it('answers the IAM payload routes with a body and leaves the user routes to response selection', function (): void {
    $registry = new ResponseContractRegistry;
    $registry->discover();

    $login = $registry->forRoute('lahatre.iam.auth.email-challenge-verifications.store');
    $organizationRegistration = $registry->forRoute('lahatre.iam.auth.organization-registrations.store');

    // the sign-in payload loads the user's memberships itself (AuthService::issueToken)
    expect($login)->not->toBeNull()
        ->and($organizationRegistration)->not->toBeNull()
        ->and($login->defaultMode)->toBe(ResponseMode::Resource)
        ->and($login->resolveShape(null))->toBeNull()
        ->and($organizationRegistration->defaultMode)->toBe(ResponseMode::Resource)
        ->and($organizationRegistration->resolveShape(null))->toBeNull()
        ->and($registry->forRoute('lahatre.iam.auth.me'))->toBeNull()
        ->and($registry->forRoute('lahatre.iam.auth.switch-member-role'))->toBeNull();
});

it('maps every relation-backed resource operation to required loads or includes', function (): void {
    useResponseContractFixtures();
    $registry = new ResponseContractRegistry;
    $registry->discover();

    $expectations = [
        'fixture.customers.show'   => ['addresses' => ['organization', 'addresses'], 'files.profile_picture' => ['profilePictureFileAttachments.file']],
        'fixture.customers.update' => ['addresses' => ['addresses']],
    ];

    foreach ($expectations as $routeName => $includes) {
        $contract = $registry->forRoute($routeName);
        $shape = $contract?->resolveShape(null);

        expect($shape)->not->toBeNull();

        foreach ($includes as $include => $loads) {
            expect($shape->relationsToLoad([$include]))->toContain(...$loads);
        }
    }

    expect($registry->forRoute('fixture.customers.store')?->resolveShape(null)->requiredLoads)
        ->toContain('organization');
});

it('ignores a response contract cache outside production and discovers definitions', function (): void {
    config(['app.env' => 'local']);

    $cachePath = responseContractCachePath();
    $customDefinitions = ['custom.route' => []];

    File::put($cachePath, '<?php return '.var_export($customDefinitions, true).';');

    $registry = new ResponseContractRegistry;
    $registry->discover();

    expect($registry->forRoute('lahatre.iam.auth.email-challenge-verifications.store'))->not->toBeNull()
        ->and($registry->forRoute('custom.route'))->toBeNull();
});

it('loads response contracts from the generated cache in production', function (): void {
    config(['app.env' => 'production']);

    $cachePath = responseContractCachePath();
    $customDefinitions = ['custom.route' => []];

    File::put($cachePath, '<?php return '.var_export($customDefinitions, true).';');

    $cachedRegistry = new ResponseContractRegistry;
    $cachedRegistry->discover();

    expect($cachedRegistry->forRoute('custom.route'))->not->toBeNull()
        ->and($cachedRegistry->forRoute('lahatre.iam.auth.email-challenge-verifications.store'))->toBeNull();
});

it('caches response contracts from discovered definitions when no cache exists', function (): void {
    useResponseContractFixtures();
    $registry = new ResponseContractRegistry;
    $registry->cache();

    expect(File::exists(responseContractCachePath()))->toBeTrue();

    $cachedDefinitions = require responseContractCachePath();

    expect($cachedDefinitions)->toHaveKey('fixture.customers.show')
        ->and(array_key_exists('_shapes', $cachedDefinitions))->toBeFalse()
        ->and($cachedDefinitions['fixture.customers.show']['shapes']['default'])
        ->toHaveKey('includes')
        ->and(array_key_exists('ref', $cachedDefinitions['fixture.customers.show']['shapes']['default']))->toBeFalse()
        ->and($cachedDefinitions['fixture.customers.export'])
        ->toEqual([])
        ->and($cachedDefinitions['fixture.customers.update']['shapes']['default'])
        ->not->toHaveKey('ref');
});

it('clears the generated response contract cache', function (): void {
    $registry = new ResponseContractRegistry;
    $registry->cache();
    $registry->clear();

    expect(File::exists(responseContractCachePath()))->toBeFalse();
});

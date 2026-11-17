<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Support\Testing\Fakes\QueueFake;
use Illuminate\Testing\TestResponse;
use Lahatre\Billing\Enums\PlanVersionStatus;
use Lahatre\Billing\Models\PlanVersion;
use Lahatre\Billing\Models\PlanVersionFeature;
use Lahatre\Billing\Models\Subscription;
use Lahatre\Billing\Registries\FeatureRegistry;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\CatalogItemPrice;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Iam\Jobs\SendLoginCode;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Master\Models\Currency;
use Lahatre\Shared\Support\ModelFinder;
use Pest\TestSuite;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature', 'Unit', '../app-modules/*/tests');

// the response selection package runs on its own bare application (Orchestra Testbench)
pest()->extend(Lahatre\ResponseSelection\Tests\TestCase::class)
    ->in('../packages/response-selection/tests');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

// expect()->extend('toBeOne', fn() => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Get all application and module models.
 *
 * @return array<int, string>
 */
function getAllModels(): array
{
    return ModelFinder::getAllModels();
}

function currentTestCase(): TestCase
{
    $testCase = TestSuite::getInstance()->test;

    if (!$testCase instanceof TestCase) {
        throw new RuntimeException('No active test case is available.');
    }

    return $testCase;
}

/**
 * Resolve a fully qualified class name from a PHP file.
 */
function resolveFullyQualifiedClassName(SplFileInfo $file): ?string
{
    $content = $file->getContents();

    if (!preg_match('/^namespace\s+([^;]+);/m', $content, $namespaceMatches)) {
        return null;
    }

    if (!preg_match('/^(?:(?:abstract|final|readonly)\s+)*class\s+([A-Za-z_][A-Za-z0-9_]*)/m', $content, $classMatches)) {
        return null;
    }

    return $namespaceMatches[1].'\\'.$classMatches[1];
}

/**
 * Create the explicit CatalogItem/ProductVariant identity pair used by catalog tests.
 *
 * @param  array<string, mixed>  $variantAttributes
 * @param  array<string, mixed>  $catalogItemAttributes
 */
function createCatalogProductVariant(
    array $variantAttributes = [],
    array $catalogItemAttributes = [],
): ProductVariant {
    $organizationId = $catalogItemAttributes['organization_id']
        ?? $variantAttributes['organization_id']
        ?? currentOrganizationId();

    $catalogItem = CatalogItem::factory()->create([
        ...$catalogItemAttributes,
        'organization_id' => $organizationId,
    ]);

    return ProductVariant::factory()->forCatalogItem($catalogItem)->create([
        ...$variantAttributes,
        'organization_id' => $organizationId,
    ]);
}

/**
 * Fills a missing level with the default and a missing amount with the catalog price ("0" if none).
 *
 * @param  list<array<string, mixed>>  $lines
 * @return list<array<string, mixed>>
 */
function pricedSaleLines(array $lines, string $defaultPriceLevelId): array
{
    $master = app(MasterInterface::class);

    return array_map(function (mixed $line) use ($defaultPriceLevelId, $master): mixed {
        if (!is_array($line)) {
            return $line;
        }

        $line['price_level_id'] ??= $defaultPriceLevelId;

        if (!array_key_exists('unit_amount', $line)) {
            $level = Str::isUuid((string) $line['price_level_id']) ? PriceLevel::withTrashed()->find($line['price_level_id']) : null;
            $unit = $master->units(collect([(string) ($line['unit_code'] ?? '')]))->first();
            $amount = $level === null || $unit === null ? null : CatalogItemPrice::query()
                ->where('price_level_id', $level->id)
                ->where('catalog_item_id', is_string($line['catalog_item_id'] ?? null) && Str::isUuid($line['catalog_item_id']) ? $line['catalog_item_id'] : null)
                ->where('unit_id', $unit->id)
                ->value('amount');
            $line['unit_amount'] = $amount === null ? '0' : $master->fromMinor((string) $amount, $level->currency_code);
        }

        return $line;
    }, $lines);
}

/** Authenticate through the real email challenge endpoints with delivery faked. */
function loginWithEmailCode(string $email): TestResponse
{
    Queue::fake();
    $response = currentTestCase()->withHeader('Authorization', '')->postJson('/v1/auth/email-challenges', ['email' => $email])->assertOk();
    $job = queuedLoginCodes()->last();

    return currentTestCase()->postJson('/v1/auth/email-challenge-verifications', [
        'challenge_id' => $response->json('challenge_id'), 'code' => $job->code,
    ]);
}

/** @return Collection<int, SendLoginCode> */
function queuedLoginCodes(): Collection
{
    $queue = Queue::getFacadeRoot();
    if (!$queue instanceof QueueFake) {
        throw new LogicException('Login code delivery must be faked in this test.');
    }

    return $queue->pushed(SendLoginCode::class);
}

/**
 * Gives the organization a running subscription to a plan granting these features, and turns the feature checks on.
 *
 * @param  array<string, array{allowance?: int|null, values?: list<string>}>  $grants
 */
function subscribeOrganizationTo(string $organizationId, array $grants): PlanVersion
{
    config(['billing.entitlements_enforced' => true]);
    Currency::query()->firstOrCreate(['code' => 'USD'], ['symbol' => '$', 'precision' => 2]);
    $version = PlanVersion::factory()->create(['status' => PlanVersionStatus::Published, 'published_at' => now()]);

    foreach ($grants as $featureKey => $grant) {
        PlanVersionFeature::factory()->create([
            'plan_version_id' => $version->id,
            'feature_key'     => $featureKey,
            'feature_type'    => app(FeatureRegistry::class)->get($featureKey)->type,
            'allowance'       => $grant['allowance'] ?? null,
            'values'          => $grant['values'] ?? null,
        ]);
    }

    Subscription::factory()->create([
        'organization_id' => $organizationId,
        'plan_version_id' => $version->id,
        'starts_at'       => now()->subDay()->startOfSecond(),
        'ends_at'         => now()->addYear()->startOfSecond(),
    ]);

    return $version;
}

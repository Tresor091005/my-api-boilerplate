<?php

declare(strict_types=1);

use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Shared\Support\ModelFinder;
use Pest\TestSuite;
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

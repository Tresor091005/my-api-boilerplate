---
name: test-generator
description: Pest 4 test-generation standards for this modular Laravel codebase, including module-local vs integration boundaries and the required project rule sources.
---

# Test Generator

This skill defines the quality standard for Pest 4 tests in this codebase, with priority on clean modular separation.

## Source Files

Before generating or reshaping tests, always read:

`/Users/imac/Documents/my-api-boilerplate/.agents/CODEBASE_RULES.md`
`/Users/imac/Documents/my-api-boilerplate/.agents/PROJECT_MEMORY.md`

## Golden Rule

- A test must document a clear business or application intent.
- Any behavior change should be preceded or accompanied by a test.

## Test Boundaries In This Codebase

To respect modular architecture constraints, keep test responsibilities separate:

1. Module-local tests: `app-modules/<module>/tests`
   - Target services, DTOs, business assertions, and persistence local to the module.
   - Use only dependencies allowed by the module dependency graph.
   - Avoid full IAM bootstrap when the module does not depend on IAM or Organization.

2. Cross-module integration tests: `tests/Feature/Integration/*`
   - Target real HTTP authorization, middleware, policy, gate, and application-wide interactions.
   - IAM and Organization dependencies are acceptable here.

3. Practical decision rule
   - If the test answers “who is allowed?” it belongs to integration.
   - If the test answers “what does the business logic do?” it belongs to the module.
   - Do not mix both questions in the same test file.

4. Foreign-key constraint without namespace dependency
   - If a module references an external table through a foreign key such as `organization_id`, insert the minimum required rows with `DB::table(...)` instead of importing the external module model.

## Environment Stability

In `beforeEach`, prefer explicit setup:

1. Team context
```php
setPermissionsTeamId($tenantId);
```

2. Rate limiter for API endpoint tests when needed
```php
RateLimiter::for('api', fn () => Limit::none());
```

## Anatomy Of A Module-Local Test

1. DTO validation
   - Build the DTO with invalid payload.
   - Expect `ValidationException`.

2. Business assertions
   - Reproduce invalid domain state.
   - Assert the expected business exception.

3. Service logic and persistence
   - Call the service with valid payload.
   - Assert persistence and relations.

4. Output contract
   - Assert the `resource` or `collection` payload with `->response()->getData(true)`.
   - Assert critical business keys.

## Pest Structure

Typical service-first setup:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);
```

Conventions:
- Prefer `it()` over `test()`.
- Name tests by expected behavior.
- Keep `beforeEach` minimal and explicit.

Expectation style:

```php
expect($unit->code)->toBe('KG')
    ->and($unit->name)->toBe('Kilogram');
```

## DTO Validation Datasets

```php
it('fails validation', function (array $data): void {
    expect(fn () => new UnitSyncDTO($data))
        ->toThrow(ValidationException::class);
})->with([
    'missing code' => [['name' => 'Test']],
    'invalid type' => [['code' => 123]],
]);
```

## Mocks And Fakes

- `Event::fake()`
- `Notification::fake()`
- `Storage::fake('public')`
- `Http::fake()` for external integrations

## Stability Reminder

- `PROJECT_MEMORY.md` documents the current interpretation of test boundaries and known tooling limitations.
- Application integration tests should own HTTP authorization expectations.
- Module-local tests should not pretend to be HTTP contract tests.

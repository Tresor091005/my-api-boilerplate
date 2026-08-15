---
paths:
  - tests/**
  - app-modules/*/tests/**
---

# Testing Boundaries

## Test Decomposition

- Write tests with Pest. Every test documents one clear business or application intent, and behavior changes require an affected test.
- Prefer complete Feature tests for user-visible behavior: make the real HTTP request through the route, middleware, Form Request, authorization, service, persistence, and Resource/JSON response layers. Verify both successful responses and expected errors, including validation, authorization, tenant boundaries, and soft-delete boundaries. Do not replace this path with a direct service test when the contract is HTTP.
- Keep module-local Feature tests in `app-modules/<module>/tests` for module-owned HTTP or business behavior. Put cross-module, real authentication/authorization, middleware, tenancy, and application boundary tests in `tests/Feature/Integration`.
- Use Unit tests for isolated behavior that does not need the complete framework boundary: Services when their orchestration can be tested without HTTP, model-centered Assertions and their Exceptions, typed Data objects, and business-agnostic Support helpers.
- Use Architecture tests in `tests/Feature/Architecture` for conventions that a new developer could accidentally break: module dependencies, route naming/scoping, localization shape, model/database integrity, exception contracts, and forbidden structure.
- Use the decision rule: “does the user or application boundary work?” is a Feature test; “does this isolated class implement its contract?” is a Unit test; “does the repository preserve a convention?” is an Architecture test.
- Do not mix unrelated responsibilities in one test. A complete HTTP test may verify validation, authorization, tenancy, persistence, and response because those are one endpoint contract; keep isolated class behavior separate.

## Test Construction

- Prefer `it()` with behavior-focused English names. Keep `beforeEach()` small and explicit.
- Test in this priority order when applicable: Request validation and Data mapping, assertions, service persistence, then output contract.
- For module-local tests that require framework state, use `RefreshDatabase` and
  the shared `Tests\TestCase` deliberately. Avoid booting IAM or Organization
  when the module contract does not need them; use the minimum foreign-key rows
  through `DB::table()` when importing another module would violate the
  dependency boundary.
- Set the permissions team context explicitly when a test needs it. For API
  endpoint tests that are about behavior rather than throttling, disable or
  neutralize the limiter explicitly instead of letting rate limits make the
  test order-dependent.
- Use factories and their states. If a module needs only a foreign-key row from a module it must not depend on, insert the minimum row with `DB::table()` instead of importing that module's model.
- Preserve the distinction between absent fields and explicit `null`, `false`, zero, empty strings, or empty arrays in Data tests.
- Prefer real application objects, factories, the test database, and Laravel fakes at external boundaries. Avoid mocks by default: do not mock Services, Models, Repositories, or internal collaborators merely to make a test easier. Use a mock only when a deterministic contract cannot be exercised otherwise, and keep that reason visible in the test.
- Use `Http::fake()` for external HTTP integrations and the narrowest Laravel fake for other framework boundaries; assert the observable interaction and resulting behavior.
- Use Pest datasets for repeated validation cases and assert the exact error
  keys/paths that form part of the contract. For Resources and collections,
  inspect `->response()->getData(true)` and assert critical business fields,
  pagination metadata, and omitted/loaded relationship behavior.
- Do not delete tests without approval.

## Execution

- By default, run the smallest affected test scope through Pest 5 Test Impact
  Analysis in parallel:
  docker compose exec -T app ./vendor/bin/pest --parallel --tia <path-or-filter>
  or make test-fast.
- If TIA fails, reports an unusable dependency graph, or cannot start parallel
  workers, immediately fall back to
  php artisan test --compact <path-or-filter> (Docker:
  docker compose exec -T app php artisan test --compact <path-or-filter>).
- Run the smallest affected test file or filter first. Use the normal
  php artisan test --compact path when a complete suite run is intentionally
  required.
- Use `composer quality:check` as the non-mutating complete quality gate: Rector dry-run, Pint check, and PHPStan. Use `composer quality` only when the requested workflow allows automated Rector/Pint changes.
- Run the affected Pest tests before the complete quality gate. A green static-analysis check does not replace behavioral tests, and a green Feature test does not replace architecture or code-quality checks.

## References

- `tests/Feature/Integration/CatalogAuthorizationIntegrationTest.php` and
  `tests/Feature/Integration/CatalogTenancyIntegrationTest.php` show complete
  application-boundary checks for authorization and tenant isolation.
- `app-modules/inventory/tests/Feature/Inventory/InventoryReadEndpointsTest.php`
  shows real authenticated inventory HTTP reads and response behavior.
- `app-modules/catalog/tests/Unit/Assertions/CatalogAssertionsTest.php` shows
  isolated model-invariant assertion tests.
- `app-modules/shared/tests/Unit/Helpers/StableCursorPaginateTest.php` shows a
  focused Support helper test.
- `tests/Feature/Architecture/` contains convention guards for routes,
  modules, localization, persistence, and exception contracts.
- `composer.json` defines the Pest, Rector, Pint, PHPStan, and quality scripts;
  `tests/Pest.php` defines the shared Pest/TestCase setup.

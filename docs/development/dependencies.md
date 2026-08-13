# Dependencies and package roles

The project keeps its runtime and development dependencies in the root
`composer.json`; the installed versions are the source of truth for API usage.
The current direct versions can be inspected with `composer show --direct`.

## Runtime packages with application behavior

- Laravel 13 provides the application framework, routing, validation, queues,
  mail, cache, database, and console foundation.
- `internachi/modular` loads the `app-modules/*` packages and their providers.
- Sanctum provides personal access tokens; Spatie Permission provides
  organization/team-scoped roles and permissions.
- Horizon, Reverb, Telescope, and Scramble provide queue supervision, realtime
  infrastructure, local observability, and generated API documentation.
- `staudenmeir/eloquent-has-many-deep` and
  `staudenmeir/laravel-adjacency-list` support module relationships and
  hierarchical catalog data.

## Development and verification packages

Pest and its Laravel/type-coverage plugins provide the test suite. Pint formats
PHP, Rector applies and checks automated refactorings, Larastan/PHPStan performs
static analysis, and IDE Helper generates development-only model and IDE
metadata. These tools are covered by [testing and generation](testing-and-generation.md)
and [code quality](code-quality.md).

The application-owned caches are instead the Redis-backed cache stores,
`UnitCache`, request-local inventory selection caching, and Spatie's permission
registrar cache described in [caching and consistency](caching-and-consistency.md).

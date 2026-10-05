> Snapshot de vérification. Les corrections suivantes sont réconciliées dans le [bilan](README.md) : accès Auth, lock du frontend, nonce Google et filtre unit/list.

# Final root inspection

The delegated manifest filter is complete: 53 root files, 3,589 lines, read in full. Related runtime evidence adds 17 files; review of the public English/French architecture, development and installation guides adds 20 pages. `root-final-coverage.json` records all 90 inspected files, SHA-256 snapshots, line counts, selected/excluded input entries and verification limits.

No DB command, generator, test, Docker build/start, Composer command or source edit was made. Only the requested scratch audit artifacts were written. The binary `.mmdb` fixture was explicitly excluded; both adjacent MaxMind license files were read fully. No IDE-generated helper was in the selected list.

## Public global guides

The current global public pages describe the source accurately in their material runtime claims:

- Installation uses the development Compose configuration, the actual 28417/28418/28419/28420/28421 ports, PostgreSQL 18's volume path, dedicated DB credentials, explicit key generation and explicit migrate/seed. Both entrypoints omit migrations. The development entrypoint installs locked Composer dependencies when vendor is missing or its marker is older than the lock, then clears config. The runtime target installs no-dev dependencies at image build and caches config at startup.
- Architecture accurately shows JSON/response context/rate limiting, profile/session/context/team middleware, lifecycle-scoped services, immutable Eloquent dates, strict mode outside production and required morph aliases. The explicit tenant predicates and distinction between authorization and service invariants are correct.
- Runtime operations correctly describe Redis queues `default`/`email`, local Horizon maximum three processes/one attempt, `after_commit=false`, explicit after-commit dispatch obligations, and the production-only morph/response caches. App timezone is explicitly UTC in `config/app.php`; public date pages correctly identify the existing Inventory/numbering application-clock limitation.
- Testing accurately states that PostgreSQL credentials and DB name/host are inherited, many tests use RefreshDatabase, and the development DB is not automatically isolated. `tests/Pest.php` does not globally enable RefreshDatabase: files select it deliberately. Composer quality:check includes the test script; the public guide correctly discloses that fact and config-cache side effects.
- Documentation maintenance commands match `docs/site/package.json`; Next config exports static HTML (`output: export`) and npm start uses `serve out`. No server-only production deployment is implied.
- Reverb is correctly described publicly as infrastructure with no business broadcasting workflow. The default example channel remains in source; it is not stock alerting or frontend invalidation.

One minor text discrepancy was found and sent to the parent: `development/index.mdx` says every JS project has its own manifest and lock, but `frontend/package.json` has no dependencies and there is no frontend lock. The documentation site has its own manifest/lock. Prefer wording that inspects each project's actual dependency setup.

French/English material runtime behavior is consistent. No source change is necessary to make the reviewed guides factual.

## Root source findings to track

1. **Default broadcasting channel compares UUIDs as integers.** `routes/channels.php:7` uses `(int) $user->id === (int) $id`. The IAM IDs are UUID strings, so distinct UUIDs can collapse to the same integer (a nonnumeric alphabetic prefix converts to zero). `bootstrap/app.php` registers the channels file. Installed Laravel's BroadcastManager registers `/broadcasting/auth`, and its broadcaster evaluates this callback against the resolved request user. The channel currently has no business events; the comparison still must not be treated as valid user identity authorization. This is a source-level finding, not an executed HTTP exploit or a claim of observed information exposure. No callback was changed.
2. **Doctor service checks are DNS checks.** HorizonServiceIsManaged, ReverbServiceIsManaged and SchedulerServiceIsManaged skip outside a container, then only check `gethostbyname(name) !== name`. They do not establish that a worker/scheduler/WebSocket process is alive or serving work. Public operations wording already says hostname resolution, which is correct.
3. **Environment checking is strict about all example blanks and bounded in runtime mode.** EnvironmentFileInspector reports missing/extra/empty keys for a file. It treats all declared blank values as empty, including optional Google, MaxMind, AWS and Reverb settings. `--runtime` gathers only names already present in the example, so it cannot discover arbitrary additional environment names. Doctor can therefore fail its environment comparison after an otherwise adequate fresh optional-free installation. This is not a full semantic credential/configuration validation. The commands do not print values in env:check, while env:format --dry-run intentionally emits the formatted file content; no command was run.
4. **Runtime image and production orchestration are separate scopes.** The runtime target builds production code/dependencies; the shared app Compose file does not by itself inject Laravel secrets or mount persistent shared private storage in PHP services. Development overlays bind-mount the checkout, which makes the local GeoIP/private files accessible to app/workers/scheduler. A production deployment must supply the corresponding environment and storage arrangement. Public operations explicitly tells readers to preserve SQL/private files/APP_KEY and does not claim a turnkey production setup.
5. **Docker build context includes private application storage unless separately excluded.** Runtime Dockerfile uses `COPY . .`; `.dockerignore` excludes Git/env/vendor/node_modules/logs/framework artifacts, but not `storage/app/private` nor generated documentation exports. This is an observable build-context boundary, not proof that actual private records were packaged. No build or private storage inspection was performed.

Optional author follow-ups: should environment diagnostics permit deliberately unset optional integrations, and what production storage/process layout is intended when the runtime image is deployed? The public ERP-in-development identity avoids promises about an unfinished production deployment.

## Older documentation discrepancies

The updated root documentation index now clearly identifies the bilingual site as canonical and old blueprints as potentially historical. Keep that distinction: several old pages are useful background but should not determine current public behavior.

- `docs/architecture/application-runtime.md` omits ResolveResponseContext and EnsureProfileComplete in its initial pipeline list, puts AssertionException type in an ambiguously described envelope, and reserves empty response contracts for no-body endpoints. Current Inventory GET collection contracts are valid empty shapes with resource bodies. The public Shared/architecture pages are more accurate.
- `docs/development/code-quality.md` describes quality:check as Rector/Pint/PHPStan without including the test script in its final paragraph. The current composer script and public testing page include it.
- `docs/broadcasting/reverb.md` opens with active broadcasting language and illustrates ProductChanged/cache invalidation without a current implementation marker. Public operations correctly marks it unimplemented.
- `docs/authentication/sanctum.md` retains an auth.api code snippet without EnsureProfileComplete and older profile/signup phrasing. The current bootstrap and IAM guide carry the exact boundaries.
- `docs/infrastructure/docker.md` has an old statement that the local build relies on Dockerfile's final development target, while the current development overlay explicitly selects `target: development`. Its service paths/ports and explicit-migration policy remain accurate.
- `docs/architecture/date-time-conventions.md` illustrates organizational expiration interpretation as a convention; Inventory currently computes counters/filter cutoffs with the application clock. The public date page correctly distinguishes intended convention from implemented behavior.

## Tests, tools and licenses read

`tests/Pest.php` binds the Laravel TestCase to root Feature/Unit and module tests; it supplies real email-challenge login with queued delivery faked and a CatalogItem/ProductVariant identity-pair helper. `tests/TestCase.php` mostly declares shared fixture properties and an exception-handling helper. Neither proves an application behavior by itself.

EnvironmentFormat creates a timestamped backup and rewrites .env in example order unless dry-run. SortModelPhpDocs writes generated model PHPDoc blocks and deduplicates/group-orders annotations; it is an implementation tool, not an IDE-generated file. These commands were inspected, not executed.

The GeoIP fixture directory includes full MIT and Apache-2.0 texts. Their presence does not establish a uniform project/module license; public reuse guidance correctly asks consumers to resolve the repository/package license metadata. No license advice or redistribution conclusion is made here.

## Verification limits

This pass verifies text and implementation correspondence, with full-file source review. It does not claim fresh Docker health, image build success, isolated SQL test success, broadcasting HTTP verification or rendered-site checks. Those checks remain owned by the parent. The source finding on the example channel and the environment diagnostic limits were delivered before this handoff.

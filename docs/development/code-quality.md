# Code quality philosophy

This project is intended not only to provide a functional foundation but also
to demonstrate a rigorous approach to code quality. Static analysis and
formatting tools help catch bugs early and maintain a consistent style.
Readable, consistent code is easier to maintain and evolve.

The following tools implement this philosophy. The `pre-commit` hook currently
runs Pint only; the complete check is `composer quality:check`.

- **Pint:** Ensures a unified PSR-12-based style without manual formatting.
- **Rector:** Automates large-scale refactoring and code updates safely.
- **PHPStan (Larastan):** Detects type errors and inconsistencies before code
  runs.
- **IDE Helper:** Generates helper files for IDE autocompletion and code
  discovery.
- **Laravel Doctor:** Checks the current environment, configuration, storage,
  service connections, migrations, and Composer security advisories. Run
  `php artisan doctor --format=cli --no-interaction` in the application
  environment with PostgreSQL and Redis available. A failed diagnostic makes
  the command exit with a nonzero status. Review the report before using
  Doctor's optional `--fix` flag, which can change local configuration.

`composer quality:check` runs Rector in dry-run mode, Pint in check mode, and
PHPStan. `composer quality` applies fixes and regenerates IDE files before
analysis. None of these scripts replaces functional tests.

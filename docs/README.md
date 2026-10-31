# Lahatre ERP documentation

The bilingual MDX site contains the public reading guides. Start with
[French](https://lahatre-erp-docs.vercel.app/fr/docs) or [English](https://lahatre-erp-docs.vercel.app/en/docs).
Website sources are maintained separately.

This directory also retains contributor references and earlier design documents.
Some predate the current contracts. The new public guides and executable source
resolve those discrepancies; historical blueprints are not current API specifications.
The internal [author questionnaire](decisions/documentation-author-questions.md) and
[audit](decisions/documentation-audit/README.md) are excluded from the site.

## Start here

- [Project README](../README.md): project purpose and quick orientation.
- [Docker development environment](infrastructure/docker.md): services, ports,
  and daily commands.
- [Architecture and conventions](architecture/): code, data, database, and
  persistence conventions.
- [Application runtime](architecture/application-runtime.md): middleware,
  service lifetimes, strict Eloquent, factories, and morph maps.
- [Application layers](architecture/application-layers.md): request flow,
  authorization, services, transactions, queries, and responses.
- [Module map](modules.md): module responsibilities and dependency direction.
- [API documentation](api/): response selection, errors,
  and rate limiting.
- [API endpoint map](api/endpoints.md): current routes and access boundaries.
- [Authentication and authorization](authentication/sanctum.md): Sanctum,
  permissions, and authentication context.
- [Development documentation](development/): tools, testing, observability,
  and operational behavior.
- [Dependencies and package roles](development/dependencies.md): direct
  dependencies, runtime use, and known package gaps.
- [Development tools](development/tools.md): integrated tools and useful
  links.
- [Testing and generation](development/testing-and-generation.md): test scope,
  quality checks, and generator contracts.
- [Validation limits](development/validation-limits.md): domain-sized string,
  identifier, and collection limits used by Form Requests.
- [Caching and consistency](development/caching-and-consistency.md): cache
  stores, UnitCache, permissions, and transaction consistency.
- [Queues and communication](development/async-and-communication.md): queues,
  mail, notifications, Reverb, and scheduler status.
- [Roadmap](roadmap.md): current capabilities and planned work.
- [Form Requests/Data migration decision](decisions/form-requests-data-migration.md)
  : a completed architectural decision kept for historical context.

## Documentation areas

### Architecture

- [Coding rules](architecture/coding-rules/)
- [Form Requests and Data](architecture/data/form-requests-and-data.md)
- [Database and Eloquent](architecture/database/)
- [Domain data model](architecture/data-model.md): persisted aggregates,
  tenancy, soft deletion, and historical references.

### Application domains

- [Billing module](modules/billing.md)
- [Catalog feature blueprints](features/)
- [Inventory module documentation](../app-modules/inventory/README.md)
- [Inventory threshold and alert specification](../app-modules/inventory/docs/specs/thresholds-and-alerts.md)
- [Inventory follow-up notes](../app-modules/inventory/docs/plans/events-and-follow-ups.md)
- [IAM permissions](iam/permissions.md)

### Development and operations

- [Code quality](development/code-quality.md)
- [Tools](development/tools.md)
- [Docker and infrastructure](infrastructure/docker.md)
- [Telescope debugging](development/debugging/telescope.md)
- [Observability](development/observability.md): logging, Telescope, Horizon,
  and privacy boundaries.
- [API documentation with Scramble](development/documentation/scramble.md)

## Source-of-truth boundaries

- Public reading guides are maintained in the separate documentation website.
- Requests, services, Resources, routes and tests define current application contracts.
- Contributor references live in this directory and module `README.md`/`docs` files.
  Older blueprints must be checked against current source before implementation.
- Agent rules live in `.ai/rules/` and `.agents/`.
- Decisions, author questions and audit evidence live in [decisions](decisions/).
  They are not imported by the public documentation site.

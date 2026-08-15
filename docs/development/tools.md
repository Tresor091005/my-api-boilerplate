# Key project tools

This document lists the main tools integrated into the project, with a short
description and a link to detailed documentation for each one.

## Application and API

- **Laravel Reverb:** A first-party WebSocket server for efficient real-time
  communication without a third-party dependency. [Read more](../broadcasting/reverb.md)
- **Laravel Sanctum:** The secure and extensible token-authentication approach.
  [Read more](../authentication/sanctum.md)
- **Spatie Permissions:** The team-aware role and permission system with
  automatic permission discovery. [Read more](../iam/permissions.md)
- **API Responses and Error Handling:** The standardized API response and
  business-error strategy. [Read more](../api/responses-and-errors.md)
- **Dedoc Scramble:** An OpenAPI documentation generator that keeps API
  documentation current automatically. [Read more](documentation/scramble.md)

## Architectural blueprints

These documents show how core principles and tools combine to build complete
features. They serve as practical examples and blueprints for future work.

- **Category CRUD:** A deep dive into a hierarchical CRUD module, including
  services, Data classes, and assertions. [Read more](../features/catalog-categories.md)
- **Unit upsert:** A high-performance create/update feature for unit groups,
  including bulk operations, custom validation rules, and business assertions.
  [Read more](../features/catalog-units.md)

## Infrastructure and environment

- **Docker and FrankenPHP:** A multi-container development infrastructure with
  FrankenPHP (Caddy), Reverb, Horizon, and a dedicated scheduler.
  [Read more](../infrastructure/docker.md)

## Code quality and automation

These tools make up the project's quality workflow. [Read more about the code
quality philosophy](code-quality.md).

- **Pint:** A PHP formatter for a consistent style.
- **Rector:** Automated refactoring and large-scale code updates.
- **PHPStan (Larastan):** Detects type errors and inconsistencies before
  execution.
- **IDE Helper:** Generates IDE autocompletion helpers.
- **Husky:** Manages Git hooks to automate pre-commit checks.

## Debugging

- **Laravel Telescope:** Debugging and inspection for local Laravel
  development. [Read more](debugging/telescope.md)

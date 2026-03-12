---
name: project-mapper
description: Map the project architecture and enforce structural integrity. Use when exploring the codebase, determining where a new feature belongs, or verifying that architectural boundaries are respected.
---

# Project Mapper

This skill serves as the architect's assistant, ensuring that the project's modular structure is understood and maintained correctly.

## Project Architecture (Modular Laravel)

The project follows a modular structure where each business domain or technical layer is encapsulated within `app-modules/`.

### 1. Structure Overview
- **`app/`**: Core application infrastructure (not domain-specific).
- **`app-modules/`**: Contains the decoupled domains of the system.
    - **`catalog/`**: Handles products, categories, units, and tags.
    - **`iam/`**: Identity and Access Management (Authentication, Permissions, Sanctum).
    - **`shared/`**: Common DTOs, traits, and utilities shared between modules.
- **`routes/`**: Global route definitions (linking to module controllers).
- **`database/`**: Shared migrations and seeders (though modules may have their own `database/` folders).
- **`docs/`**: Project-wide documentation.

### 2. Feature Placement Guide
- **Domain Logic**: If the logic belongs to a business domain, it MUST go into its respective module.
- **Common Logic**: If logic is needed by 2+ modules, it belongs in `app-modules/shared`.
- **Infrastructure**: Only put things in `app/` if they are truly global (e.g., base middleware, core providers).

### 3. Structural Guardianship
- **Namespace Consistency**: Always use the correct namespace for modules (e.g., `Modules\Catalog\...`).
- **Dependency Flow**: Modules should generally depend on `shared` but avoid tight coupling between each other.
- **Test Placement**: Feature tests for a module should live in `app-modules/{module}/tests`.

## Workflow for Mapping

1.  **Identify Boundary**: Determine which module a request relates to.
2.  **Locate Symbols**: Use `grep_search` and `glob` to find related classes, traits, or functions.
3.  **Cross-Reference**: Verify how the feature interacts with other modules (e.g., does a `Catalog` service use an `IAM` model?).
4.  **Enforce Patterns**: When adding new files, ensure they follow the pattern of the module (e.g., `src/Models`, `src/Http/Controllers`, etc.).

## Quick Lookups
- `app-modules/*/composer.json`: Quickly see module dependencies.
- `app-modules/*/routes/`: Find module-specific API or Web routes.
- `config/app-modules.php`: View the list of active modules.

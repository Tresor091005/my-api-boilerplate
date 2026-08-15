---
paths:
  - app-modules/*/src/Providers/**
  - app-modules/*/src/Support/**
  - app-modules/*/src/Integrations/**
  - app-modules/*/src/Contracts/**
  - app-modules/*/src/Registries/**
  - app-modules/*/src/Pipelines/**
  - app-modules/*/src/Traits/**
---

# Extension Points

- Treat extension points as exceptional design decisions. Start with a direct
  class, service, constructor dependency, or explicit method before introducing
  an abstraction family.
- Use one service provider per module when the module needs one. Keep providers
  limited to bindings, configuration merging, and genuinely unique framework
  integration that cannot be inferred from conventions. Internachi/Modular and
  Laravel conventions already discover module migrations, translations, views,
  routes, and ordinary package structure; do not manually register those again.
  Morph maps belong here only when their registration is not already handled by
  the project's established mechanism.
- Do not resolve or manipulate an object owned by another module or package
  from `register()`. Provider registration order is not a safe dependency boundary;
  perform cross-module integration in `boot()` using method injection after all
  providers have registered their bindings. Keep `register()` for the current
  module's bindings and configuration.
- Keep `Support` code business-agnostic and free from implicit HTTP,
  authorization, or tenant assumptions. A helper must not become a hidden
  service or policy layer.
- Keep external technical dependencies behind `Integrations`. Translate
  business needs into technical calls there, but leave application business
  decisions in services.
- Use `Contracts` only for a real module boundary, external integration, or
  intentionally replaceable capability. Do not create an interface solely to
  abstract one local implementation.
- A module interface is the public inter-module API: expose only the methods
  that other packages are expected and permitted to call. Keep HTTP-specific,
  adapter-specific, and other module-internal operations on concrete services
  or dedicated internal collaborators instead of adding them to the shared
  contract.
- Avoid `Registries` by default. They are a last resort for a genuinely dynamic
  mapping or discovery problem that cannot remain explicit. Each registry is a
  design family of its own: document its ownership, registration lifecycle,
  duplicate/conflict behavior, and lookup contract. It must never become a
  hidden service locator for business workflows.
- Avoid `Pipelines` by default. Use one only when a variable, ordered sequence
  of independent processing stages is essential and a direct service would be
  materially less clear. Keep the pipeline's input/output contract explicit;
  do not use it to split a normal workflow into arbitrary classes.
- Use `Traits` when a concrete behavior or set of methods is genuinely repeated
  across multiple compatible classes. Keep the trait focused, give it an
  explicit host contract, and do not extract code merely to avoid a small
  duplication. A trait must not hide authorization, transactions, tenant
  resolution, queries, or broad orchestration from the owning class; use a
  collaborator or service when the behavior has its own lifecycle or identity.

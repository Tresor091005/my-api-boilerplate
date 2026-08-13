---
paths:
  - app/**
  - app-modules/*/resources/lang/**
  - app-modules/*/src/**
  - app-modules/*/database/**
  - routes/**
---

# Localization

This is a cross-cutting rule. Apply it anywhere text can reach a user or
operator: HTTP responses, validation, exceptions, notifications, mail,
console commands, permission descriptions, and user-facing events. Internal
developer-only logs and machine identifiers do not need localization unless
they are also presented to a user.

## Directory and File Taxonomy

- Store module translations in `app-modules/<module>/resources/lang/<locale>/<file>.php`.
- Keep the same directory shape and file taxonomy across modules and locales. Add a file only when the module has content for that purpose, but do not invent module-specific locations such as `src/Exceptions/lang` or one file per exception class.
- Use purpose-specific files consistently:
  - `exceptions.php`: messages constructed by business or technical exception factories.
  - `validation.php`: Form Request, Rule, and domain-validator failures, including validation attributes when needed.
  - `messages.php`: general user-facing success, status, and response messages.
  - `notifications.php`: notification titles, bodies, and action text.
  - `console.php`: CLI output, permission descriptions, and command-facing text.
- Keep exception, validation, message, notification, and console content separate. A validation failure is not an exception message just because both indicate an unsuccessful operation.

## Keys, Namespaces, and Values

- Resolve module translations with `__('<module>::<file>.<key>')`; resolve shared translations with `__('shared::<file>.<key>')`.
- Use descriptive `snake_case` keys. Nested keys may group one bounded context or operation, but preserve the same grouping style within and across modules.
- Reuse an existing shared key only when its wording and meaning are genuinely identical. Keep module-specific wording in the owning module.
- Translation files declare strict types and return arrays. Use `:placeholder` for dynamic values; do not introduce `{placeholder}` syntax.
- English is the reference locale. Every additional locale must preserve the English key tree and placeholder set.
- Write stable, explicit English messages. End complete sentences with a period; labels, titles, and short CLI fragments may omit it.
- Do not put identifiers, exception context, SQL fragments, or internal diagnostic metadata into translation values. Pass stable machine-readable context separately.

## Code by Boundary

- No hardcoded user-facing text in Controllers, Services, Models, Assertions, Exceptions, Rules, Form Requests, Jobs, Listeners, Notifications, mail, Console commands, or event payloads.
- Form Request and Rule failures resolve keys from `validation.php`; custom attribute labels belong in the same validation namespace.
- Business exceptions pass a translated `exceptions.php` message to `AssertionException`; their context remains structured and non-localized.
- Notification and mail text belongs in `notifications.php` or a purpose-specific mail file and should be resolved at rendering time when the recipient locale may differ from the queueing process.
- Console commands use `console.php`, even when the command belongs to a module that also has HTTP messages.
- Internal logs may remain technical English, but any text returned in an API response, displayed by a CLI command, sent by notification/mail, or shown in a user-facing event must use a translation key.

## Localization Workflow

- When reviewing or changing a scoped area, scan only that area for hardcoded
  user-facing strings. Distinguish display text from internal identifiers,
  database values, technical logs, and machine-readable error context.
- Before adding a key, search the owning module and shared translations for a
  genuinely identical existing meaning. Reuse shared text only when its wording
  and semantics are identical; otherwise create the key in the owning module.
- When adding or changing a translation, update the English reference tree and
  every existing locale in the same module, preserving keys and placeholders.
- Replace the call site with the correctly namespaced `__()` lookup and verify
  that the selected file matches the boundary: exception, validation, message,
  notification, or console.
- After localization changes, run the affected localization/architecture tests
  and inspect the resulting translation tree. Do not use a broad automated
  string replacement without reviewing whether each string is user-facing.

## References

- `app-modules/inventory/resources/lang/en/exceptions.php` and `validation.php` show the separation between business failures and input/domain validation.
- `app-modules/iam/resources/lang/en/messages.php` and `console.php` show general feedback and CLI-specific content in separate files.
- `app-modules/shared/resources/lang/en/validation.php` shows a module-agnostic validation key in the shared namespace.
- `app-modules/*/src/Exceptions/**`, `src/Rules/**`, `src/Validation/**`, and `src/Http/Requests/**` show the expected translation call sites.
- `tests/Feature/Architecture/LocalizationIntegrityTest.php` guards referenced key existence, strict translation file structure, and Laravel placeholder syntax.

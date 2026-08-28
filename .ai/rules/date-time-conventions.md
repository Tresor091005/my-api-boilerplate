---
paths:
  - app/**
  - app-modules/*/src/**
  - app-modules/*/database/**
  - database/**
---

# Date and time conventions

- Use the `_at` suffix for business values that represent an instant with a
  time. Store them as database timestamps and cast them as
  `immutable_datetime` in Eloquent models.
- Use the `_date` suffix for civil calendar dates with no time of day. Store
  them as database `date` values and do not invent a timezone for them.
- API inputs and outputs for business `_at` fields use the shared
  `Lahatre\Shared\Rules\Rfc3339Utc` validation rule. Do not use Laravel's
  generic `date` rule when the field contract is an `_at` timestamp.
- `Rfc3339Utc` accepts UTC written with `Z` or `+00:00`, with optional
  millisecond precision. Examples are `2026-08-28T14:30:00Z` and
  `2026-08-28T14:30:00.000Z`.
- Keep the validation rule separate from relative constraints such as
  `after:now`. The shared rule validates the wire format and UTC offset only.
- Form Requests validate the wire format. Data classes convert already
  validated values to `CarbonImmutable`, and models keep the
  `immutable_datetime` cast for timestamp fields.
- Laravel-managed fields such as `created_at`, `updated_at`, and `deleted_at`,
  and framework-owned temporal fields, are outside this API input convention.

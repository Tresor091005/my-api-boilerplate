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
- Use the `_time` suffix for a local wall-clock time without a date or
  timezone. Store it as a database `time` value. It is only meaningful when
  combined with a related `*_date` value and an IANA timezone.
- Organization settings must use the organization's real geographical IANA
  timezone, such as `Africa/Porto-Novo`, for local business schedules. `UTC`
  is a valid IANA timezone identifier, but it is only appropriate when the
  organization intentionally operates on UTC rather than local civil time.
- Use the organization timezone by default for organization-owned business
  dates and local schedules; do not add a timezone column to every model. Add
  a model-level timezone only when that schedule is independently configurable,
  belongs to another owner such as a user, or must preserve its timezone when
  organization settings change. Name it for its meaning, such as
  `reminder_timezone`, and validate it with `IanaTimezone`.
- API inputs for business `_date` fields use the shared
  `Lahatre\Shared\Rules\YmdDate` validation rule and the exact `Y-m-d` format.
- API inputs for business `_time` fields must use a strict local-time format
  such as `H:i` or `H:i:s`, according to the field contract. Validate the
  date, time, and IANA timezone separately in the Form Request with the shared
  `YmdDate`, `LocalTime`, and `IanaTimezone` rules, then combine them in the
  backend when a local-time calculation is needed.
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
- A civil date does not carry a timezone. Whenever backend work turns a
  `*_date` value into an instant—for reminders, expiration jobs, notifications,
  or other scheduled work—resolve the IANA timezone from the owning
  organization. If a local `*_time` is supplied, combine the date, time, and
  timezone dynamically at the moment the task is evaluated. Do not persist a
  derived `_at` by default: keeping the civil values and timezone context
  allows recalculation when timezone rules or DST change. Persist a derived
  UTC `_at` only when the domain explicitly requires a frozen execution
  instant, an immutable audit record, or a performance snapshot.
- Laravel-managed fields such as `created_at`, `updated_at`, and `deleted_at`,
  and framework-owned temporal fields, are outside this API input convention.

# Date and time conventions

The API separates instants from civil calendar dates.

## Timestamps

Use `_at` for a value that identifies an instant, including its time of day.
Examples include `expires_at` and `effective_at`.

Business `_at` values sent through the API must be UTC RFC 3339 values. The
shared `Rfc3339Utc` rule accepts these forms:

```text
2026-08-28T14:30:00Z
2026-08-28T14:30:00.000Z
2026-08-28T14:30:00+00:00
2026-08-28T14:30:00.000+00:00
```

The frontend may use `new Date().toISOString()`. The backend validates the
wire format, converts the value to `CarbonImmutable`, and stores it through an
Eloquent `immutable_datetime` cast.

Requests should combine `new Rfc3339Utc` with a separate business constraint
when needed, for example `after:now` for a future expiry.

Laravel-managed fields such as `created_at`, `updated_at`, and `deleted_at`
are framework concerns and do not need the API rule.

## Civil dates

Use `_date` for a calendar date with no time of day or timezone. Store it as a
database `date` value and validate API inputs with the shared `YmdDate` rule.
The wire format is exactly `Y-m-d`, for example:

```text
2027-01-31
```

Do not convert a civil date to UTC. A timezone conversion can move it to the
previous or next calendar day.

## Local times

Use `_time` for a wall-clock time without a date or timezone. Store it as a
database `time` value. A local time is meaningful only with a related
`*_date` value and an IANA timezone.

For a locally configured schedule, the API may receive:

```text
reminder_date     = 2027-01-30
reminder_time     = 08:00:00
reminder_timezone = Europe/Paris
```

Validate the date, time, and timezone separately in the Form Request with the
shared `YmdDate`, `LocalTime`, and `IanaTimezone` rules. The backend combines
them dynamically when it needs to evaluate the reminder, taking the current
timezone rules and DST into account. These local input fields are appropriate
when the business meaning is “at this local time”; for an already determined
instant, use only an RFC 3339 UTC `_at` value.

## Deriving instants from civil dates

When backend work needs an exact moment from a civil date—for example a
reminder, expiration job, notification, or other scheduled task—use the IANA
timezone of the owning organization as the date's context. The date itself
remains unchanged and timezone-free.

Calculate the local time dynamically when the task is evaluated, taking the
organization's current timezone rules and DST into account. Do not persist a
derived `_at` by default: the civil values and timezone context are the source
of truth. Persist a derived UTC `_at` only when the domain explicitly requires
a frozen execution instant, an immutable audit record, or a performance
snapshot.

For example:

```text
expiration_date       = 2027-01-31
organization.timezone = Europe/Paris
reminder_date         = 2027-01-30
reminder_time         = 08:00:00
```

The backend calculates the reminder dynamically using the organization's
timezone. No separate local datetime field and no derived `reminder_at` field
are required for the normal case.

## Choosing a temporal representation

Use these questions when introducing a timestamp-related field:

| Question | Yes | No |
| --- | --- | --- |
| Does the value identify one precise instant globally? | Use one `_at` field, sent and handled as UTC RFC 3339. | Continue. |
| Is it only a calendar day, with no time-of-day meaning? | Use one `_date` field as a native SQL `date`. | Continue. |
| Must that day be interpreted in the organization's local context? | Use `_date` and resolve the organization's IANA timezone dynamically. | Continue. |
| Can the user configure a local hour for that day? | Use related `_date` + `_time` fields and the IANA timezone context. | Continue. |
| Must the exact calculated instant remain frozen for audit or execution? | Persist a derived UTC `_at` explicitly, with a documented reason. | Keep the civil values and calculate dynamically. |

The normal local-scheduling representation is therefore `_date` + `_time` plus
the organization's timezone. A derived `_at` is an exception, not an automatic
replacement for those source values.

Organization settings should contain the organization's geographical IANA
timezone, such as `Africa/Porto-Novo`. `UTC` is technically a valid IANA
timezone identifier, but it should be used only when the organization
intentionally operates on UTC rather than its local civil time.

The organization timezone is the default context for organization-owned
business data. Do not duplicate it on every model. Add a timezone column to a
model only when the local schedule is independently configurable, belongs to a
different owner such as a user, or must remain tied to its original timezone
after organization settings change. Give that column a domain-specific name,
such as `reminder_timezone`, and validate it with `IanaTimezone`.

For example, inventory expiration belongs to the organization and uses
`organization_settings.timezone`. A user-specific reminder may instead use
`reminder_timezone` on the reminder record. The timezone is selected from the
data's owner and context; it is not copied mechanically onto every table.

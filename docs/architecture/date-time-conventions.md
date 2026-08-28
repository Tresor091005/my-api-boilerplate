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
database `date` value and treat it as `Y-m-d`, for example:

```text
2027-01-31
```

Do not convert a civil date to UTC. A timezone conversion can move it to the
previous or next calendar day.

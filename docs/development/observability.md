# Observability

## Logging

The default local stack writes to `storage/logs/laravel.log` through the
`single` channel at the configured `LOG_LEVEL`. Daily, stderr, syslog,
Papertrail, Slack, errorlog, null, and emergency channels are available for
deployment-specific wiring.

Authentication context adds user, organization, member, member-role, role, and
guard identifiers to Laravel's request context when a request is authenticated.
This context should be used when correlating logs rather than copying identity
fields into every message.

## Telescope

Telescope is a development dependency and is enabled locally at `/debug`.
Configured watchers cover requests, queries, models, cache, Redis, jobs, mail,
notifications, events, gates, commands, views, schedules, exceptions, and logs.
It stores entries in the database and can itself queue pending updates with a
configurable delay (10 seconds by default). Disable it in production unless a
protected and privacy-reviewed deployment is intended.

In non-local environments, request cookies and CSRF headers are hidden. The
Telescope and Horizon gates currently have empty allow-lists, so access must be
configured deliberately before use outside local development.

## Horizon

Horizon provides queue worker supervision and metrics at the configured
`HORIZON_PATH` (`/queues` locally). Its retention, wait thresholds, worker
limits, and failed-job behavior are documented with the queue configuration in
[queues and communication](async-and-communication.md).

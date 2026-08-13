# Queues, mail, notifications, and realtime communication

This page records both the infrastructure that exists and the application
features that do not yet exist, so an empty directory is not mistaken for an
omission.

## Queues and Horizon

The Docker stack runs a dedicated Horizon process. Local application defaults
use Redis queues and the `default` queue. Horizon supervises that queue with
automatic balancing, one process by default, up to three local processes, one
attempt locally, a 60-second worker timeout, and a 90-second retry window.

Failed jobs use the `failed_jobs` database table. Job batches use the
`job_batches` table. Queue connections currently have `after_commit: false`;
code dispatching a job from a transaction must therefore explicitly defer the
dispatch if it depends on committed data.

There are currently no application jobs under `app/Jobs` or module job
directories. Horizon is operationally ready, but no business workflow is
queued yet. The absence of jobs is intentional and should change together with
the relevant business documentation and tests.

## Mail

The Docker development environment sends SMTP mail to `mailpit:1025`; Mailpit
is exposed on `http://localhost:28419`. The default sender is configured by
`MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME`.

Laravel also exposes log, array, failover, SES, Postmark, Resend, sendmail,
and round-robin mailer configurations. The current password-reset endpoint
creates and returns a reset link; it does not send that link by email. There
are no application mailables or custom password-reset notification classes.
Password reset links are built from `APP_URL`.

## Notifications

No application notification classes, notification database table, or broadcast
notification channel currently exists. If notifications are added, document
whether they are synchronous, queued, persisted, broadcast, or sent by email;
do not infer that Horizon or Reverb automatically delivers them.

## Realtime broadcasting

Laravel Reverb is enabled as the configured broadcaster and runs internally on
`reverb:6001`, published to the host as port `28418`. Browser clients use
`VITE_REVERB_HOST=localhost` and `VITE_REVERB_PORT=28418`; backend containers
use the internal service hostname.

The project currently defines no application events implementing broadcast
contracts and no private/presence channel authorization beyond Laravel's
default example channel. Reverb is infrastructure prepared for future events,
not an active business notification system.

## Scheduler

No application schedule is currently registered. The scheduler container is
present and runs `schedule:work`, but there are no project-owned scheduled
tasks to execute. Telescope can observe scheduled tasks when they are added.

# Queues, mail, notifications, and realtime communication

This page records the queue, mail, notification, and realtime services used by
the application.

## Queues and Horizon

The Docker stack runs a dedicated Horizon process. Local application defaults
use Redis queues. Horizon supervises the `default` and `email` queues with
automatic balancing, one process by default, up to three local processes, one
attempt locally, a 60-second worker timeout, and a 90-second retry window.
Queue names are defined in `Lahatre\Shared\Enums\QueueName`.

Failed jobs use the `failed_jobs` database table. Job batches use the
`job_batches` table. Queue connections currently have `after_commit: false`;
code dispatching a job from a transaction must therefore explicitly defer the
dispatch if it depends on committed data.

IAM dispatches `SendOrganizationRegistrationLink`, `SendInvitationLink`, and
`SendPasswordResetLink` jobs to the `email` queue. Horizon must run for these
messages to be delivered.
Registration and invitation tokens are persisted before their jobs are queued
after commit. These jobs carry encrypted payloads and only deliver the current
token; retries and out-of-order processing cannot rotate tokens.

## Mail

The Docker development environment sends SMTP mail to `mailpit:1025`; Mailpit
is exposed on `http://localhost:28419`. The default sender is configured by
`MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME`.

Laravel also exposes log, array, failover, SES, Postmark, Resend, sendmail,
and round-robin mailer configurations. Email login codes, organization registration,
and invitation links are sent by email. Organization and invitation links use
the frontend URL configured by
`FRONTEND_URL` and their paths in `config/frontend.php`.

## Notifications

IAM uses `OrganizationRegistrationLinkNotification`, `InvitationLinkNotification`,
and the email login code notification for email delivery from queued jobs. No notification database
table or broadcast notification channel currently exists.

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

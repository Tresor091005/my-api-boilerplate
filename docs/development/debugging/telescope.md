# Laravel Telescope

**Laravel Telescope** is a debugging tool that provides an overview of
incoming requests, exceptions, log entries, database queries, queued jobs,
emails, notifications, caches, and more in a Laravel application. It is
intended primarily for **local development environments**.

## Usage

Telescope is included as a development dependency (`require-dev` in
`composer.json`), so it is not deployed to production by default.

### Enable and access Telescope

Telescope's enabled state and path are configured through `.env`:

- **`TELESCOPE_ENABLED=true`:** Enables Telescope. Set it to `false` in
  production.
- **`TELESCOPE_PATH=debug`:** Sets the web interface path. The example
  environment uses `/debug`.

To access Telescope, set `TELESCOPE_ENABLED=true` in the local `.env` and open
the application URL followed by `/debug`, or the configured path. For example:
`http://localhost:28417/debug`.

### Key features

- **Requests:** Inspect request data, headers, sessions, and responses.
- **Exceptions:** View exceptions thrown by the application.
- **Logs:** Access log entries.
- **Database:** Inspect executed SQL, execution times, and bindings.
- **Jobs:** Monitor queued job execution, data, connections, and timings.
- **Cache, mail, notifications:** Track interactions with these services.

## Important

Telescope collects a large amount of data. Enable it only in controlled
environments, such as development or staging, and disable it in production for
performance and security reasons.

See the [official Laravel Telescope documentation](https://laravel.com/docs/12.x/telescope) for advanced configuration and filters.

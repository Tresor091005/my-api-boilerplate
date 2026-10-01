# Docker infrastructure and development environment

This project uses a robust Docker architecture based on **serversideup**
images and optimized for Laravel with **FrankenPHP**.

## 1. FrankenPHP in normal mode

The application runs on `serversideup/php:8.4-frankenphp`. FrankenPHP uses
**normal mode** through Caddy rather than pure worker mode by default.

- **Caddy benefits:** Normal mode provides Caddy's web-server handling,
  compression, and configuration simplicity.
- **Octane flexibility:** The setup is Octane-ready. If performance needs
  change, switching to Laravel Octane only requires changing the startup
  command or FrankenPHP configuration.

## 2. Multi-container architecture

To keep development close to production and separate responsibilities, the
`docker-compose.yml` infrastructure is split into specialized services.

### Application services (shared image)

All these services use the same `Dockerfile` to keep dependencies and code
consistent:

- **`app`:** Main web server (FrankenPHP/Caddy), published at
  `http://localhost:28417`.
- **`reverb`:** Laravel Reverb WebSocket server, published at
  `ws://localhost:28418` and available to containers at `reverb:6001`.
- **`horizon`:** Queue management through Laravel Horizon.
- **`scheduler`:** Scheduled-task manager (`artisan schedule:work`).

### Infrastructure services

- **`db`:** PostgreSQL 18 (Alpine), published at `localhost:28420` for
  development tools and available to containers at `db:5432`. Tests use the
  `my_api_boilerplate` database directly and may reset it.
- **`redis`:** Redis 8 (Alpine) for cache, queues, and Reverb.
- **`mailpit`:** Development email capture tool, with its web interface at
  `http://localhost:28419` and internal SMTP server at `mailpit:1025`.

## 3. Development optimizations

Use the provided Makefile and run commands inside the container to keep PHP
8.4, extensions, and permissions consistent. These shortcuts are recommended.

## 4. Useful commands (through Makefile)

### Container management

- **`make up`:** Start containers and enter the application shell.
- **`make down`:** Stop containers.
- **`make rs`:** Restart the environment.
- **`make ps`:** List active containers.
- **`make logs <service>`:** Show service logs, for example `make logs app`.

### Application commands (inside Docker)

- **`make a <cmd>`:** Alias for `php artisan`, for example `make a migrate`.
- **`make c <cmd>`:** Alias for Composer, for example `make c install`.
- **`make test`:** Run the Pest test suite.
- **`make pint`:** Run Laravel Pint.
- **`make phpstan`:** Run PHPStan (Larastan).
- **`make rector`:** Run Rector automated refactorings.

## 5. Internal alias (inside the container)

When already inside the container through `make up` or `docker compose exec`,
the `.bashrc` configures an `a` alias (`alias a='php artisan'`).

## 6. Infrastructure and health checks

- **Health checks:** Every service has a health check to ensure dependencies
  such as the database and Redis are ready before startup.
- **Permissions:** The Dockerfile manages user IDs (`1000:1000`) to avoid
  permission problems on mounted files.

## 7. Session location setup

Create a [MaxMind account with free GeoLite access](https://www.maxmind.com/en/create-account).
Find your account ID in [Account Information](https://support.maxmind.com/knowledge-base/articles/find-your-maxmind-account-id)
and generate a key in [Manage License Keys](https://support.maxmind.com/knowledge-base/articles/generate-a-maxmind-license-key).
The key is displayed only once. Add both values to `.env`:

```dotenv
MAXMIND_ACCOUNT_ID=your_account_id
MAXMIND_LICENSE_KEY=your_license_key
```

For immediate availability, run this initialization command once after starting
the environment:

```bash
docker compose exec -T app php artisan iam:geoip-update
```

The command installs the database at `storage/app/private/GeoLite2-City.mmdb`.
It can be skipped: the existing scheduler installs a missing database and checks
for updates daily at 03:00 in the application timezone. Until installation,
session locations are null. If configuration was previously cached, clear or
rebuild that cache after changing `.env`.

Keep Horizon and the scheduler running. Their containers already share the
database file through the project mount; no additional MaxMind service is
needed. GeoIP lookups use the local file without external HTTP requests.
The downloaded database and credentials remain outside Git.

Local Docker requests commonly arrive with a private IP such as `192.168.65.1`,
which cannot be geolocated. Public deployments must expose the client's public
IP to Laravel, with trusted proxies configured when applicable. See
[IAM session enrichment](../modules/iam.md#email-otp-and-sessions) for metadata,
queue behavior, and update failure handling.

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
- **`frontend`:** Separate Node 24 IAM demo at `http://localhost:28421`, with
  a same-origin proxy to the application API. See [demo setup](#9-iam-demo-frontend).

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

## 8. Google sign-in setup

Create or select a project in [Google Auth Platform](https://console.cloud.google.com/auth/overview).
Configure Branding and Audience, then create an OAuth client of type **Web application**
under Clients. Register the frontend's exact origin, including its development
port, as an Authorized JavaScript origin. For example, `http://localhost:28421`.
Copy the client ID into `.env`:

```dotenv
GOOGLE_CLIENT_ID=your_client_id.apps.googleusercontent.com
FRONTEND_URL=http://localhost:28421
```

Use that same client ID in the frontend Google Identity Services SDK. This
integration receives ID credentials through its JavaScript callback and verifies
public signatures; it does not need a client secret, service-account JSON file,
Google access token, or refresh token. See [Google's setup guide](https://developers.google.com/identity/gsi/web/guides/get-google-api-clientid).

Install the locked dependencies and apply the migrations:

```bash
docker compose exec -T app composer install --no-interaction
docker compose exec -T app php artisan migrate --no-interaction
docker compose exec -T app php artisan config:clear
```

The frontend first requests a Google challenge, keeps its ID and nonce locally,
and passes the nonce to `google.accounts.id.initialize`. Submit Google's
`response.credential` with that challenge ID to the API. Do not retrieve a
challenge ID from URL parameters supplied by another party. Use a same-origin
API proxy or direct API requests. `config/cors.php` enables `/v1/*` preflight
and bearer headers for the origin derived from `FRONTEND_URL`.
The API additionally checks browser Origin against `FRONTEND_URL`.

Google is disabled when `GOOGLE_CLIENT_ID` is empty; email OTP and existing
organization/invitation flows continue working. The scheduler prunes expired
Google challenges daily at 02:45. Public verification certificates are cached
according to Google's Cache-Control lifetime. No manual certificate downloads
or Google-specific queue worker are required.

The [endpoint map](../api/endpoints.md#google-authentication) describes the
Bruno requests, profile completion, and OTP confirmation before linking.

## 9. IAM demo frontend

The `frontend/` directory is an independent client with its own Node server and
package manifest. It has no npm dependencies or build step. Start it with:

```bash
docker compose up -d frontend
```

Open `http://localhost:28421/`. The service is bound to loopback and proxies
`/api/v1/*` to `http://app:8080/v1/*`; Laravel owns authentication, profile
requirements, authorization, and all business state. `/config.json` exposes
only the public `GOOGLE_CLIENT_ID` from the root `.env`, never other environment
values. After changing that ID, rerun `docker compose up -d frontend` and clear
the backend configuration cache. Authorize `http://localhost` and
`http://localhost:28421` in Google, and set `FRONTEND_URL=http://localhost:28421`.
The demo redirects HTML pages opened through the equivalent loopback address
`127.0.0.1` to the configured `localhost` origin, preserving emailed link
parameters. API requests keep their original Origin header.

The UI supports email OTP signup/login, Google signup/login, OTP confirmation
before Google linking, profile completion, direct organization creation, emailed
organization registration, public and authenticated invitation acceptance,
organization/member-role switching and default preference, profile edits,
session listing and individual/all revocation. Organization tools also allow
editing name/timezone, creating custom roles, inviting members, and resending or
cancelling invitations. APIs enforce permissions; an unavailable capability
shows the API error without logging the user out. Email links open the relevant
screen automatically. Development emails are available at `http://localhost:28419`.

Challenges and Google credentials remain in memory and are managed by the
client. An invitation link opens its acceptance form directly, without requiring
Google or an OTP. Public acceptance signs the recipient in and opens profile
completion when names are missing. A matching authenticated account keeps its
session; a different signed-in account must explicitly sign out before continuing.
Sanctum tokens are kept in `sessionStorage` for the current browser tab;
signing out clears them. HTTP 401 clears the session, profile-incomplete 403
opens profile completion, and other 403 responses preserve the account session.
The client automatically switches to an available default member role after
login. Profile read/update and session controls remain available before the
profile is complete. Reloading loses a pending Google-link challenge; restart
Google linking if its ten-minute window expires. The optional manual helper
for Bruno remains at `/google-test.html`.

To run outside Docker with Node 24 or later:

```bash
npm --prefix frontend start
```

This reads the root `.env` locally, proxies to `APP_URL`, and listens on
`127.0.0.1:28421`. Stop the Docker frontend first to free the port. Test the
server with `npm --prefix frontend test`. Bruno remains the API specification
and can be used independently of the demo.

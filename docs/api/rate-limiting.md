# API rate limiting

This document describes the API rate-limiting configuration and strategy used
to protect the system's security and stability.

## 1. Overview

The project uses Laravel's native rate-limiting features to limit the number
of requests a client, identified by its IP address or user ID, can make within
a given period.

Limits are defined in `App\Providers\RateLimitServiceProvider`.

**Infrastructure note:** Rate limiting uses Laravel's database cache store.
This keeps the setup small while sharing the application's existing PostgreSQL
dependency. Redis remains dedicated to cache, queues, and Reverb.

### Client IP behind a proxy

Limiters keyed on the IP use `$request->ip()`, which also feeds session IPs
and geolocation. Behind a reverse proxy (the SPA's same-origin `/api` proxy
on Vercel or Vite, nginx, a load balancer) the TCP peer is the proxy, so all
clients share its bucket unless the proxy is trusted.

`TRUSTED_PROXIES`, read through `config/trustedproxy.php` by Laravel's
`TrustProxies` middleware, selects the trusted proxies:

- **Empty (default):** no proxy is trusted and `X-Forwarded-*` headers are
  ignored. Use it when clients reach the API directly, including local
  development.
- **IPs or CIDR ranges, comma-separated:** Laravel reads `X-Forwarded-For`
  from right to left and keeps the first address that is not a trusted proxy,
  so an address forged by the client at the left of the header is skipped.
  Prefer this for nginx or Docker, for example the proxy's network range.
- **`*`:** every caller is treated as a proxy and the leftmost
  `X-Forwarded-For` address becomes the client IP. Use it only when the API
  cannot be reached except through a proxy that overwrites `X-Forwarded-For`,
  such as Vercel, whose egress addresses are not a fixed list. If the API is
  also reachable directly, any client can send its own `X-Forwarded-For`, take
  a new IP on every request, bypass the `auth` and `google-auth` limits, and
  falsify session IPs.

Only `X-Forwarded-For` and `X-Forwarded-Proto` are trusted
(`bootstrap/app.php`). Forwarded host, port, and prefix headers are ignored so
absolute URLs keep the API's own host.

## 2. Configured limiters

### `api` (global API)

This limiter applies to most API endpoints (`v1/*`).

- **Limit:** 90 requests per minute.
- **Identification:** User ID when authenticated, otherwise IP address.
- **Middleware:** `throttle:api`.
- **Application:** Included globally in the `api` middleware group in
  `bootstrap/app.php`, so it applies by default to all API routes.

### `auth` (authentication)

This limiter applies specifically to sensitive authentication endpoints to
prevent brute-force attacks.

- **Limit:** 5 requests per minute.
- **Identification:** IP address only.
- **Middleware:** `throttle:auth`.
- **Affected endpoints:**
  - `POST /v1/auth/email-challenges`
  - `POST /v1/auth/email-challenge-verifications`
  - `POST /v1/auth/organization-registration-tokens`
  - `POST /v1/auth/organization-registrations`
  - `POST /v1/iam/invitations/accept`

### `google-auth` (Google authentication)

- **Limit:** 20 requests per minute, shared between Google challenge creation,
  verification, and identity linking.
- **Identification:** IP address.
- **Middleware:** `throttle:google-auth`.
- **Affected endpoints:** `POST /v1/auth/google-challenges`,
  `POST /v1/auth/google-challenge-verifications`, and
  `POST /v1/auth/google-identities`.

This budget is independent of the email `auth` budget. The frontend prepares
Google automatically and reuses an unexpired challenge when changing screens.
The global `api` limit still applies.

Other authentication routes, such as `/me` and `/logout`, use the default
`api` limiter.

## 3. Rate-limit responses

When a client exceeds its limit, the API returns a standardized response:

- **HTTP status:** `429 Too Many Requests`.
- **Headers:**
  - `X-RateLimit-Limit`: total number of allowed requests.
  - `X-RateLimit-Remaining`: number of remaining requests.
  - `Retry-After`: number of seconds to wait before the next request.

## 4. Tests

Rate-limiting configuration is tested in `tests/Feature/RateLimitTest.php`.
Client IP resolution behind trusted proxies, including separate `auth`
buckets per forwarded client, is tested in
`tests/Feature/Integration/TrustedProxyIntegrationTest.php`.

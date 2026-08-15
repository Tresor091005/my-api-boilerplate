# API rate limiting

This document describes the API rate-limiting configuration and strategy used
to protect the system's security and stability.

## 1. Overview

The project uses Laravel's native rate-limiting features to limit the number
of requests a client, identified by its IP address or user ID, can make within
a given period.

Limits are defined in `App\Providers\RateLimitServiceProvider`.

**Infrastructure note:** To keep rate limiting independent from application
cache and queue load, it uses a dedicated Redis instance (`redis_limiter`) via
the `limiter` cache store.

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
  - `POST /v1/auth/{type}/login`
  - `POST /v1/auth/register`

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

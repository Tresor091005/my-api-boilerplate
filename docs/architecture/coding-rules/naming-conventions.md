# Naming and style conventions

This document defines the naming and style conventions used in the project to
keep the codebase consistent and readable.

## 1. General PHP conventions

### Variables

Use `camelCase` for all local variables.

```php
$myVariable = 'value';
$usersList = ['user1', 'user2'];
```

### Functions and methods

Use `camelCase`.

```php
public function myFunction($argument)
{
    // ...
}
```

### Class properties

Use `camelCase` by default.

```php
class MyClass
{
    public string $myProperty;
}
```

**Exception:** Eloquent models keep database attributes in `snake_case`. Data
classes always use `camelCase` PHP properties and explicitly map payload keys
from `snake_case` in `::fromArray()`.

```php
// Eloquent model
$user->is_active = true;

// Transport data
final readonly class UserData
{
    private function __construct(
        public string $userName,
        public string $emailAddress,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            userName: $data['user_name'],
            emailAddress: $data['email_address'],
        );
    }
}
```

## 2. Database

All table, column, and constraint names must be in English `snake_case`.

- **Tables:** plural names, for example `users` and `blog_posts`.
- **Columns:** singular names, for example `title`, `created_at`, and
  `is_published`.
- **Foreign keys:** `[singular_table]_id`, for example `user_id` in `posts`.

## 3. API and payloads

All keys in JSON requests and responses must use `snake_case`, consistently
with database column names.

```json
{
  "user_name": "John Doe",
  "is_active": true,
  "created_at": "2023-10-27T10:00:00Z"
}
```

Boolean columns normally use the `is_`, `has_`, `can_`, or `should_` prefixes.
Approved semantic names may be used when they express the capability more
clearly; `stock_tracking_enabled` is the current example. Do not introduce
another exception without updating the architecture test and documenting the
domain meaning.

## 4. Routes

### Paths

Use `kebab-case` for URL segments containing multiple words. Resources should
be plural.

- `GET /users`
- `GET /users/{user}`
- `POST /users/{user}/publish-resume`

### Names

Use dot notation for route names. This makes them easier to organize and
reference.

- `users.index`
- `users.show`
- `user.publish.resume`

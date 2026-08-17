# API: standardized responses and business error handling

This document describes the standardized JSON response structure and the
business-error strategy based on assertion objects.

## Response contracts

Every API route has a contract declared in `config/response-contracts.php` or
in the owning module's `app-modules/<module>/config/response-contracts.php`.
The key is the complete route name. Resource-producing routes declare their
default shape and relation contract. An empty definition is reserved for an
endpoint with no response representation, such as a deletion; the owning
module must explain that exception with an inline comment beside the route
entry.

The `ResponseContractRegistry`, discovered by `SharedServiceProvider`, loads
these files after providers are registered. It rejects duplicate route keys and
loads `bootstrap/cache/response-contracts.php` only in production. In local,
testing, staging, and other environments, the cache is ignored so definitions
are always rediscovered.

Cache lifecycle commands:

```bash
php artisan response-contracts:clear
php artisan response-contracts:cache
```

Defaults are determined by the HTTP verb: `GET` returns a resource; `POST`,
`PUT`, and `PATCH` return no content by default and may request
`?response=resource`; `DELETE` always remains `204 No Content`. An explicit
`default_mode` is reserved for documented exceptions and cannot change the
`DELETE` rule.

The architecture test verifies that every API route has a contract and that
every declared key matches an existing route. Shapes currently describe
required relation loads; includes describe only relations explicitly requested
by the client. When field selection is introduced, the fields selected by a
shape will be the source of truth for `required_loads`: a relation is required
only when a displayed field or computed field needs it. For example, a
ProductVariant representation that does not display `name` must not load the
`product` or `optionValues` relations solely because another representation
displays that field. Field selection is not currently supported and must not be
declared in a shape. Module contract files may declare reusable shapes under the
reserved `_shapes` key and reference them from a route shape with `ref`.
References are resolved before the production response-contract cache is
written and are scoped to the configuration file that declares them.

Every include name used by `includeWhenRequestedAndLoaded()` must also be an
include key in at least one response-contract shape. An alias is allowed only
when the same Resource is rendered at that nested path. Do not add an alias
just because the corresponding Eloquent relation exists: the contract must
explicitly allow the client include and define its load first. The architecture
test `ensures resource include aliases are declared by response contracts`
enforces this rule.

## 1. JSON response structure

All API responses follow a consistent structure to simplify client
integration.

### Base structure

Every response contains at least a `message` field:

```json
{
  "message": "Description of the request status"
}
```

### Success with data

Responses returning resources wrap them in a `data` field:

```json
{
  "message": "OK",
  "data": {
    "id": "...",
    "name": "..."
  }
}
```

### Lists and pagination

Paginated index routes add a `meta` field with pagination information:

```json
{
  "message": "OK",
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 75
  }
}
```

### Errors

For validation or business errors, an `errors` field contains details:

```json
{
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

## 2. Business errors with assertions

The [business exception rules](../architecture/coding-rules/exceptions.md)
describe how exceptions are organized. Rules for one model are grouped in a
model exception and exposed through named static methods.

Basic validation, such as types and required fields, belongs to Form Requests.
Validated values are transported to services through Data classes. More
complex business logic uses dedicated assertion objects.

### The problem

Business logic, such as preventing a user from applying to a job when an
active application already exists, can quickly overload services or
controllers.

### The solution: assertions and `AssertionException`

Assertion objects encapsulate one business rule. When the condition is not
met, the assertion object throws an `AssertionException`.

`Lahatre\Shared\Exceptions\AssertionException` is the abstract base class for
all business assertion exceptions.

**Example of a specific `AssertionException`:**

```php
namespace Lahatre\Catalog\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

class ProductOutOfStockException extends AssertionException
{
    public function __construct(string $productId)
    {
        parent::__construct(
            'Product is out of stock.',
            ['product_id' => $productId]
        );
    }
}
```

### Centralized error handling

In `bootstrap/app.php`, all exceptions extending `AssertionException` are
intercepted and formatted consistently:

```php
$exceptions->render(function (AssertionException $e, $request) {
    if ($request->expectsJson()) {
        return response()->json([
            'message' => $e->getMessage(),
            'errors'  => [
                'type'    => class_basename($e),
                'context' => app()->isProduction() ? null : $e->context(),
            ],
        ], 422);
    }
});
```

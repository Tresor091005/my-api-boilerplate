# API: standardized responses and business error handling

This document describes the standardized JSON response structure and the
business-error strategy based on assertion objects.

## Response bodies

Routes rendering a model answer through [response selection](response-selection.md):
`GET` returns the resource; `POST`, `PUT` and `PATCH` return `204 No Content`
unless the client sends `?response=resource`; `DELETE` always returns `204`.
The client chooses the fields and nested resources with `fields` and `include`.

Routes that render no model build their JSON in the controller and always
answer a body: the IAM sign-in payload and its messages, and the inventory lots
and stock summary. File downloads stream the file itself.

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

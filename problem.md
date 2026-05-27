## Problem: Nested DTO Validation Loses Parent Error Paths

### Context

The base DTO class [`app-modules/shared/src/DTO/LahatreDTO.php`](app-modules/shared/src/DTO/LahatreDTO.php) validates data inside its constructor:

1. `defaults()`
2. `sanitize()`
3. `beforeValidation()`
4. `rules()`
5. `withValidator()`
6. `validate()`
7. `hydrate()`

During `hydrate()`, values are cast according to `casts()`.

For nested DTO casts, the relevant path is:

- [`HasCasting`](app-modules/shared/src/DTO/Concerns/HasCasting.php)
- [`CollectionCast`](app-modules/shared/src/DTO/Casts/CollectionCast.php)
- [`DTOCast`](app-modules/shared/src/DTO/Casts/DTOCast.php)

`DTOCast` creates nested DTOs with:

```php
return new $this->dtoClass(is_array($value) ? $value : []);
```

This means every nested DTO starts a brand new validation cycle with its own independent Laravel validator.

### Actual Problem

When a parent DTO uses a nested DTO cast such as:

```php
              'units' => 'collection:'.UnitData::class,
```

or:

```php
'variants' => 'collection:'.ProductVariantData::class,
```

the nested items are not validated in the context of the parent field path.

Instead, each child DTO validates itself as a root object.

As a result:

- errors do not naturally come back as `units.0.name`
- errors do not naturally come back as `variants.1.sku`
- nested validation stops at the first thrown `ValidationException`
- multiple validators are instantiated: one for the parent, then one per nested item

### Why This Is a Problem for the API

The global exception handler returns validation errors exactly as provided by Laravel:

- [`bootstrap/app.php`](bootstrap/app.php)

It does not re-prefix nested errors with the parent path.

So if a nested unit payload fails on `name`, the API response tends to expose:

```json
{
  "message": "Validation failed",
  "errors": {
    "name": [
      "The name field is required."
    ]
  }
}
```

instead of:

```json
{
  "message": "Validation failed",
  "errors": {
    "units.0.name": [
      "The units.0.name field is required."
    ]
  }
}
```

This makes the response less useful for clients and hides which item in the request actually failed.

### Affected Production DTOs

The issue currently affects DTOs that cast to other DTOs.

#### 1. Master

- [`app-modules/master/src/DTO/UnitSyncDTO.php`](app-modules/master/src/DTO/UnitSyncDTO.php)
  - previously used `units => collection:UnitDataDTO::class`
- [`app-modules/master/src/Data/UnitData.php`](app-modules/master/src/Data/UnitData.php)
  - now represents typed unit data after parent validation

#### 2. Catalog

- [`app-modules/catalog/src/DTO/ProductDTO.php`](app-modules/catalog/src/DTO/ProductDTO.php)
  - previously used `variants => collection:ProductVariantDataDTO::class`
- [`app-modules/catalog/src/DTO/ProductVariantDTO.php`](app-modules/catalog/src/DTO/ProductVariantDTO.php)
  - previously used `variants => collection:ProductVariantDataDTO::class`
- [`app-modules/catalog/src/Data/ProductVariantData.php`](app-modules/catalog/src/Data/ProductVariantData.php)
  - now represents typed variant data after parent validation

### Affected Test Coverage

The same nested behavior is also present in:

- [`app-modules/shared/tests/Unit/DTO/LahatreDTOTest.php`](app-modules/shared/tests/Unit/DTO/LahatreDTOTest.php)
  - `nested => NestedDTO::class`
  - `items => 'array:'.NestedDTO::class`

### What Is Not the Problem

Using nested DTO casts for hydration is still useful.

The main benefit remains:

- typed objects after validation
- property access with `->`
- better service-layer ergonomics

So the problem is not the existence of nested DTOs themselves.

The problem is using child DTO construction as the primary validation mechanism for nested HTTP payloads.

### Recommended Direction

For request validation, the parent DTO should validate nested payloads directly with Laravel path-aware rules such as:

- `units.*.id`
- `units.*.name`
- `units.*.symbol`
- `units.*.ratio`
- `variants.*.sku`
- `variants.*.unit_group_id`
- `variants.*.options`
- `variants.*.options.*.name`
- `variants.*.options.*.value`

Then, after parent validation succeeds, nested DTO casts can still be used for hydration into typed objects.

This keeps the advantages of:

- `Collection<UnitData>`
- `Collection<ProductVariantData>`
- object property access in services

without losing:

- indexed validation paths
- complete API-friendly error keys
- predictable nested request validation behavior

### Design Conclusion

In this codebase, nested DTOs should primarily be treated as typed hydration objects.

For nested HTTP payload validation, the parent DTO is the natural validation boundary because it knows the full field path and can produce Laravel-native indexed keys.

### Architectural Direction

The most predictable Laravel-aligned direction is:

- `FormRequest` for HTTP validation
- `Data` classes for typed transport
- dedicated validators for reusable non-HTTP validation

This means a controller flow would become:

1. request validation through a custom `FormRequest`
2. `$request->validated()`
3. creation of a typed `Data` object from validated input
4. service consumption of the `Data` object

This is more verbose than `LahatreDTO`, but it is:

- more explicit
- easier to debug
- easier to understand for Laravel developers
- better aligned with indexed nested validation such as `units.*.name`

For simple reusable filters such as `UnitFilter`, the preferred shape is also:

- `FormRequest + Data` for HTTP
- `Validator + Data` when the same validation must be reused from CLI, jobs, imports, or internal services

This follows the pattern already present in `inventory`, where validation and typed transport are already separated.

### What To Keep vs Replace

`LahatreDTO` still has some value as a unified input abstraction, but its current shape mixes too many concerns:

- validation
- sanitization
- defaults
- hydration
- transformation
- source resolution

The current recommendation is not to extend it further for new nested input modeling.

Instead:

- prefer `FormRequest + Data` for controller-driven HTTP endpoints
- prefer `Validator + Data` when validation must be reused outside HTTP
- keep plain `Data` classes responsible only for typed transport and object graph construction

### Refactoring Scope

Completely removing `LahatreDTO` from the project would be a large refactor.

It is currently used across multiple modules for:

- HTTP controller inputs
- filters
- update payloads
- command-oriented construction helpers
- internal service tests

So full removal should be treated as a staged architectural migration, not a quick cleanup.

A realistic path would be:

1. stop introducing new `LahatreDTO`-based nested payloads
2. migrate the most problematic HTTP payloads to `FormRequest + Data`
3. extract reusable validation into dedicated validators where needed
4. progressively migrate flat/simple DTOs only if the team decides the abstraction is no longer worth keeping

In short:

- partial containment is feasible now
- full deletion is a significant refactor
- the migration should be incremental and convention-driven

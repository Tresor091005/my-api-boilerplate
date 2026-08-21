# Form Requests and Data classes

HTTP input and service contracts are separate responsibilities.

- A Form Request validates and normalizes the HTTP payload.
- A Data class transports already validated values to the service.
- The Controller keeps authorization through Gates and Policies.
- The Service does not depend on HTTP, the Validator, or a Request.

## Standard flow

```php
public function update(CategoryRequest $request, Category $category): JsonResponse
{
    Gate::authorize('update', $category);

    $data = CategoryData::fromArray(
        $request->validated(),
        missingFields: ['name', 'parent_id', 'is_active'],
    );

    return response()->json($this->categoryService->update($category, $data));
}
```

An injected Request is validated before the Controller executes. The Gate
remains deliberately in the Controller; authorization must not be hidden in
validation.

## Naming

Form Requests always keep the `Request` suffix:

- `CategoryRequest` when store and update genuinely share one contract.
- `ProductVariantCreateRequest` and `ProductVariantUpdateRequest` when their
  shapes differ. The resource name comes first; do not use
  `StoreEntityRequest` or `UpdateEntityRequest`.
- `CategoryFilterRequest`, `LoginRequest`, and `UnitUpsertRequest` according to
  their intent.

Service transport objects use the `Data` suffix:

- `CategoryData`.
- `CategoryFilterData`.
- `LoginData`.
- `UnitUpsertData`.

Do not automatically create a store/update pair. One class represents one
coherent shape; split it when conditional branches make the contract difficult
to read, using the `EntityCreateRequest` / `EntityUpdateRequest` naming pair.

## Form Requests

A Request contains:

- `rules()` for Laravel rules.
- `prepareForValidation()` only for cleanup specific to the affected fields.
- `after()` for complex, indexed HTTP validation.
- Rules tied to the route model, such as `unique()->ignore(...)`.

Presence rules are chosen explicitly:

- Omitted key allowed: no presence rule.
- Required key, nullable value: `present` and `nullable`.
- Required key and value: `required`.
- `nullable` alone does not reveal whether a key was absent.

## Data classes

A Data class is normally `final readonly`, uses a private constructor, and
always exposes `::fromArray()`:

```php
final readonly class CategoryFilterData
{
    private function __construct(
        public int $perPage,
        public string $sortBy,
        public ?bool $isActive,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 15),
            sortBy: $data['sort_by'] ?? 'name',
            isActive: array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : null,
        );
    }
}
```

Payloads and columns remain `snake_case`; PHP properties use `camelCase`.
Mapping stays explicit in `::fromArray()`.

`::fromArray()` may build enums, dates, collections, or nested Data classes
from already validated values. It must not run HTTP validation again.

## Missing values and nullability

`MissingValue` distinguishes an absent key from an explicit value. In Data
type unions, put `MissingValue` first so this possibility is immediately
visible: `MissingValue|string|null`, `MissingValue|array|null`, or
`MissingValue|bool`.

When a Data class reads several fields, create one `MissingValueReader` to
avoid repeating the source array and missing-field list:

```php
$read = MissingValueReader::fromArray($data, $missingFields);

$isActive = $read->get('is_active', default: false);

return new self(
    name: $read->get('name'),
    isActive: $isActive instanceof MissingValue ? $isActive : (bool) $isActive,
);
```

The reader follows the same rules as `MissingValue::fromArray()`: a present key
keeps its value, including `null`; an allowed absent key returns the sentinel;
and `default` is used for other absent keys.

```php
$data = CategoryData::fromArray(
    $request->validated(),
    missingFields: ['parent_id'],
);
```

`missingFields` names come from the source and therefore remain `snake_case`.
The list applies only to the array passed to the Data class; it is not applied
automatically to nested objects or arrays. A child Data class needs its own
list when it supports partial updates. Global paths such as
`variants.*.name` are not interpreted by `MissingValueReader`.

For updates:

```php
use function Lahatre\Shared\Data\withoutMissing;

$category->fill(withoutMissing([
    'name' => $data->name,
    'parent_id' => $data->parentId,
]));
```

The helper removes only `MissingValue::Instance`. It preserves `null`, `false`,
`0`, `''`, and `[]`.

For a field required during creation:

```php
use function Lahatre\Shared\Data\required;

'name' => required($data->name),
```

## Generation

Create files in the relevant module:

```bash
php artisan make:request CategoryRequest --module=catalog --no-interaction
php artisan make:class Data/CategoryData --module=catalog --no-interaction
```

The `make:dto` command and `LahatreDTO` class have been removed.

## Tests

Test separately:

1. Form Request rules, normalization, and indexed errors.
2. Data mapping from `snake_case` to `camelCase`.
3. The difference between absence, `null`, `false`, zero, and empty arrays.
4. Service logic and persistence.

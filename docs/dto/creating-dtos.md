# Creating Data Transfer Objects (DTOs)

This document outlines the standardized approach for creating and using Data Transfer Objects (DTOs) within the application. DTOs are the primary mechanism for structuring and validating data from incoming HTTP requests, acting as a more powerful and flexible alternative to Laravel's Form Requests.

Our implementation is built upon the `wendelladriel/validated-dto` package, extended with custom functionalities to better suit our architecture.

## 1. The `LahatreDTO` Base Class

All DTOs in the application **must** extend `Lahatre\Shared\DTO\LahatreDTO`. This base class provides two crucial features out-of-the-box through traits:

### `HasBeforeValidation` (Pre-Validation Hook)

This trait provides a `beforeValidation(array $data): array` method, which acts similarly to the `prepareForValidation` method in Form Requests. It allows you to modify or sanitize data *before* it gets passed to the validator.

**Example: Slugifying a title before validation**
```php
// in your ProductDTO.php
protected function beforeValidation(array $data): array
{
    if (isset($data['title'])) {
        $data['slug'] = Str::slug($data['title']);
    }
    return $data;
}
```

### `UpdatesFromModel` (Update Context Awareness)

This trait provides a factory method `forUpdate(Request $request, Model $model)` and helpers to handle DTOs in an update scenario. This is critical for validation rules that need to ignore the current model's ID, such as unique constraints.

- **`forUpdate(Request $request, Model $model)`:** Creates a DTO instance by merging the model's existing data with the new data from the request.
- **`$this->modelId`:** When using `forUpdate`, this property is automatically populated with the model's ID, making it available within your `rules()` method.

**Example: Using `$this->modelId` for a unique rule**
```php
// in your UserDTO.php
protected function rules(): array
{
    return [
        'email' => [
            'required',
            'email',
            Rule::unique('users', 'email')->ignore($this->modelId),
        ],
    ];
}
```

## 2. Creating a New DTO

The easiest way to create a new DTO is by using the provided Artisan command:
```bash
php artisan make:dto YourDTOName
```
To create a DTO inside a specific module:
```bash
php artisan make:dto YourDTOName --module=your-module
```
This will generate a DTO file that extends `LahatreDTO` with all the necessary methods stubbed out.

### Structure of a DTO
A DTO class consists of several key parts:

- **Public Properties:** Define the data fields your DTO will hold.
- **`rules(): array`:** Defines the Laravel validation rules.
- **`defaults(): array`:** Provides default values for properties not present in the input.
- **`casts(): array`:** Automatically casts properties to specific PHP types or other objects.
- **`after(Validator $validator): void`:** A hook for logic to be executed after successful validation.

## 3. Property Casting

The `casts()` method is powerful for ensuring data types are correct. Our application comes with a set of pre-defined `Castable` classes.

| Cast Type | Example Usage | Description |
|---|---|---|
| **Primitives** | `new IntegerCast()`, `new FloatCast()`, `new StringCast()`, `new BooleanCast()` | For basic types. |
| **Dates** | `new CarbonCast(format: 'd/m/Y')`, `new CarbonImmutableCast()` | For converting strings to `Carbon` objects. |
| **Enums** | `new EnumCast(YourEnum::class)` | For converting strings or integers to a PHP Enum. |
| **Collections**| `new CollectionCast()` | For converting an array to an `Illuminate\Support\Collection`. |
| **Nested DTO**| `new DTOCast(CategoryDTO::class)` | For casting an array into another DTO. |
| **Array of DTOs**| `new ArrayCast(new DTOCast(TagDTO::class))` | For casting an array of arrays into an array of DTOs. |

**Example `casts()` implementation:**
```php
// in your PlanDTO.php
protected function casts(): array
{
    return [
        'pricing_model' => new EnumCast(PricingModel::class),
        'price'         => new FloatCast(),
        'features'      => new ArrayCast(new DTOCast(PlanFeatureDTO::class)),
    ];
}
```

## 4. Usage in a Controller

Here is how you would use a DTO for both creation and update operations.

```php
// in YourController.php

public function store(Request $request)
{
    // For creation
    $dto = ProductDTO::fromRequest($request);

    // ... use $dto->property to access validated and casted data
}

public function update(Request $request, Product $product)
{
    // For update, using the forUpdate factory method
    $dto = ProductDTO::forUpdate($request, $product);

    // ... $dto now contains merged data and $dto->modelId is set
}
```

By following these conventions, we ensure that our application's data layer is robust, predictable, and easy to maintain.

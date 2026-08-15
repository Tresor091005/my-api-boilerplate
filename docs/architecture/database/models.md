# Eloquent model conventions

This document defines the conventions for creating and managing Eloquent
models in the application to ensure consistency, maintainability, and Laravel
best-practice compliance.

## General principles

- **Location and namespace:** `catalog` models belong in
  `app-modules/catalog/src/Models/` and use the `Lahatre\Catalog\Models`
  namespace.
- **Base class:** Every model must extend
  `Illuminate\Database\Eloquent\Model`.
- **Common traits:** Use `Lahatre\Shared\Traits\SharedTraits`. This trait
  includes `HasUuids` for UUID primary keys and `HasFactory` for model
  factories.

## Model properties

- **`$table`:** Always define `$table` explicitly to identify the associated
  database table, for example `protected $table = 'catalog_currencies';`.
  Table names must be plural.
- **`$primaryKey` and `$incrementing`:** By default, tables use an `id` UUID,
  so these properties do not need to be defined. If a table uses another
  primary key, such as `code` for `catalog_currencies`, or does not use an
  auto-incrementing UUID, define `protected $primaryKey = 'your_primary_key';`
  and `public $incrementing = false;` explicitly.
- **`$fillable`:** Explicitly define every mass-assignable column.
- **`$casts`:** Define all model columns in `$casts`:
  - Use `immutable_datetime` for date and time columns such as `created_at`,
    `updated_at`, `active_from`, and `active_to`.
  - Cast UUID columns, including primary and foreign keys, to `string`.
  - Cast numeric columns to appropriate PHP types (`integer`, `float`, or
    `string` for large decimal values).
  - Cast boolean columns to `boolean`.
  - Cast other text columns to `string` when explicit typing is useful.

## Eloquent relationships

- **Explicitness:** Define relationships explicitly, including foreign keys,
  local keys, and pivot table names when applicable. For example:
  - `belongsTo`: `return $this->belongsTo(Category::class, 'parent_id', 'id');`
  - `belongsToMany`: `return $this->belongsToMany(Tag::class, 'catalog_product_tags', 'product_id', 'tag_id')->using(ProductTag::class)->withTimestamps();`
- **Pivot models:** For intermediate tables containing additional columns,
  such as timestamps, create a dedicated model extending
  `Illuminate\Database\Eloquent\Relations\Pivot`.
  - Pivot models must also use `SharedTraits` and define `$table` and `$casts`
    for all columns.
  - `belongsToMany` relationships must use
    `->using(YourPivotModel::class)` and `->withTimestamps()` when the pivot
    table has timestamps.

## Code style

- **No explanatory comments:** Avoid comments that merely describe what the
  code does. Code should be self-descriptive. Reserve comments for complex
  explanations or non-obvious technical decisions.
- **Naming:** Follow PSR-12 and Laravel naming conventions, such as singular
  model names and plural table names.

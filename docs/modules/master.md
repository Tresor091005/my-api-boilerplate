# Master module

The Master module owns shared reference data used by business modules.

## Currencies

Currencies are identified by code and precision. The `MasterInterface` exposes
lookup plus conversion to and from minor units. Currency data is globally
cached because currencies are not currently tenant-owned.

## Units

Unit groups define a base unit (`ratio = 1`). Other units store a ratio relative
to that base. Conversion uses BCMath and returns decimal strings to avoid
floating-point money/quantity drift. Units can be system-wide (`organization_id
is null`) or organization-specific.

`UnitService::upsert` updates a unit group and its units transactionally, then
rewarm the relevant cache after commit. System groups and another
organization's groups are protected by request validation and service checks.

## Labels

`InteractsWithLabels` provides grouped polymorphic label attachment, detachment, synchronization,
and scopes for any/all/none of a label set. A model that uses labels must use
`Lahatre\Master\Traits\InteractsWithLabels` and expose a persisted `organization_id`:

```php
use Illuminate\Database\Eloquent\Model;
use Lahatre\Master\Traits\InteractsWithLabels;

class ProductVariant extends Model
{
    use InteractsWithLabels;
}
```

The persisted organization must be non-empty and match the active organization;
system or organization-less models cannot be labeled. Relation loading requires
an active organization context, including eager loading; write operations also
validate the persisted model through the label service. Label values and groups are
normalized, operations are transactional, and detaching a label does not delete
the label itself. The label write methods return `void`; reload the `labels` relation
explicitly when the updated collection is needed.

Labels expose organization-scoped list, batch creation by group, value update,
reorder, and safe-delete routes. `POST /v1/master/labels` accepts a payload shaped
like `{"labels":{"status":["active","inactive"],"color":["red"]}}`.
Groups are 2-50 character identifiers and label values are limited to 50
characters. `GET /v1/master/labelables/{labelable_type}/{labelable_id}/labels` reads the
labels attached to a model that uses `InteractsWithLabels`; it resolves the morph type from
the registered morph map and requires the labelable model's `retrieve`
permission. A label cannot be deleted while pivot links still reference it.

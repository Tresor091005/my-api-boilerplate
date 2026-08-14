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

## Tags

`InteractsWithTags` provides typed polymorphic tag attachment, detachment, synchronization,
and scopes for any/all/none of a tag set. A model that uses tags must implement
`Lahatre\Master\Contracts\HasTags`, use
`Lahatre\Master\Traits\InteractsWithTags`, and return its persisted tenant from
`getOrganizationId(): string`:

```php
use Illuminate\Database\Eloquent\Model;
use Lahatre\Master\Contracts\HasTags;
use Lahatre\Master\Traits\InteractsWithTags;

class ProductVariant extends Model implements HasTags
{
    use InteractsWithTags;

    public function getOrganizationId(): string
    {
        return $this->organization_id;
    }
}
```

The returned organization must be non-empty and match the active organization;
system or organization-less models cannot be tagged. Relation loading requires
an active organization context, including eager loading; write operations also
validate the persisted model through the tag service. Tag names and types are
normalized, operations are transactional, and detaching a tag does not delete
the tag itself. The tag write methods return `void`; reload the `tags` relation
explicitly when the updated collection is needed.

The tag repository currently has no standalone public CRUD routes.

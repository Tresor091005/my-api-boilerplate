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

`UnitService::sync` updates a unit group and its units transactionally, then
rewarm the relevant cache after commit. System groups and another
organization's groups are protected by request validation and service checks.

## Tags

`HasTags` provides typed polymorphic tag attachment, detachment, synchronization,
and scopes for any/all/none of a tag set. Tag names and types are normalized;
operations are transactional and scoped by the taggable model's organization
when applicable. Detaching a tag does not delete the tag itself.

The tag repository currently has no standalone public CRUD routes.

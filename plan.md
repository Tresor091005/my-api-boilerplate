# Service domain implementation plan

## Objective

Add the service catalog item and the service-execution domain without adding a
Sale or Fulfillment module. Sale will integrate later through a narrow public
contract.

## Catalog

- Add `CatalogItemType::Service` with value `catalog_service`.
- Services reuse the existing CatalogItem metadata (`sku`, `unit_group_id`,
  and `is_active`) and are always non-stockable.
- Add `catalog_services` using the CatalogItem shared identity strategy.
- Add `catalog_service_deliverable_templates` with `service_id`, `name`, and
  deterministic `position`.
- Never create or update an InventoryItem for a Service.
- Expose only the catalog lookup needed by the service module to snapshot
  deliverable templates.

## Service module

Create `app-modules/service` with Composer metadata, a ServiceProvider, models,
migrations, factories, assertions, exceptions, translations, and services.

### Models

- `ServiceCommitment`: `organization_id`, `service_id`, opaque future
  `sale_line_id`, status, and lifecycle timestamps.
- `ServiceDeliverable`: commitment snapshot row with name, position, status,
  completion and cancellation timestamps.
- `Evidence`: immutable submitted snapshot with lifecycle timestamps,
  verifier identifier, and public token.

No table or model references `sale` or `fulfillment` directly. The
`organization_id` boundary and one-commitment-per-sale-line uniqueness are
enforced in the service migrations.

### Lifecycle

- Creating a commitment snapshots the current catalog templates and starts in
  `draft`.
- Deliverables are editable only while the commitment is `draft`.
- Confirming changes `draft` to `active`.
- Completing all non-cancelled deliverables changes the commitment to
  `completed`.
- Closing a `draft` or `active` commitment changes it to `closed` and cancels
  remaining pending deliverables.
- Completed and closed commitments are immutable.

### Evidence

- Keep `draft`, `sent`, `accepted`, `rejected`, and `expired` states.
- Sending an Evidence freezes its snapshot.
- Accepting Evidence completes its pending deliverable.
- Accepted, rejected, or expired Evidence cannot transition again.
- Acceptance is idempotent.
- Evidence produces no Fulfillment and has no OTP, HTTP, notification, or
  external-customer integration in this phase.

## Public inter-module contract

Add `Lahatre\Service\Contracts\ServiceInterface`, bound by the module
provider, with only the commitment operations that a future Sale integration
needs:

```php
public function createCommitment(string $serviceId, string $saleLineId): ServiceCommitment;

public function confirmCommitment(ServiceCommitment $commitment): ServiceCommitment;

public function closeCommitment(ServiceCommitment $commitment): ServiceCommitment;
```

The contract accepts no Sale model and does not trigger Sale checks, listeners,
events, or invoice behavior. Deliverable and Evidence operations remain
internal service APIs until a later consumer requires a public contract.

## Verification

Add Pest tests for Catalog type behavior, non-stockable creation, shared
identity, template snapshotting, tenant boundaries, duplicate sale-line
protection, commitment transitions, deliverable immutability, Evidence
immutability/idempotence, and the interface binding. Run the affected tests,
Pint, and `composer quality:check` where the existing autoload issue permits.

## Explicit exclusions

No Sale module, Sale confirmation hook, Fulfillment model/table/integration,
`fulfillment_quantity`, HTTP routes/controllers/resources/requests/policies,
OTP, notifications, billing, returns, scheduling, projects, tasks, or portal.

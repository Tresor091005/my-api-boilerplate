# Shared module

The Shared module contains infrastructure used by multiple modules and owns no
business aggregate.

It provides:

- `AssertionException` and shared exception contracts;
- UUID/factory traits and the shared authenticatable model;
- deterministic cursor pagination;
- handle and SKU generation;
- model discovery and morph-map caching;
- response-contract discovery and deployment caching;
- PostgreSQL-backed, organization-scoped business numbering;
- module-aware/native Artisan generator overrides;
- generator architecture support and shared test helpers;
- an enum-based state machine for domain-owned workflows.

Shared code must remain independent of business modules. If a business rule is
added here, it should first be expressed as a public contract or moved to the
owning module.

## State machine

The shared state machine defines which events can move a domain object from one
state to another. The domain module owns its state and event enums, the graph of
allowed transitions, authorization, persistence, and transition audit data.
The shared implementation changes only an in-memory actor; it does not save a
model or retain a history.

Define transitions while constructing the machine, then call `freeze()` before
sharing it. Creating the first actor also freezes the machine automatically.
After freezing, neither the machine nor a `State` obtained from `getState()` can
register another transition. A `State` does not track an actor's current state;
it resolves a transition from the fixed graph, and guard results can vary with
the context supplied. Each actor retains its own state and context.
During definition, `from()` selects the source state for subsequent
`addTransition()` calls; it does not change an actor's state.

### Define a workflow

The following example shows an order workflow. Both states and events are
enums; every case of the state enum is registered automatically. These example
types belong in the order module, not in `shared`:

```php
enum OrderStatus: string
{
    case Pending = 'pending';
    case Updating = 'updating';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Deleted = 'deleted';
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';
    case Unapproved = 'unapproved';
}

enum OrderEvent: string
{
    case PreUpdate = 'pre_update';
    case SubmitForApproval = 'submit_for_approval';
    case Reject = 'reject';
    case Confirm = 'confirm';
    case RevertToPending = 'revert_to_pending';
    case Delete = 'delete';
    case Cancel = 'cancel';
    case Approve = 'approve';
    case RequestChanges = 'request_changes';
}
```

The order module defines the graph in its provider and freezes the machine
before registering it as a singleton. The binding name keeps this workflow
distinct from other state machines:

```php
use Lahatre\Shared\StateMachine\StateMachine;

$this->app->singleton('order.state.machine', static fn (): StateMachine =>
    StateMachine::define(OrderStatus::Pending, OrderEvent::class)
        ->from(OrderStatus::Pending)
            ->addTransition(OrderEvent::PreUpdate, OrderStatus::Updating)
            ->addTransition(OrderEvent::Reject, OrderStatus::Rejected)
            ->addTransition(OrderEvent::Confirm, OrderStatus::Completed)
        ->from(OrderStatus::Updating)
            ->addTransition(OrderEvent::RevertToPending, OrderStatus::Pending)
            ->addTransition(OrderEvent::SubmitForApproval, OrderStatus::AwaitingApproval)
            ->addTransition(OrderEvent::Delete, OrderStatus::Deleted)
        ->from(OrderStatus::Rejected)
            ->addTransition(OrderEvent::RevertToPending, OrderStatus::Pending)
            ->addTransition(OrderEvent::Delete, OrderStatus::Deleted)
        ->from(OrderStatus::Completed)
            ->addTransition(OrderEvent::Cancel, OrderStatus::Cancelled)
        ->from(OrderStatus::AwaitingApproval)
            ->addTransition(OrderEvent::Approve, OrderStatus::Approved)
            ->addTransition(OrderEvent::RequestChanges, OrderStatus::Unapproved)
        ->from(OrderStatus::Unapproved)
            ->addTransition(OrderEvent::PreUpdate, OrderStatus::Updating)
        ->from(OrderStatus::Approved)
            ->addTransition(OrderEvent::Reject, OrderStatus::Rejected)
            ->addTransition(OrderEvent::Confirm, OrderStatus::Completed)
        ->freeze()
);
```

`Cancelled` and `Deleted` have no outgoing transitions, so they need no
`from()` call. The approval path is reachable through `SubmitForApproval`, and
`RequestChanges` returns an order to the update path through `Unapproved`.

`freeze()` validates the graph and prevents subsequent calls to `from()`,
transition registration, or `State::on()`, including on a `State` reference
obtained before freezing. A rejected change throws `StateMachineException`.
Guards must read only their provided context and must not capture mutable
request or tenant state. The definition can then be shared across requests and
jobs, while each `actor()` call creates an independent, mutable actor.

### Apply a transition

Create an actor at the model's current enum state (cast `status` to
`OrderStatus` on the model). A domain service should reload and lock a
persisted object within its transaction before checking a race-sensitive
transition:

```php
DB::transaction(function () use ($orderId): void {
    $order = Order::query()->lockForUpdate()->findOrFail($orderId);

    /** @var StateMachine $machine */
    $machine = app('order.state.machine');
    $actor = $machine->actor(state: $order->status);

    $result = $actor->trigger(OrderEvent::Confirm);
    $order->status = $result->to;
    $order->save();

    // Persist any transition audit record in this same transaction.
});
```

`trigger()` returns a `TransitionResult` with `event`, `from`, and `to`, and
updates the actor in memory. `can()` checks the same graph without changing
state. An unavailable transition throws a translated `StateMachineException`
with the attempted event, current state, and allowed events. A service can
instead pass `onFailure: fn (TransitionFailure $failure): Throwable => ...` to
`trigger()` to throw a domain-specific exception. Authorize the operation
and enforce domain invariants in the owning module.

Transitions may also have side-effect-free guards. Multiple transitions can
use the same event; the first guard that accepts the actor's context determines
the target. `availableTransitions()` returns only that effective transition for
each event, even when later guards for the same event also accept the context.
`can()` and `availableTransitions()` can preview a temporary
`extraContext` without changing the actor. Call `setContext()` to use those
values for a real transition:

```php
$machine = StateMachine::define(OrderStatus::Pending, OrderEvent::class)
    ->from(OrderStatus::Pending)
    ->addTransition(
        OrderEvent::Confirm,
        OrderStatus::Completed,
        static fn (array $context): bool => $context['is_ready'] === true,
    )
    ->addTransition(
        OrderEvent::Confirm,
        OrderStatus::Rejected,
        static fn (array $context): bool => $context['is_ready'] === false,
    )
    ->freeze();

$actor = $machine->actor(OrderStatus::Pending, context: ['is_ready' => false]);
$actor->availableTransitions(extraContext: ['is_ready' => true])[0]->target;
// OrderStatus::Completed; the actor still has ['is_ready' => false].
$actor->setContext(['is_ready' => true]);
$actor->trigger(OrderEvent::Confirm); // to OrderStatus::Completed
```

`trigger()` reads the actor's stored context. Guards are evaluated for each
check, so they should not perform persistence or other side effects. The domain
service owns the transaction and any durable state change.

## Generated identifiers

`HandleGenerator` creates a slug and chooses the next numeric suffix among
matching rows. It is a naming helper, not a concurrency guarantee: the owning
table must still enforce the relevant uniqueness constraint and race-safe write
path. `SkuGenerator` creates a readable date/random SKU and is likewise
unique-ish rather than a database identity mechanism.

## Business numbering

`BusinessNumberService::next($key)` generates a unique, human-readable sequence
for the active organization. Definitions live in
`shared/config/business-numbering.php`. The format supports only the system
tokens `{YEAR}`, `{YEAR2}`, `{MONTH}`, `{DAY}`, and `{SEQ}`. There are no
runtime placeholders or hidden scopes. The organization is always resolved by
`currentOrganizationId()` and is never accepted as an argument.

The service writes `shared_business_number_counters`, which makes the Shared
module's ownership explicit. It increments the counter with one PostgreSQL
`INSERT ... ON CONFLICT DO UPDATE ... RETURNING` statement. Before incrementing,
it renders the configured format with the current date, using `0` in place of
`{SEQ}`. This rendered value is stored as
`number_identity`, and its SHA-256 hash is indexed with `organization_id`.
Different keys that render the same number identity intentionally share one
counter. A format change creates a different sequence, while restoring a prior
format resumes its previous sequence.

The reset period is encoded in the visible format. `yearly` requires `{YEAR}`
or `{YEAR2}`, `monthly` also requires `{MONTH}`, and `daily` also requires
`{DAY}`. Additional date tokens are allowed and become part of the visible
number identity. This prevents a reset from producing a number that has already
been displayed.

The statement uses the current Laravel connection and therefore participates in
an existing transaction. A number consumed outside the surrounding business
transaction can leave a gap when the business operation later fails; this
primitive guarantees atomicity and uniqueness, not legal gapless numbering.

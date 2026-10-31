# Billing module

The platform defines what is sold; organizations subscribe and pay each
period by hand, the way mobile money bills are paid. There is no automatic
charging, no refund and no credit balance. Routes are listed in
[the endpoint reference](../api/endpoints.md#billing-offer).

## Features

A module declares the features a plan can sell in a `ProvidesFeatures` class
registered from its provider into `FeatureRegistry`. A feature is a key
`module.feature` and a type. Its name is the translation
`<module>::features.<feature>`.

- **Boolean**: the plan has it or not.
- **Capacity**: the plan grants an allowance, and a counter method of the
  module measures the organization's usage. With a window
  (`CarbonInterval`), the counter receives the moment the window started and
  counts only what was used since, so the allowance comes back as time passes.
- **Choice**: the feature lists its options; the plan grants some of them as
  `values`, and the check is made for one value.

Only what sets plans apart is a feature: what every organization has needs no
check, the subscription middleware already guarding access. Disabling a
feature cuts it for every subscriber, whatever the plan grants.
`billing:features:check` guards deployments: every granted key must still be
registered with the type it was granted with, and granted values must still be
options. `php artisan doctor` runs the same check
(`BillingFeatureGrantsMatchCode`) and fails on any difference.

Each type has one example in the code: `catalog.bundle_assembly` (the stock of
a bundle: asking to track it needs the feature, and left unsaid it follows the
plan, so a bundle stays creatable; any stock operation on a bundle, assembling,
taking apart or transferring, is checked when the draft is created and again
when it is completed), `catalog.stock_locations`
(the active locations: checked when a location is created or reactivated, so
a smaller plan keeps only as many active ones), `catalog.stock_transfers` (a
count over 30 days, deleted drafts included) and `library.file_kinds` (the
kinds of file stored, checked at upload).

## Plans

Plans are managed at platform level (`auth.platform`). A plan has at most one
published version on sale and one draft; publishing archives the previous
version. A version carries one price, its currency and
`billing_interval_months`, plus the granted features. Prices are decimals at
the API and minor units in the database. There is one price per version, with
USD as the reference; converting at payment time is integration work, not
another tariff. A plan is `on_sale`, `retired` (sold once, nothing on sale) or
`unused`; only an unused plan with no pending checkout can be deleted.

## Subscriptions

A row of `billing_subscriptions` is one plan version paid without
interruption from `starts_at` to `ends_at`. Rows of an organization never
overlap. The subscription in effect is the latest row already started; its
status is derived from the dates, never stored: `active` before `ends_at`,
`in_grace` for 5 days after, then `suspended`. Access holds during grace.

- **Subscribe**: plans on sale only, when there is no subscription or it is
  suspended. A suspended customer loses the old version and its price.
- **Renew**: pays for what comes next, from the end of what is paid (from the
  old end during grace, since access continued). Renewing the current plan
  keeps its version and price even once it is no longer on sale. Choosing
  another plan makes it start when the paid coverage ends.
- **Change now**: starts a full period of the new plan now and costs its price
  minus the unused value of what is paid. It is refused when that would be
  zero or negative (nothing to pay, so the checkout could never be confirmed),
  across currencies, and outside the active status.

What is paid ahead stays on a single plan: only one next plan, chosen only
while the current plan has nothing paid beyond its current period. Settling a
dispute by hand then values one plan only. Coverage may end at most
`billing.max_advance_months` (12) after today, and no plan interval may
exceed it; a platform selling yearly plans raises it to 24 or 36.

Periods are counted from the row's start, so a start on January 31 renews on
February 28, then March 31.

## Trial and access

A trial is not a subscription: a trialing organization is treated like one
with no subscription. It may subscribe, which starts the plan now and ends the
trial; renewing and changing now are refused. A trial has no grace and is
stored apart, in `billing_trials`, at most one per organization.

The trial plan is a built-in plan (`is_trial`, code `trial`) created by a
migration with one free monthly version and no feature. There is only one; it
is never sold nor listed in the offers, its price stays 0, and it cannot be
deleted. Creating an organization gives it a trial lasting one period of the
trial plan's published version, unless it already had a trial or a
subscription. The platform sets the length and the features through the
usual draft and publication; publishing moves the running trials to the new
version at once, since nobody paid for them. Archiving the trial plan stops
giving trials.

Every request in `auth.api`, reads included, needs a subscription giving
access or a running trial: `EnsureSubscriptionAccess` (alias `billing.subscription`)
answers 403 otherwise, with `reason` `no_subscription` or `suspended`. It
checks nothing else; what a plan allows is decided by each action through
`EntitlementService`. A route a blocked organization must reach excludes it
with `withoutMiddleware('billing.subscription')`, without importing billing. Only
seven do, to show why the organization is blocked and let it pay: the
subscription, the trial, the checkouts (list, start, show), the current
permissions and the current settings. A test pins that list.
`billing.subscription_required` turns the check off for an application that
sells no subscription; the base test case turns it off too, and billing tests
turn it back on.

## Checkouts

A checkout freezes what the customer is about to pay: the action, the plan,
the periods, the total amount and the coverage dates, evaluated when it
starts. It is also the preview shown before paying. Confirming it applies
exactly that, once, even after the grace has ended meanwhile. It expires after
an hour, and starting one cancels the pending one. Confirmed checkouts are the
payment history.

`CheckoutService::confirm()` is the entry point a payment provider webhook
will call. Until then, `billing:checkout:confirm {checkout}`, registered
outside production, plays that role.

## Entitlements

`EntitlementService` answers whether an organization may use a feature now,
from the plan of its subscription giving access, or else of its running trial.
`resolve()` returns the decision with its `reason` when refused: `disabled`,
`no_subscription`, `suspended`, `not_in_plan`, `limit_reached` or
`value_not_in_plan`, checked in that order. Grace still grants access. A
capacity feature is refused once the counted usage reaches the allowance; a
grant without allowance is unlimited. A choice feature is resolved for one
value and also returns the granted `values`. `canUse()` returns the decision
alone. `authorize()` throws `FeatureUnavailableException`, which answers 403
with the reason, the feature key, the allowance and the counted quantity (or
the value and the granted values), in production too, so the client can offer
the right plan. An unregistered key, or a value missing for a choice or given
for another type, is a `FeatureException`.

Call `authorize()` inside the transaction that uses the feature. For a
capacity it takes the transaction lock `billing-entitlement:<organization>:<feature>`
before counting and keeps it until that transaction ends, so two concurrent
requests cannot both take the last unit; outside a transaction it fails.
`billing.entitlements_enforced` (true) can turn every check off: the test suite
and the demo seeder do, billing tests turn it back on.

## Permissions

Plans need the `operate-platform` Gate; organization permissions never grant
it. Organizations use `billing_subscription.retrieve` (subscription, trial
and a checkout), `billing_subscription.list` (checkouts) and
`billing_subscription.pay` (starting checkouts). Checkouts and trials
generate no permission of their own.

## Next steps and open questions

- Payment provider: research pointed to FedaPay for local mobile money and to
  Polar or Paddle for international cards; none is chosen.
- A checkout paid after it was cancelled or expired is not applied; handling
  that money is not decided.
- Reminders before the end of coverage and during grace.
- Periods are computed in UTC; whether the organization timezone should drive
  them is not decided.
- Moving customers off an old version on the platform's initiative.

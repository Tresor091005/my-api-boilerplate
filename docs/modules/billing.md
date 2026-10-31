# Billing module

The platform defines what is sold; organizations subscribe and pay each
period by hand, the way mobile money bills are paid. There is no automatic
charging, no refund and no credit balance. Routes are listed in
[the endpoint reference](../api/endpoints.md#billing-offer).

## Features

A module declares the features a plan can sell in a `ProvidesFeatures` class
registered from its provider into `FeatureRegistry`. A feature is a key
`module.feature` and a type: boolean, or capacity with the method counting an
organization's usage. Its name is the translation `<module>::features.<feature>`.
Disabling a feature cuts it for every subscriber. `billing:features:check`
guards deployments: every granted key must still be registered with the type
it was granted with.

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

## Permissions

Plans need the `operate-platform` Gate; organization permissions never grant
it. Organizations use `billing_subscription.retrieve` (subscription and a
checkout), `billing_subscription.list` (checkouts) and
`billing_subscription.pay` (starting checkouts). Checkouts generate no
permission of their own.

## Next steps and open questions

- Entitlements: review `EntitlementService` and decide how modules check a
  feature when it is used, such as refusing a product beyond the allowance.
- Payment provider: research pointed to FedaPay for local mobile money and to
  Polar or Paddle for international cards; none is chosen.
- A checkout paid after it was cancelled or expired is not applied; handling
  that money is not decided.
- Reminders before the end of coverage and during grace.
- Periods are computed in UTC; whether the organization timezone should drive
  them is not decided.
- Moving customers off an old version on the platform's initiative.

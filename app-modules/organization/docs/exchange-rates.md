# Exchange rates

The Organization module owns the functional currency of each organization and
its exchange-rate history. Master currencies remain global reference data and
provide the precision used by conversions.

Each organization has one settings record with an `enable_currencies` list.
The list is initialized with the functional currency and must always retain it.
Exchange-rate creation and conversion reject currencies outside this list before
checking the global currency master or the rate history.

Exchange rates are directed: a rate describes the target-currency amount for
one source-currency unit. Rates are selected by organization, currency pair,
and effective date, and context. The latest rate whose `effective_at` is not
later than the conversion date is used. Context defaults to `default` and is
matched exactly; it never falls back to another context.

The API is available under `/v1/organization/exchange-rates`:

- `GET` lists cursor-paginated rates and supports pair and effective-date filters;
- `POST` creates a rate;
- `GET /{exchange_rate}` retrieves a rate;
- `PATCH /{exchange_rate}` updates a future rate;
- `DELETE /{exchange_rate}` deletes a future rate.

Rates that are already effective cannot be changed or deleted. A correction
must be represented by a new effective-dated rate. The `ExchangeRateService`
resolves transaction amounts into the organization's functional currency and
returns the amount, rate, effective date, and context as a structured array for
storage in a future document's `exchange_metadata` JSON field. It converts
persisted monetary amounts in minor units using BCMath and never resolves an
organization implicitly.

The settings API is available under `/v1/organization/settings`:

- `GET` retrieves the active organization's currency whitelist;
- `PATCH` replaces the whitelist after validating every code and preserving the
  functional currency.

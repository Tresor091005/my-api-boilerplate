# Validation limits

Form Requests use domain-sized limits rather than a generic `255` maximum.
These limits protect query filters and API payloads while leaving genuinely
long content, such as product descriptions, open-ended.

| Field family | Limit | Reason |
| --- | ---: | --- |
| Names, labels, option values | 100 characters | Human-readable short values |
| Tag names | 50 characters | Compact classification labels |
| Product names | 150 characters | Product titles can be longer than labels |
| Handles, SKUs, unit codes | 100, 100, 50 characters | Identifiers remain practical for URLs and search |
| Tag types | 2–50 characters | Machine-readable identifiers |
| Currency codes | Exactly 3 characters | ISO-style currency code contract |
| Morph/reference types | 100 characters | Registered model aliases |
| UUID filters | UUID format | Persisted identifiers are UUIDs |
| Filter identifier lists | At most 100 values | Prevent oversized `whereIn` queries |
| Product descriptions and metadata | No short-string limit | Descriptive or structured content |
| Pagination cursors | Opaque string | The pagination implementation owns its format |

A wildcard rule such as `tags.*.*` validates nested values, not associative
array keys. Form Requests must use `withValidator()` or `after()` when those
keys have their own contract.

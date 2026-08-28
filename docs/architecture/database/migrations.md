# Migration conventions

This document defines the rules for creating and managing PostgreSQL database
migrations.

## General principles

- **Database engine:** The project uses PostgreSQL exclusively.
- **Grouping:** Group logically related table creations, such as `users`,
  `password_reset_tokens`, and `sessions`, in one migration file for clarity.

## Table structure

- **Table names:** Use plural names, for example `users` and `job_postings`.
- **Primary keys:** Always use UUIDs: `$table->uuid('id')->primary();`.
- **Foreign keys:** Use `$table->foreignUuid('user_id')`. When adding a
  constraint, specify the table explicitly with `->constrained('users')`.
- **Polymorphic relations:** Use `$table->uuidMorphs('commentable');`.
- **Soft deletes:** Use `$table->softDeletes();` on relevant models.
- **Indexing:** Every foreign key (`foreignUuid`) must have an index
  (`->index()`). Frequently filtered columns, such as statuses and
  publication dates, must also be indexed.

## Column types

- **Text:** Use `text()` for short and long text fields to benefit from
  PostgreSQL optimizations. For short, fixed-length identifiers, such as ISO
  4217 currency codes, `string(length)` is acceptable.
- **Dates and times:** Use `timestamp()` for instant values whose columns end
  in `_at`. Use `date()` for civil calendar values whose columns end in
  `_date`; do not add a fake time or timezone to a civil date. See the [date
  and time conventions](../date-time-conventions.md).
- **JSON:** Always use `jsonb()` for query performance.
- **Numbers:** Use `integer`/`unsignedInteger` for standard integers,
  `bigInteger`/`unsignedBigInteger` for very large integers, and `decimal` for
  precision values.
- **Indexes:** Add `->index()` to frequently queried columns. Use `->unique()`
  for uniqueness constraints, including composite constraints.

## Constraints

- **Nullable versus default:** Never use `->nullable()` and `->default()` on
  the same column. A column is either nullable or has a default value, not both.

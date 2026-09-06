---
name: code-reviewer
description: Review specified code changes, files, or modules in this Laravel repository for actionable defects and project-rule violations. Use for code review requests; findings do not authorize implementation.
---

# Skill: Code Reviewer

## Review Process

Review-only requests authorize inspection and relevant diagnostic checks, not
file edits, autofixes, commits, or external review comments. Report corrections
without applying them. If the user also requests implementation, apply only
the corrections within that authorized scope.

When asked to review a file or directory:

1. Identify the complete requested diff or file scope and relevant consumers.
2. Read `.ai/rules/index.md`, `.agents/CODEBASE_RULES.md`, and every applicable
   rule. Reuse unchanged readings. Follow the global invariants for authority
   and unresolved conventions.
3. Inspect the whole scope against those rules and their documented tradeoffs.
   For HTTP changes, inspect binding, authorization, input, and response
   together; for persistence claims, follow Current Database Schema below.
4. Report each actionable finding with its file and line, applicable rule,
   demonstrated impact, and expected correction. Label unverified concerns;
   a difference from an example alone is not a violation.
5. Follow `.ai/rules/testing.md` for relevant diagnostic checks and review
   completion. Report what was and was not verified; do not infer full
   compliance from a narrow check or stop at the first finding.

## Finding Priorities

Order findings by concrete severity and affected behavior. Prioritize security
and data integrity, functional regressions, architectural violations with
demonstrated consequences, durable conventions, then style. A category alone
does not determine severity. Do not invent findings when none qualify.

## Current Database Schema

For persistence-related reviews, follow Schema Audits in
`.ai/rules/persistence-tenancy.md` for evidence, environment identification,
and access boundaries. Review migration source, relevant tests, and consumers
without assuming a database connection is required. Claims about the current
schema require evidence from the identified database.

If database access is unavailable or not authorized, continue the source
review. Report supported findings separately from unverified current-schema
claims, and identify any remaining check and access needed to complete it.

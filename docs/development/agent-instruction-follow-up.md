# Agent instruction cleanup follow-up

Status: points 1–3 of the cleanup plan are implemented. Points 4–7 below remain
open; this tracking document does not authorize external actions or permission
changes. The repository retains `.agents/CODEBASE_RULES.md` as its global
invariants entry point.

## Completed scope

- Review findings are ranked by concrete impact. Quality-gate side effects,
  failure handling, and completion criteria are centralized in
  [testing rules](../../.ai/rules/testing.md).
- Missing conventions follow the global authority and change rules; routine
  reversible decisions do not require a new rule before implementation.
- Local generator/reviewer skills describe selection boundaries and route to
  authoritative rules instead of repeating doctrine.
- Personal `find-skills` and `caveman` instructions are updated under
  `/home/happiness/.agents/skills/`. These changes are outside repository Git
  history. Discovery does not authorize installation, and ordinary brevity
  requests do not activate a persistent telegraphic style.
- The standalone memory file was removed after retaining its useful context
  in the owning rules. Existing duplicate doctrine was not copied again.

## Preserved decision context

| Context | Owning source |
| --- | --- |
| Rule authority, missing conventions, decision maintenance, sensitive information | [Global invariants](../../.agents/CODEBASE_RULES.md) |
| Nested authorization, IAM User assertions, validation-before-authorization tradeoff, morph permissions | [HTTP rules](../../.ai/rules/http-api.md) |
| Resources as intentional service outputs, retired validated DTOs, exception rendering, transactions and missing values | [Domain rules](../../.ai/rules/domain-services-data.md) |
| No universal tenant global scope; raw queries and soft deletes | [Persistence rules](../../.ai/rules/persistence-tenancy.md) |
| Inventory ownership and organization context | [Inventory rules](../../.ai/rules/inventory-tenancy.md) |
| Pest/PHPStan exceptions and integration-test boundaries | [Testing rules](../../.ai/rules/testing.md) |
| Translation ownership and message/context separation | [Localization rules](../../.ai/rules/localization.md) |

## 4. Maintained plugin corrections — pending

- [ ] Identify each plugin's maintained source and update mechanism before
  changing bundled caches. Record exact paths and versions.
- [ ] OpenAI Docs: allow local-first inspection for local instruction audits;
  use official documentation for claims requiring current product evidence.
- [ ] Sites: separate building from publishing and require authorization before
  the corresponding external action, including creation, upload, version
  saving, and deployment. Preserve public/shared access approval gates.
- [ ] Sites: make social-preview generation conditional on the requested
  deliverable; inspect relevant rendered states after visible UI changes.
- [ ] Product Design: remove rereading every second message while preserving
  the workflow's actual visual fidelity and browser constraints.
- [ ] Compare explicit visual selection with delegated art direction on a
  bounded task before changing the intentional three-option selection gate.

Acceptance: proposed diffs identify maintained sources, retain safeguards, and
pass available validators. Do not infer durable installation from cache edits.

## 5. Permissions review — pending, separate approval

- [ ] Inspect all 31 previously inventoried rules in
  `/home/happiness/.codex/rules/default.rules`, including the four long command
  payloads only partially inspected in the first audit. Redact secrets.
- [ ] Classify reads, local edits/tests, installations, external messages,
  deployments, deletion, and production access separately.
- [ ] Review broad Docker, Tinker, curl/wget, npm, and system-install prefixes;
  distinguish needed rules from historical one-off approvals.
- [ ] Prepare an exact target-by-target diff; do not replace broad approvals
  with another general allow rule.
- [ ] Verify the effective sandbox and approval profile independently of the
  prefix rules. The audited session used unrestricted filesystem access and
  no technical approval prompts; removing allow rules alone is insufficient.
- [ ] Obtain approval for the concrete permission changes before applying them.

Acceptance: the user can review the operation and target affected by each
change, its expected runtime effect, and how to restore the prior configuration.

## 6. Remaining audit coverage — pending

- [ ] Reconcile the current session's exposed skills with installed, enabled,
  cached, and temporary copies. Do not equate file presence with activation.
- [ ] Finish the Google Docs entry point and inspect the remaining previously
  metadata-only skills in bounded batches: imagegen, creators/installer, Deep
  research, Drive comments/Sheets/Slides, Notion, plugin-management, specialized
  Product Design workflows, and visualize.
- [ ] Inspect outstanding agent definitions and relevant hooks/configuration.
  Record inaccessible app-managed configuration explicitly.
- [ ] Follow linked references when they affect selection, authority,
  validation, or stopping. Report exact inspected and uninspected paths.
- [ ] Propose edits only for demonstrated friction or a clearly labeled
  hypothesis; preserve exact procedures that protect correctness.

Acceptance: an inventory distinguishes always-loaded instructions, discovery
metadata, conditional content, and access gaps. No unsupported full-coverage claim.

## 7. Consolidated validation and handoff — pending

- [ ] Validate changed skills, local links, rule routing, and final diffs after
  the remaining batches. Keep unrelated user changes intact.
- [ ] Re-run the five paper walkthroughs against the final instructions:

| Scenario | Expected boundary and completion |
| --- | --- |
| Typo | Scoped edit and appropriate check; no artificial test or new decision record |
| Migration | Source review can proceed offline; current-schema claims need verified evidence; shared/production access needs authorization |
| UI change | Relevant local build and rendered-state inspection; disclose missing visual verification |
| Failing test | Investigate and fix within scope; use TIA fallback when needed; report unrelated or blocked checks without false success |
| Approval-required deployment | Prepare the reviewable result; wait before the external action requiring approval; confirm deployment status after authorized execution |

- [ ] Run controlled before/after tasks in isolated workspaces when behavioral
  measurement is warranted. Use the same task, model, and initial state.
- [ ] Measure instruction reads/repeats, blocking questions, commands, changed
  files, missed defects, and validation quality. Keep predictions separate from
  observations; quantify savings only from measurements or labeled estimates.
- [ ] Confirm review-only tasks do not edit sources, create documents, publish,
  or connect to unapproved shared databases. Preserve tenant, authorization,
  transaction, and test safeguards.
- [ ] Deliver one final report of applied changes, actual validation results,
  remaining limitations, and decisions requiring user input.

The current structural checks do not establish behavioral improvements for
all these scenarios. Do not mark this final evaluation complete solely because
Markdown links and skill metadata validate.

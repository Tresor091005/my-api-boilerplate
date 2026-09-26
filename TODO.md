# TODO

## Services and commitments

- Implement `ServiceCommitment` as the first consumer of the shared state machine.
- Define commitment states and events as module-owned enums.
- Persist commitment transitions and any transition audit data in the owning service transaction.
- Generate concrete service deliverables from `ServiceDeliverableTemplate` when a commitment is created or activated.
- Add the deliverable execution lifecycle separately from client acceptance.
- Add evidence submission, review, requested changes, approval, and rejection flows.
- Keep execution state and client validation state as separate state machines.
- Add the necessary domain assertions, authorization rules, API endpoints, and Pest coverage for each transition.

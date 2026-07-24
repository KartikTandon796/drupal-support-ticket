# Testing — Support Ticket System (Drupal)

## Prompt 1
Write PHPUnit Kernel tests proving the state machine: each valid transition succeeds, and
at least three invalid transitions (e.g. Closed -> Open, Open -> Resolved, Cancelled ->
In Progress) are rejected with a clear error.

## Response 1
Not implemented in this session. State machine service + constraint (Step 3) is not in the
codebase yet, so Kernel tests for transitions were deferred. Planned location:
`web/modules/custom/support_tickets/tests/src/Kernel/` covering each valid edge and at
least Closed→Open, Open→Resolved, Cancelled→In Progress with assertions on validation
messages (not merely “save threw”).

## Prompt 2
Add a test proving the API layer also rejects an invalid transition, not just the entity
layer.

## Response 2
Not implemented in this session. Planned as a Functional (or Kernel + HTTP) test that
PATCHes `/jsonapi/support_ticket/support_ticket/{uuid}` with an illegal status and expects
rejection via the same entity validation constraint as the form path. Blocked on Step 3
landing first so the API has something real to reject.

## Prompt 3
Review this test file for gaps — are we only testing the happy path?

## Response 3
No test file existed to review yet. Gap checklist for when tests are written:

- Do not only assert valid transitions — require ≥3 invalid cases with clear error text.
- Cover same-status no-op (allowed) and terminal states (closed/cancelled).
- Cover entity `validate()`/`save()` and at least one JSON:API illegal PATCH.
- Avoid asserting only HTTP 500 / generic exceptions; assert validation / 422-style failure.
- Optional later: missing required fields (title/description) — useful but secondary to the
  mandatory state-machine tier.

## What I kept / changed
- Kept the mandatory Kernel + API rejection requirements from the Core brief; nothing was
  weakened to happy-path-only.
- No PHPUnit suite was added or run via `ddev exec phpunit` in this session — state machine
  code is still pending, so there was nothing green to claim.
- Next: implement Step 3, then Prompt 1 → Prompt 2 → re-run Prompt 3 against the real files
  and record pass/fail under `ddev exec`.

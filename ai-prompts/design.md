# Design — Support Ticket System (Drupal)

## Prompt to Cursor
Should the state machine validation live in the Ticket entity's preSave(), in a dedicated
StateMachineValidator service, or as a constraint plugin? Give me the trade-offs for
testability and reuse across the form and the API layer.

Design the Comment-to-Ticket relationship — entity reference field vs. a base field with a
custom storage handler. Which fits Drupal's content entity API better here?

## Response

### Status enforcement: preSave() vs service vs constraint

| Option | Pros | Cons |
|--------|------|------|
| **Only `preSave()`** | Simple; always runs on save | Awkward for forms (exceptions vs. field errors); easy to think of as “the only gate” while still needing `validate()` for good UX; harder to unit-test the pure transition map without bootstrapping a full entity save |
| **Dedicated service only** | Transition map is testable and reusable; forms/API can call it explicitly | Easy to forget a call site; not automatically attached to entity validation unless something invokes it |
| **Constraint + dedicated service** | Drupal-idiomatic; surfaces cleanly on Form API and JSON:API via `$entity->validate()`; service holds the transition map for reuse and unit tests; one rule for UI + API | Slightly more files (Constraint, ConstraintValidator, services.yml) |

**Recommendation: Service + Constraint** used from entity validation (and callable from forms if needed). `preSave()` alone is easy to bypass conceptually if callers don’t go through validation UX paths; a Constraint is the place Drupal expects business rules that must reject invalid input with a clear validation error. The service owns the allowed-edge map so Kernel tests can assert transitions without HTTP.

Form-only checks are insufficient: JSON:API and `$entity->save()` would skip them. Prefer asserting via `$entity->validate()` and API PATCH, not a thrown exception in `preSave()`.

### Comment → Ticket relationship

| Option | Fits Core entity API? | Notes |
|--------|----------------------|--------|
| **Entity reference base field** (`ticket_id` → `support_ticket`) | **Yes — preferred** | Standard `BaseFieldDefinition::create('entity_reference')`; works with Entity Query, Views, JSON:API relationships, Form API widgets, access/reference validation out of the box |
| **Custom storage handler** | Overkill for Core | Extra surface area; harder to review; not needed for a simple parent reference; fights default content-entity patterns |

**Recommendation:** a normal **entity reference** field on `support_ticket_comment` targeting `support_ticket`. That matches Drupal’s content entity API for this use case and stays inside Core scope — no custom storage handler.

## What I kept / changed
- Chose service + validation constraint (not preSave-only) so the same rule enforces on
  both form saves and JSON:API PATCH requests.
- Chose a standard entity reference field for Comment -> Ticket, not a custom storage
  handler, to stay within Core scope and Drupal's default entity API patterns.

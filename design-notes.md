# Design notes — Support Tickets (Core)

Compiled from existing repo design material (`ai-prompts/spec.md`, `ai-prompts/design.md`,
`data-model.md`, `ui-flow.md`, `api-contract.md`) rather than a fresh redesign. Stretch
items (agent/viewer roles, user CRUD UI, Docker/CI, OpenAPI, contrib workflow modules)
remain out of scope.

---

## Architecture overview

**Module:** `support_tickets` under `web/modules/custom/support_tickets/`  
**Stack:** Drupal 10 (Composer `drupal/recommended-project`), docroot `web/`

One Drupal custom module provides both surfaces — **no separate JS frontend** (no React/Vue/SPA):

| Layer | How |
|-------|-----|
| **Frontend** | Form API + entity routes + Views + Twig (`support-ticket.html.twig`, module CSS library) |
| **Backend / API** | Core **JSON:API** only — standard entity resources; no custom REST Resource plugins |
| **Users** | Core `user` entity; seed via Drush; no user-management UI in this module |

```
Browser ──► Form API / Views / Twig ──┐
                                      ├──► Entity API (Ticket, TicketComment)
JSON:API clients ──► /jsonapi/... ────┘         │
                                                 ▼
                              TicketStatusTransition Constraint
                                                 │
                              TicketStatusTransitionValidator (service)
                                                 ▼
                              DB tables: support_ticket, support_ticket_comment
```

Permissions (`access` / `create` / `edit` / `delete support tickets`, `add support ticket comments`) gate both UI and JSON:API. There is no agent vs. viewer role split in Core.

---

## Frontend design (Forms / Views / Twig)

Primary flows (from `ui-flow.md`):

```
/support-tickets  (Views list)
       │
       ├─ New ticket ──────────────► /support-tickets/add
       │                                    └─ save ──► /support-tickets/{id}
       ├─ click title ─────────────► /support-tickets/{id}  (detail)
       │                                    ├─ Update ticket (embedded TicketForm)
       │                                    ├─ Add a comment (embedded TicketCommentForm)
       │                                    └─ Edit link ──► …/edit
       └─ row Edit link ───────────► /support-tickets/{id}/edit
```

### Views list

| | |
|--|--|
| Path | `/support-tickets` (`view.support_tickets.page_list`) |
| Config | `config/install/views.view.support_tickets.yml` |
| Columns | Title (link), Status, Priority, Assignee, Updated, Edit |
| Filters | Status select + keyword search over title/description |
| Empty | “No tickets match your search.” |

Entity collection `TicketListBuilder` is secondary at `/admin/content/support-tickets` so it does not fight the Views path.

### Forms

| Form | Paths / placement | Fields |
|------|-------------------|--------|
| `TicketForm` | `/support-tickets/add`, `…/{id}/edit`, embedded on canonical | title, description, priority, status, assigned_to (`created_by` not editable) |
| `TicketCommentForm` | Embedded on ticket detail; standalone `/support-tickets/comment/add` | message; `ticket_id` hidden when preset |

Status select options are limited to **allowed targets** via `TicketForm` + `TicketStatusTransitionValidator::getAllowedTargets()` (UX aid; server-side constraint remains authoritative).

### Detail Twig

Canonical ticket page: read-only card (badges, meta), chronological comments, then update + add-comment panels when the user has permission. Presentational polish is module-scoped CSS/Twig only.

---

## Backend design (entities + state machine)

### Entities (from `ai-prompts/spec.md` / `data-model.md`)

| Entity type id | Class | Role |
|----------------|-------|------|
| `support_ticket` | `Ticket` | Support ticket content entity |
| `support_ticket_comment` | `TicketComment` | Append-oriented comments (**not** core `comment`) |
| `user` | core | Owner / assignee only |

Comment → Ticket is a normal **entity reference** base field (`ticket_id` → `support_ticket`), not a custom storage handler — preferred in `ai-prompts/design.md` because it works with Entity Query, Views, JSON:API relationships, and Form API widgets without extra surface area.

### Status state machine

Allowed non–no-op edges:

```
open ──────────► in_progress ──► resolved ──► closed
  │                    │
  └──────► cancelled ◄─┘
```

Same-status saves are allowed. `closed` and `cancelled` are terminal. New tickets must start as `open`.

### Service + Constraint (why not `preSave()`-only)

From `ai-prompts/design.md` trade-offs:

| Option | Pros | Cons |
|--------|------|------|
| **Only `preSave()`** | Simple; always runs on save | Awkward for forms (exceptions vs. field errors); harder to unit-test the pure transition map without a full entity save; easy to treat as “the only gate” while good UX still needs `validate()` |
| **Dedicated service only** | Transition map testable and reusable | Easy to forget a call site; not automatic on entity validation |
| **Constraint + dedicated service** | Drupal-idiomatic; Form API + JSON:API share `$entity->validate()`; service holds the map for Kernel tests; one rule for UI + API | Slightly more files |

**Chosen:** `TicketStatusTransitionValidator` service + entity constraint `TicketStatusTransition` (`TicketStatusTransitionConstraint` / `TicketStatusTransitionConstraintValidator`).

Reasons recorded in design/spec:

- Form-only checks are bypassed by JSON:API and programmatic `$entity->save()`.
- `preSave()` throwing does not surface clean Form API / JSON:API validation errors.
- A Constraint is where Drupal expects business rules that reject invalid input with a clear validation message; the service owns the allowed-edge map for reuse and unit tests.

JSON:API does not duplicate rules in a custom controller — it rides the same entity validation.

---

## Database design (entity schema)

Schema is created by Drupal’s entity API on module install (no hand-written SQL). Data lives in the site DB and survives restarts.

### `support_ticket`

| Machine name | Field type | Required | Default / notes |
|--------------|------------|----------|-----------------|
| `id` | integer | auto | PK |
| `uuid` | uuid | auto | |
| `title` | string (255) | yes | Entity label |
| `description` | string_long | yes | Plain long text |
| `priority` | list_string | yes | Default `medium` (`low`/`medium`/`high`/`urgent`) |
| `status` | list_string | yes | Default `open`; workflow — **not** Entity Published |
| `assigned_to` | entity_reference → user | no | Optional |
| `created_by` | entity_reference → user | yes | Owner (`EntityOwnerTrait`) |
| `created` / `changed` | created / changed | auto | |

Entity constraint: `TicketStatusTransition` on the entity type.

### `support_ticket_comment`

| Machine name | Field type | Required | Notes |
|--------------|------------|----------|--------|
| `id` / `uuid` | integer / uuid | auto | |
| `ticket_id` | entity_reference → support_ticket | yes | Parent |
| `message` | string_long | yes | Body |
| `created_by` | entity_reference → user | yes | Owner |
| `created` | created | auto | **No** `changed` field |

```
user  ◄──── created_by ──── support_ticket ──── assigned_to ────► user
                                    ▲
                                    │ ticket_id (required)
                         support_ticket_comment
                                    │
                              created_by ────► user
```

---

## Validation strategy

Layers (shared by Form API and JSON:API):

1. **Field definitions** — required flags and `list_string` allowed values for `priority` / `status`.
2. **Entity constraint `TicketStatusTransition`** — runs on `$entity->validate()`:
   - **New entities:** only initial status `open` (`isValidInitialStatus()`).
   - **Updates:** compare to original; same-status → OK; else `isTransitionAllowed($from, $to)`.
3. **Transition map** — `TicketStatusTransitionValidator::ALLOWED` (includes same-status no-ops; terminal states only map to themselves).
4. **Form UX** — status widget options restricted to allowed targets (does not replace the constraint).
5. **Reference fields** — invalid `assigned_to` / `ticket_id` UUIDs fail via core entity-reference validation.

Comment creates validate `message` + required `ticket_id`; comments have no status field, so the ticket state machine does not apply to them.

---

## Error handling strategy

**Principle (spec / design):** illegal transitions fail server-side with a **clear validation error**, not a raw exception / WSOD. Same message path for UI and API.

| Surface | Behavior |
|---------|----------|
| **Form API** | Entity validation violations → form / field errors and messenger feedback; user stays on the form. Status select already limits options; forced/stale illegal values still hit the constraint. |
| **JSON:API** | Field and state-machine violations → HTTP **`422 Unprocessable Entity`**; `errors[].detail` carries the message (e.g. `Invalid status transition from Open to Resolved. Allowed transitions follow the ticket state machine.`). Ticket status in the DB is unchanged. |
| **Other API statuses** | `401`/`403` auth/access; `404` unknown UUID; `405` if `jsonapi.settings:read_only` is true |

Representative constraint messages (from `data-model.md` / `api-contract.md`):

- New non-open: `New tickets must start in the "Open" status.`
- Bad transition: `Invalid status transition from %from to %to. Allowed transitions follow the ticket state machine.` (`%from` / `%to` use human labels when available)

Programmatic `$entity->save()` without `validate()` can still skip entity validation (Drupal default); Core relies on Form API and JSON:API paths that validate, plus PHPUnit covering `validate()` and PATCH.

---

## Testing

Automated proof of the state machine (Kernel valid/invalid transitions + Functional JSON:API PATCH rejection/success) is documented in:

**→ [test-strategy.md](./test-strategy.md)**

That document covers goals, what is and is not tested in Core, how to run PHPUnit under DDEV, and manual smoke checks complementary to the suite.

# Data model — Support Tickets

Pulled from the implemented entity classes and status-transition constraint/service — not
from the product spec.

| Source | Path |
|--------|------|
| Ticket entity | `src/Entity/Ticket.php` (`baseFieldDefinitions`, `@ContentEntityType`) |
| Ticket constants | `src/Entity/TicketInterface.php` |
| Comment entity | `src/Entity/TicketComment.php` |
| Transition map | `src/Service/TicketStatusTransitionValidator.php` (`ALLOWED`, `isValidInitialStatus`) |
| Enforcement | Entity constraint `TicketStatusTransition` → `TicketStatusTransitionConstraintValidator` |

---

## Ticket (`support_ticket`)

- **Class:** `Drupal\support_tickets\Entity\Ticket`
- **Base table:** `support_ticket`
- **Bundles:** none
- **Entity keys:** `id` → `id`, `label` → `title`, `uuid` → `uuid`, `owner`/`uid` → `created_by`
- **Entity constraint:** `TicketStatusTransition` (on the entity type, not a field plugin)

### Fields

| Machine name | Field type | Required | Default | Notes |
|--------------|------------|----------|---------|--------|
| `id` | integer (entity id) | auto | — | From `parent::baseFieldDefinitions()` |
| `uuid` | `uuid` | auto | — | From parent |
| `title` | `string` | **yes** | — | `max_length` 255; entity label |
| `description` | `string_long` | **yes** | — | Plain long text (no text format) |
| `priority` | `list_string` | **yes** | `'medium'` | Allowed values from `TicketInterface::PRIORITIES` |
| `status` | `list_string` | **yes** | `TicketInterface::STATUS_OPEN` (`'open'`) | Workflow status; **not** Entity Published. Allowed values from `TicketInterface::STATUSES` |
| `assigned_to` | `entity_reference` → `user` | **no** | — | `handler` = `default`; optional assignee |
| `created_by` | `entity_reference` → `user` | yes (owner) | current user via `EntityOwnerTrait` | From `ownerBaseFieldDefinitions()`; form display disabled |
| `created` | `created` | auto | now | |
| `changed` | `changed` | auto | now | Via `EntityChangedTrait` |

### Priority allowed values (`TicketInterface::PRIORITIES`)

| Machine name | Label |
|--------------|-------|
| `low` | Low |
| `medium` | Medium |
| `high` | High |
| `urgent` | Urgent |

### Status allowed values (`TicketInterface::STATUSES`)

| Machine name | Label |
|--------------|-------|
| `open` | Open |
| `in_progress` | In Progress |
| `resolved` | Resolved |
| `closed` | Closed |
| `cancelled` | Cancelled |

Constant used as default / initial status: `TicketInterface::STATUS_OPEN` = `'open'`.

---

## Comment (`support_ticket_comment`)

- **Class:** `Drupal\support_tickets\Entity\TicketComment`
- **Base table:** `support_ticket_comment`
- **Bundles:** none
- **Entity keys:** `id` → `id`, `uuid` → `uuid`, `owner`/`uid` → `created_by`
- **No** `changed` field
- **No** status / priority fields
- Entity type id is intentionally **not** `comment` (avoids core Comment module collision)

### Fields

| Machine name | Field type | Required | Default | Notes |
|--------------|------------|----------|---------|--------|
| `id` | integer (entity id) | auto | — | From parent |
| `uuid` | `uuid` | auto | — | From parent |
| `ticket_id` | `entity_reference` → `support_ticket` | **yes** | — | Parent ticket; `handler` = `default` |
| `message` | `string_long` | **yes** | — | Comment body |
| `created_by` | `entity_reference` → `user` | yes (owner) | current user via `EntityOwnerTrait` | Form display disabled |
| `created` | `created` | auto | now | |

`label()` is overridden to return a truncated `message` (not a stored field).

---

## Status state machine (as enforced)

Enforcement path:

1. `Ticket` entity type declares constraint `TicketStatusTransition`.
2. `TicketStatusTransitionConstraintValidator::validate()` runs on `$entity->validate()` (Form API and JSON:API).
3. Transition legality is delegated to `TicketStatusTransitionValidator`.

### Initial status (new entities)

From `TicketStatusTransitionConstraintValidator` when `$entity->isNew()`:

- Status must pass `isValidInitialStatus()` → **only `'open'` is allowed**.
- Violation message: `New tickets must start in the "Open" status.`
- Empty status string skips the constraint body (required-field validation still applies via the field definition).

### Updates (existing entities)

1. Resolve prior status from `$entity->original` or `loadUnchanged($id)`.
2. If original cannot be resolved, or **new status equals original** → **no violation** (same-status no-op allowed).
3. Otherwise require `isTransitionAllowed($from, $to)`.

`isTransitionAllowed()` also returns `FALSE` if `$from` or `$to` is not a key in `TicketInterface::STATUSES`.

### Allowed transition map (`TicketStatusTransitionValidator::ALLOWED`)

Includes same-status targets (no-ops):

| From | Allowed to |
|------|------------|
| `open` | `open`, `in_progress`, `cancelled` |
| `in_progress` | `in_progress`, `resolved`, `cancelled` |
| `resolved` | `resolved`, `closed` |
| `closed` | `closed` only |
| `cancelled` | `cancelled` only |

### Terminal states

As encoded by the map above:

- **`closed`** — only `closed → closed` (no outbound change).
- **`cancelled`** — only `cancelled → cancelled` (no outbound change).

### Edges that are rejected (examples)

Any pair not listed in `ALLOWED` is invalid, including:

| From | To |
|------|-----|
| `open` | `resolved` |
| `open` | `closed` |
| `in_progress` | `open` |
| `in_progress` | `closed` |
| `resolved` | `open` / `in_progress` / `cancelled` |
| `closed` | `open` / `in_progress` / `resolved` / `cancelled` |
| `cancelled` | `open` / `in_progress` / `resolved` / `closed` |

Violation message template (`TicketStatusTransitionConstraint::$message`):

`Invalid status transition from %from to %to. Allowed transitions follow the ticket state machine.`

(`%from` / `%to` are replaced with human labels from `TicketInterface::STATUSES` when present.)

### Diagram (allowed non–no-op edges only)

```
open ──────────► in_progress ──► resolved ──► closed
  │                    │
  └──────► cancelled ◄─┘
```

---

## Relationship summary

```
user  ◄──── created_by ──── support_ticket ──── assigned_to ────► user
                                    ▲
                                    │ ticket_id (required)
                                    │
                         support_ticket_comment
                                    │
                              created_by ────► user
```

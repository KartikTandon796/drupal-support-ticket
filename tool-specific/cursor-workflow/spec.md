# Support Tickets — Core Specification

**Module:** `support_tickets`  
**Stack:** Drupal 10.6 (Composer `drupal/recommended-project`), custom module under `web/modules/custom/support_tickets/`  
**UI:** Drupal Form API + entity routes (no separate JS frontend)  
**API:** Drupal core JSON:API  
**Users:** Drupal core `user` entity (no custom User entity)

This document describes the Core tier as built and decided through the module scaffold. It does not cover Stretch items (custom roles beyond permissions, user CRUD UI, Docker/CI, API docs).

---

## 1. Entities

### 1.1 Ticket (`support_ticket`)

Content entity. Base table: `support_ticket`.

| Field | Machine name | Type | Required | Notes |
|-------|--------------|------|----------|-------|
| ID | `id` | integer (entity id) | auto | Primary key |
| UUID | `uuid` | uuid | auto | |
| Title | `title` | `string` (max 255) | yes | Entity label |
| Description | `description` | `string_long` | yes | Plain long text (no text formats) |
| Priority | `priority` | `list_string` | yes | Default: `medium` |
| Status | `status` | `list_string` | yes | Workflow status; default: `open`. Not Entity Published. |
| Assigned to | `assigned_to` | `entity_reference` → `user` | **no** | Optional assignee |
| Created by | `created_by` | `entity_reference` → `user` | yes | Owner (`EntityOwnerTrait`) |
| Created | `created` | `created` | auto | |
| Updated | `changed` | `changed` | auto | |

**Priority allowed values**

| Machine name | Label |
|--------------|-------|
| `low` | Low |
| `medium` | Medium |
| `high` | High |
| `urgent` | Urgent |

**Status allowed values**

| Machine name | Label |
|--------------|-------|
| `open` | Open |
| `in_progress` | In Progress |
| `resolved` | Resolved |
| `closed` | Closed |
| `cancelled` | Cancelled |

New tickets default to `open`.

---

### 1.2 Comment (`support_ticket_comment`)

Content entity. Base table: `support_ticket_comment`.

Entity type id is `support_ticket_comment` (class `TicketComment`) so it does not collide with core Comment module’s `comment` entity.

| Field | Machine name | Type | Required | Notes |
|-------|--------------|------|----------|-------|
| ID | `id` | integer (entity id) | auto | Primary key |
| UUID | `uuid` | uuid | auto | |
| Ticket | `ticket_id` | `entity_reference` → `support_ticket` | yes | Parent ticket |
| Message | `message` | `string_long` | yes | Comment body |
| Created by | `created_by` | `entity_reference` → `user` | yes | Owner (`EntityOwnerTrait`) |
| Created | `created` | `created` | auto | |

No `changed` field. Comments are append-oriented in the Core UI.

---

### 1.3 User

Core `user` only. Seeded later via Drush (not part of this scaffold’s shipped data). No user-management UI in this module.

---

## 2. Status state machine

Status is stored on the ticket’s `status` field. Allowed transitions (domain rules, confirmed):

| From | To | Allowed? |
|------|-----|----------|
| `open` | `in_progress` | Yes |
| `open` | `cancelled` | Yes |
| `open` | `resolved` | No |
| `open` | `closed` | No |
| `open` | `open` | Yes (no-op / unchanged) |
| `in_progress` | `resolved` | Yes |
| `in_progress` | `cancelled` | Yes |
| `in_progress` | `open` | No |
| `in_progress` | `closed` | No |
| `in_progress` | `in_progress` | Yes (no-op) |
| `resolved` | `closed` | Yes |
| `resolved` | `open` | No |
| `resolved` | `in_progress` | No |
| `resolved` | `cancelled` | No |
| `resolved` | `resolved` | Yes (no-op) |
| `closed` | *(any other)* | No — terminal |
| `closed` | `closed` | Yes (no-op) |
| `cancelled` | *(any other)* | No — terminal |
| `cancelled` | `cancelled` | Yes (no-op) |

Diagram of allowed edges only:

```
open ──────────► in_progress ──► resolved ──► closed
  │                    │
  └──────► cancelled ◄─┘
```

**Enforcement decision:** reject invalid transitions server-side with a clear validation error (shared by forms and JSON:API), via a dedicated service + entity validation constraint — not form-only checks. Same-status saves are allowed. `closed` and `cancelled` are terminal.

**Implementation status:** status *values* are constrained by the `list_string` field. Transition validation (service + constraint) is decided and not yet wired in code.

---

## 3. Access (permissions)

Custom permissions (granted to the `authenticated` role on the local smoke-test site):

| Permission | Purpose |
|------------|---------|
| `access support tickets` | View ticket list and detail |
| `create support tickets` | Create tickets |
| `edit support tickets` | Update ticket fields / status |
| `delete support tickets` | Delete tickets |
| `add support ticket comments` | Create comments |

No agent vs. viewer role split (Stretch — out of scope).

---

## 4. UI (Form API + entity routes)

There is no separate frontend app. The UI is Drupal routes + Form API. A Views-based list with keyword search and status exposed filter is Core scope but **not** shipped in config yet; the live collection page uses `TicketListBuilder`.

### 4.1 Ticket pages

| Purpose | Method / path | Implementation |
|---------|---------------|----------------|
| List tickets | `GET /support-tickets` | `TicketListBuilder` (columns: ID, title, status, priority) |
| Create ticket | `GET/POST /support-tickets/add` | `TicketForm` (`ContentEntityForm`) |
| View ticket | `GET /support-tickets/{support_ticket}` | Entity canonical + `support-ticket.html.twig` |
| Edit ticket | `GET/POST /support-tickets/{support_ticket}/edit` | `TicketForm` |
| Delete ticket | `GET/POST /support-tickets/{support_ticket}/delete` | Core `ContentEntityDeleteForm` |

Form fields exposed from base-field display options: title, description, priority, status, assigned_to. `created_by` is not editable on the form.

### 4.2 Comment pages

| Purpose | Method / path | Implementation |
|---------|---------------|----------------|
| Add comment | `GET/POST /support-tickets/comment/add` | `TicketCommentForm` |
| View comment | `GET /support-tickets/comment/{support_ticket_comment}` | Entity canonical |
| Edit / delete comment | under `/support-tickets/comment/...` | Entity forms (not emphasized in Core UX) |

Ticket-detail-embedded comment form and a polished comments list on the ticket page are not separate custom controllers yet; comments are available as their own entity forms and via JSON:API.

---

## 5. API (JSON:API)

`jsonapi` and `serialization` are module dependencies and are enabled with `support_tickets`. Resources are the standard core JSON:API entity resources (no custom REST plugins).

Base path prefix: `/jsonapi`  
Resource type paths (no bundles):

| Resource | Path prefix |
|----------|-------------|
| Ticket | `/jsonapi/support_ticket/support_ticket` |
| Comment | `/jsonapi/support_ticket_comment/support_ticket_comment` |

### 5.1 Ticket operations

| Purpose | Method | Path |
|---------|--------|------|
| List tickets | `GET` | `/jsonapi/support_ticket/support_ticket` |
| Create ticket | `POST` | `/jsonapi/support_ticket/support_ticket` |
| Ticket detail | `GET` | `/jsonapi/support_ticket/support_ticket/{uuid}` |
| Update ticket | `PATCH` | `/jsonapi/support_ticket/support_ticket/{uuid}` |

Typical create/update attributes: `title`, `description`, `priority`, `status`, plus relationships for `assigned_to` / `created_by` as applicable. Required field validation is enforced by the entity field definitions.

### 5.2 Comment operations

| Purpose | Method | Path |
|---------|--------|------|
| List comments | `GET` | `/jsonapi/support_ticket_comment/support_ticket_comment` |
| Add comment | `POST` | `/jsonapi/support_ticket_comment/support_ticket_comment` |
| Comment detail | `GET` | `/jsonapi/support_ticket_comment/support_ticket_comment/{uuid}` |

Create payload must include `message` and a relationship to the parent ticket (`ticket_id`).

Filtering/sorting follow core JSON:API query parameters. Authentication uses Drupal’s normal session/cookie or basic auth if configured on the site; the module does not add a custom auth layer.

---

## 6. Persistence

Entity schema is created by Drupal’s entity API when the module is installed (tables `support_ticket`, `support_ticket_comment`). Data lives in the site database and survives process restarts.

---

## 7. Out of scope (Stretch — not in this Core build)

- Role differentiation (agent vs. viewer)
- Full user CRUD UI
- Pagination/sorting beyond default list/JSON:API behavior
- Docker / CI
- Separate API documentation site
- Contrib workflow/state_machine modules

---

## 8. Module layout (current)

```
web/modules/custom/support_tickets/
├── support_tickets.info.yml
├── support_tickets.module
├── support_tickets.permissions.yml
├── support_tickets.routing.yml
├── templates/support-ticket.html.twig
└── src/
    ├── Entity/
    │   ├── Ticket.php
    │   ├── TicketInterface.php
    │   ├── TicketComment.php
    │   └── TicketCommentInterface.php
    ├── Form/
    │   ├── TicketForm.php
    │   └── TicketCommentForm.php
    ├── TicketAccessControlHandler.php
    ├── TicketCommentAccessControlHandler.php
    └── TicketListBuilder.php
```

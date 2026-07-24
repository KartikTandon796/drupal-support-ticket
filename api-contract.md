# API contract — Support Tickets (JSON:API)

Base URL prefix: `/jsonapi`  
Implementation: Drupal core **JSON:API** exposing the `support_ticket` and
`support_ticket_comment` content entities from module `support_tickets`. There are
**no** custom REST Resource plugins.

| Resource | Entity type | JSON:API `type` | Collection path |
|----------|-------------|-----------------|-----------------|
| Ticket | `support_ticket` | `support_ticket--support_ticket` | `/jsonapi/support_ticket/support_ticket` |
| Comment | `support_ticket_comment` | `support_ticket_comment--support_ticket_comment` | `/jsonapi/support_ticket_comment/support_ticket_comment` |

Individual resources use UUID (not numeric id):

`/jsonapi/{entity_type}/{entity_type}/{uuid}`

**Headers (all mutating requests):**

```http
Accept: application/vnd.api+json
Content-Type: application/vnd.api+json
```

**Auth:** Drupal session cookie, or HTTP Basic Auth if `basic_auth` is enabled. Entity
access uses module permissions:

| Permission | Operations |
|------------|------------|
| `access support tickets` | GET list/detail (ticket + comment) |
| `create support tickets` | POST ticket |
| `edit support tickets` | PATCH / DELETE ticket; comment update/delete |
| `add support ticket comments` | POST comment |

**Write mode:** Core JSON:API defaults to **read-only**. POST/PATCH return **405** with
detail `JSON:API is configured to accept only read operations` until an admin disables
read-only at `/admin/config/services/jsonapi` (config key `jsonapi.settings:read_only`).
Functional tests and local demos set `read_only` to `false`.

---

## Shared response envelope

Success (single resource):

```json
{
  "jsonapi": { "version": "1.0", "meta": { "links": { "self": { "href": "http://jsonapi.org/format/1.0/" } } } },
  "data": { "type": "…", "id": "<uuid>", "attributes": { }, "relationships": { }, "links": { "self": { "href": "…" } } },
  "links": { "self": { "href": "…" } }
}
```

Success (collection): `"data"` is an array of resource objects (may be empty). Items the
current user cannot view may appear only as `meta.omitted` links rather than in `data`.

Error:

```json
{
  "jsonapi": { "version": "1.0", "…" },
  "errors": [
    {
      "title": "…",
      "status": "422",
      "detail": "…",
      "source": { "pointer": "…" }
    }
  ]
}
```

---

## Tickets

### Resource shape (as returned)

**Attributes**

| Attribute | Source field | Notes |
|-----------|--------------|--------|
| `drupal_internal__id` | `id` | Integer entity id (read-only in responses) |
| `title` | `title` | string, max 255, **required** |
| `description` | `description` | `string_long`, **required** |
| `priority` | `priority` | `low` \| `medium` \| `high` \| `urgent` (default `medium`) |
| `status` | `status` | `open` \| `in_progress` \| `resolved` \| `closed` \| `cancelled` (default `open`) |
| `created` | `created` | ISO-8601 timestamp |
| `changed` | `changed` | ISO-8601 timestamp |

**Relationships**

| Relationship | Target `type` | Notes |
|--------------|---------------|--------|
| `created_by` | `user--user` | Owner; set on create (typically current user) |
| `assigned_to` | `user--user` | **Optional**; may be `null` |

---

### 1. List tickets

| | |
|--|--|
| **Method** | `GET` |
| **Path** | `/jsonapi/support_ticket/support_ticket` |
| **Purpose** | List tickets the current user can view |
| **Request body** | none |
| **Response** | `200` — collection of ticket resource objects |
| **Validation** | Permission `access support tickets`; inaccessible rows omitted via JSON:API access filtering |
| **Errors** | `401` / `403` if unauthenticated or lacking view permission (may also yield empty `data` + `meta.omitted`) |

Optional query (core JSON:API): `?filter[status]=open`, `?sort=-changed`, `?page[limit]=…`.

---

### 2. Ticket detail

| | |
|--|--|
| **Method** | `GET` |
| **Path** | `/jsonapi/support_ticket/support_ticket/{uuid}` |
| **Purpose** | Fetch one ticket by UUID |
| **Request body** | none |
| **Response** | `200` — single ticket resource (shape above) |
| **Validation** | View access on that entity |
| **Errors** | `403` if denied; `404` if UUID unknown |

---

### 3. Create ticket

| | |
|--|--|
| **Method** | `POST` |
| **Path** | `/jsonapi/support_ticket/support_ticket` |
| **Purpose** | Create a new ticket |
| **Request body** | see below |
| **Response** | `201` — created ticket resource; `Location` / `links.self` point at the new UUID URL |
| **Validation** | See [Ticket validation](#ticket-validation-rules) |
| **Errors** | `401`/`403`; `405` if JSON:API read-only; `422` on field/state-machine violations |

**Request body:**

```json
{
  "data": {
    "type": "support_ticket--support_ticket",
    "attributes": {
      "title": "Cannot reset password",
      "description": "Password reset emails are not arriving.",
      "priority": "high",
      "status": "open"
    },
    "relationships": {
      "assigned_to": {
        "data": {
          "type": "user--user",
          "id": "<user-uuid>"
        }
      }
    }
  }
}
```

- `assigned_to` may be omitted or `"data": null`.
- `status` must be `open` for new tickets (or omit to use the field default `open`).
- Creating with `status` other than `open` is rejected by `TicketStatusTransition`.

---

### 4. Update ticket

| | |
|--|--|
| **Method** | `PATCH` |
| **Path** | `/jsonapi/support_ticket/support_ticket/{uuid}` |
| **Purpose** | Update fields and/or change status |
| **Request body** | see below |
| **Response** | `200` — updated ticket resource |
| **Validation** | See [Ticket validation](#ticket-validation-rules); status changes must follow the state machine |
| **Errors** | `401`/`403`; `404`; `405` if read-only; **`422` on invalid status transition** and other field violations |

**Request body (status change example):**

```json
{
  "data": {
    "type": "support_ticket--support_ticket",
    "id": "<ticket-uuid>",
    "attributes": {
      "status": "in_progress"
    }
  }
}
```

`id` in the body must match the UUID in the path. Only include attributes/relationships you
intend to change.

---

### Ticket validation rules

Enforced on POST/PATCH via entity field constraints + entity constraint
`TicketStatusTransition` (shared with Form API):

| Rule | Effect |
|------|--------|
| `title` required | Empty/missing → `422` |
| `description` required | Empty/missing → `422` |
| `priority` ∈ allowed list | Invalid value → `422` |
| `status` ∈ allowed list | Invalid value → `422` |
| **New ticket status** | Must be `open` — message: `New tickets must start in the "Open" status.` |
| **Status transition** | Only edges below; same-status save allowed |
| `assigned_to` | Optional user reference; invalid UUID → `422` |

**Allowed transitions** (`TicketStatusTransitionValidator`):

```
open         → open | in_progress | cancelled
in_progress  → in_progress | resolved | cancelled
resolved     → resolved | closed
closed       → closed          (terminal)
cancelled    → cancelled       (terminal)
```

**Invalid transition rejection (API):**

- HTTP **`422 Unprocessable Entity`**
- `errors[].detail` contains text like:  
  `Invalid status transition from Open to Resolved. Allowed transitions follow the ticket state machine.`
- Proven by `TicketStatusTransitionJsonApiTest::testJsonApiRejectsInvalidOpenToResolved`
  (e.g. open → resolved). Ticket status in the database is **unchanged**.

Examples that must fail with `422`:

| From | To |
|------|-----|
| `open` | `resolved` |
| `closed` | `open` |
| `cancelled` | `in_progress` |

---

## Comments

### Resource shape (as returned)

**Attributes**

| Attribute | Source field | Notes |
|-----------|--------------|--------|
| `drupal_internal__id` | `id` | Integer id |
| `message` | `message` | `string_long`, **required** |
| `created` | `created` | ISO-8601 timestamp |

**Relationships**

| Relationship | Target `type` | Notes |
|--------------|---------------|--------|
| `ticket_id` | `support_ticket--support_ticket` | **Required** parent ticket |
| `created_by` | `user--user` | Comment author |

---

### 5. List comments

| | |
|--|--|
| **Method** | `GET` |
| **Path** | `/jsonapi/support_ticket_comment/support_ticket_comment` |
| **Purpose** | List comments the current user can view |
| **Request body** | none |
| **Response** | `200` — collection of comment resources |
| **Validation** | `access support tickets` |
| **Errors** | `401`/`403`; possible empty `data` + `meta.omitted` |

Filter by ticket (example):  
`?filter[ticket_id.id]=<ticket-uuid>` (core JSON:API relationship filter).

---

### 6. Comment detail

| | |
|--|--|
| **Method** | `GET` |
| **Path** | `/jsonapi/support_ticket_comment/support_ticket_comment/{uuid}` |
| **Purpose** | Fetch one comment |
| **Request body** | none |
| **Response** | `200` — single comment resource |
| **Errors** | `403`, `404` |

---

### 7. Add comment

| | |
|--|--|
| **Method** | `POST` |
| **Path** | `/jsonapi/support_ticket_comment/support_ticket_comment` |
| **Purpose** | Append a comment to a ticket |
| **Request body** | see below |
| **Response** | `201` — created comment resource |
| **Validation** | `message` required; `ticket_id` relationship required and must reference an existing `support_ticket`; permission `add support ticket comments` |
| **Errors** | `401`/`403`; `405` if read-only; `422` if `message` empty or `ticket_id` missing/invalid |

**Request body:**

```json
{
  "data": {
    "type": "support_ticket_comment--support_ticket_comment",
    "attributes": {
      "message": "Comment from API"
    },
    "relationships": {
      "ticket_id": {
        "data": {
          "type": "support_ticket--support_ticket",
          "id": "<ticket-uuid>"
        }
      }
    }
  }
}
```

Comments have no status field; the ticket state machine does not apply to comment create.

---

## Common error responses (this module)

| Status | When |
|--------|------|
| `401` | Missing/invalid credentials (e.g. Basic Auth failure) |
| `403` | Authenticated but missing permission / entity access denied |
| `404` | Unknown UUID |
| `405` | POST/PATCH while `jsonapi.settings:read_only` is `true` |
| `422` | Field required/allowed-values failure, **or invalid status transition / non-open create status** |

Invalid transition `errors[0].detail` (representative):

```text
title: Invalid status transition from Open to Resolved. Allowed transitions follow the ticket state machine.
status: Invalid status transition from Open to Resolved. Allowed transitions follow the ticket state machine.
```

(JSON:API may prefix the field path; the important part is the transition message from
`TicketStatusTransitionConstraint`.)

---

## Implementation map

| Concern | Code |
|---------|------|
| Resources | Entity types `support_ticket`, `support_ticket_comment` + core `jsonapi` |
| Access | `TicketAccessControlHandler`, `TicketCommentAccessControlHandler` |
| Status machine | `TicketStatusTransitionValidator` + `TicketStatusTransition` constraint on Ticket |
| API rejection proof | `tests/src/Functional/TicketStatusTransitionJsonApiTest.php` |
| Entity rejection proof | `tests/src/Kernel/TicketStatusTransitionTest.php` |

There is **no** module-specific route YAML for these API paths — routes are generated by
core JSON:API from the entity type definitions.

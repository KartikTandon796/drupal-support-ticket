# Project context — Support Tickets (Cursor workflow)

Agreed context for AI-assisted work on this repo. Reflects what was **built and decided**,
not a restatement of the original exercise prompt. Prefer updating this file (and
`.cursor/rules/`) when scope or conventions change.

---

## Stack decision

| Choice | Decision |
|--------|----------|
| Product | Internal **Core-tier** Support Ticket Management System |
| Platform | **Drupal 10** Composer project (`drupal/recommended-project`), docroot `web/` |
| Feature code | Custom module `support_tickets` at `web/modules/custom/support_tickets/` |
| UI | **Form API + Views + Twig** inside the module — **no** separate React/Vue/SPA |
| API | Core **JSON:API** only — no custom REST Resource plugins |
| Users | Core `user` entity; seed via Drush; **no** user-management UI |
| Local run | **DDEV primary** (project `support-tickets`, MariaDB 10.11, PHP 8.3); non-DDEV Composer/Drush secondary in README |

Installed core version in practice: Drupal **10.6.x**. Module dependencies include
`user`, `views`, `jsonapi`, `serialization`, `options`.

---

## Domain model

### Entities

| Entity | Type id | Notes |
|--------|---------|--------|
| Ticket | `support_ticket` | Content entity; class `Ticket` |
| Comment | `support_ticket_comment` | Content entity; class `TicketComment` — **not** core `comment` |
| User | `user` | Core only |

### Ticket fields (`support_ticket`)

| Field | Type | Required | Notes |
|-------|------|----------|--------|
| `title` | string (max 255) | yes | Entity label |
| `description` | string_long | yes | Plain long text |
| `priority` | list_string | yes | `low` / `medium` / `high` / `urgent`; default **medium** |
| `status` | list_string | yes | Workflow status; default **open**; **not** Entity Published |
| `assigned_to` | entity_reference → user | **no** | Optional assignee |
| `created_by` | entity_reference → user | yes | Owner (`EntityOwnerTrait`) |
| `created` / `changed` | created / changed | auto | |

### Comment fields (`support_ticket_comment`)

| Field | Type | Required | Notes |
|-------|------|----------|--------|
| `ticket_id` | entity_reference → `support_ticket` | yes | Parent ticket |
| `message` | string_long | yes | Comment body |
| `created_by` | entity_reference → user | yes | Owner |
| `created` | created | auto | **No** `changed` — append-oriented in Core UI |

### Permissions (custom)

`access support tickets`, `create support tickets`, `edit support tickets`,
`delete support tickets`, `add support ticket comments`. Flat permission model in Core —
no agent vs viewer role split.

---

## Status state machine

Machine names: `open`, `in_progress`, `resolved`, `closed`, `cancelled`.

Allowed non–no-op edges:

```
open ──────────► in_progress ──► resolved ──► closed
  │                    │
  └──────► cancelled ◄─┘
```

| Rule | Detail |
|------|--------|
| Same-status save | Allowed (no-op) |
| Terminal states | `closed` and `cancelled` — no outbound change except self |
| New tickets | Must start as `open` |
| Enforcement | Server-side; clear validation error (UI form error / JSON:API `422`) — not raw exceptions |

Transition map lives in `TicketStatusTransitionValidator`; entity constraint
`TicketStatusTransition` runs on `$entity->validate()`.

---

## Core vs Stretch scope boundary

**In Core (what we build):** tickets + comments, Forms/Views/Twig UI, state machine shared
by forms and JSON:API, seed command, PHPUnit for valid/invalid transitions (+ API rejection),
README with DDEV primary path.

**Out of Core (Stretch — do not expand unless explicitly asked):**

- Agent vs viewer roles / fine-grained ACLs beyond the module’s custom permissions
- Full user CRUD UI
- Docker/CI pipelines beyond DDEV, OpenAPI / API docs sites
- Contrib State Machine / Workflow modules
- Pagination/sort beyond default Views / JSON:API

When unsure: smallest Core change. Prefer updating `spec.md` / `TASKS.md` /
`implementation-plan.md` over inventing features.

---

## Key implementation decisions (agreed along the way)

### 1. State machine: service + validation constraint (not `preSave()`-only)

**Chosen:** `TicketStatusTransitionValidator` service + `TicketStatusTransition` entity
constraint (Constraint + ConstraintValidator), wired on the Ticket entity type.

**Why:** Form-only checks are bypassed by JSON:API and programmatic saves. `preSave()`
throwing is awkward for Form API / JSON:API UX (exceptions vs validation messages). A
Constraint runs on `$entity->validate()`, which both Form API and JSON:API use; the
service owns the allowed-edge map for reuse and Kernel tests. One rule for UI + API.

**Also:** `TicketForm` limits status select options to allowed targets (UX aid only —
constraint remains authoritative).

### 2. Comment → Ticket: entity reference field (not custom storage)

**Chosen:** standard `entity_reference` base field `ticket_id` → `support_ticket`.

**Why:** Fits Drupal’s content entity API; works with Entity Query, Views, JSON:API
relationships, and Form API widgets without a custom storage handler. Custom storage would
be overkill for Core.

### 3. D10-compatible entity annotations

**Chosen:** `@ContentEntityType` / `@Constraint` annotations on entity and constraint
classes (Drupal 10 style), with `handlers`, `entity_keys`, `links`, and
`constraints = { "TicketStatusTransition" = {} }` on Ticket. HTML routes via
`DefaultHtmlRouteProvider`.

**Why:** Matches the Drupal 10 project we scaffolded; Attribute-based entity plugins are
not required for this Core build.

### 4. DDEV as primary local environment

**Chosen:** `.ddev/config.yaml` — type `drupal10`, docroot `web`, MariaDB 10.11, PHP 8.3;
project name `support-tickets` → `https://support-tickets.ddev.site`.

**Why:** Reproducible “clone → start → install → enable → seed” path for graders and
developers. Non-DDEV Composer + `--db-url` (SQLite or MySQL) remains documented as secondary.

### 5. Related practical decisions (also locked)

| Topic | Decision |
|-------|----------|
| Views vs entity collection | Views owns `/support-tickets`; collection at `/admin/content/support-tickets` |
| Status filter on Views | String/`=` filter + exposed form alter to select (avoid `list_field` fatal on base fields) |
| Schema / demo data | Entity install on `drush en support_tickets` — **no** Migrate API; demo via `drush support_tickets:seed` (`st-seed`) |
| Ownership / ACLs | Flat permissions; no owner-only update rules in Core (Stretch if ever needed) |
| Comment entity id | `support_ticket_comment` to avoid colliding with core Comment |

---

## Where things live

| Concern | Path |
|---------|------|
| Module | `web/modules/custom/support_tickets/` |
| Transition service | `src/Service/TicketStatusTransitionValidator.php` |
| Constraint | `src/Plugin/Validation/Constraint/TicketStatusTransition*` |
| Persistent Cursor rules | `.cursor/rules/` (stack, domain, Drupal standards) |
| Task checklist | `TASKS.md`, `implementation-plan.md`, `tool-specific/cursor-workflow/tasks.md` |
| Spec / design trail | `ai-prompts/`, `design-notes.md`, `data-model.md`, `ui-flow.md`, `api-contract.md` |

---
description: Drupal coding standards and PHPUnit expectations for support_tickets
globs: web/modules/custom/support_tickets/**/*.{php,module,install,inc,yml,twig,css}
alwaysApply: false
---

# Drupal coding standards

- Follow **Drupal coding standards** (PHP): `declare(strict_types=1);`, useful docblocks, injectable services where appropriate, snake_case machine names, PSR-4 under `Drupal\support_tickets\`.
- Prefer Entity API, Form API, Views config, and JSON:API over one-off controllers/SQL.
- Module CSS/JS only via `*.libraries.yml`; keep styling scoped — do not restyle the global theme.
- No hardcoded credentials; no `eval`; no auth bypasses.
- Minimal diffs: match existing module patterns; do not add Composer packages unless necessary and justified.

```php
// ❌ BAD — form-only status check
public function validateForm(array &$form, FormStateInterface $form_state): void {
  // only place transition rules live
}

// ✅ GOOD — entity validation used by forms + JSON:API
$violations = $ticket->validate();
```

## Tests

- Use **PHPUnit** (Kernel and/or Functional) under `tests/src/`.
- Mandatory coverage: every **valid** status transition succeeds; at least **2–3 invalid** transitions rejected with a clear error; include **API-level** rejection if JSON:API is in play.
- After logic changes, add/update tests; say if tests were not run and why.
---
description: Ticket/Comment entities and status state-machine conventions
alwaysApply: true
---

# Domain conventions

## Entities

| Entity | Type id | Notes |
|--------|---------|--------|
| Ticket | `support_ticket` | Content entity |
| Comment | `support_ticket_comment` | Not core `comment` |
| User | `user` | Core only |

**Ticket fields:** `title` (string, required), `description` (`string_long`, required), `priority` (`list_string`: low/medium/high/urgent, default medium), `status` (`list_string` workflow — not Entity Published), `assigned_to` (user ref, **optional**), `created_by` (owner), `created`, `changed`.

**Comment fields:** `ticket_id` → ticket, `message` (`string_long`), `created_by`, `created`. Append-oriented in Core UI.

New tickets default to status `open`.

## Status state machine

Allowed transitions only:

```
open → in_progress → resolved → closed
open → cancelled
in_progress → cancelled
```

- Same-status save is allowed (no-op).
- `closed` and `cancelled` are **terminal**.
- Enforce in a **service + validation constraint** shared by forms and JSON:API — not form-only checks.
- Invalid transitions → clear validation error surfaced in UI (not raw exceptions).

Status machine names: `open`, `in_progress`, `resolved`, `closed`, `cancelled`.
---
description: Support Tickets stack, UI/API choice, and Core vs Stretch scope
alwaysApply: true
---

# Stack & scope (Core)

- **Product:** internal Support Ticket Management System (Core tier only).
- **Stack:** Drupal 10 Composer project (`web/` docroot). Feature code lives in custom module `support_tickets` at `web/modules/custom/support_tickets/`.
- **UI:** Drupal Form API + Views + Twig inside the module — **no** separate React/Vue/SPA frontend.
- **API:** core **JSON:API** only — no custom REST Resource plugins unless explicitly requested.
- **Users:** core `user` entity; seed via Drush. No user-management UI.
- **Local run:** prefer DDEV (`docroot: web`); keep non-DDEV Composer/Drush steps in README as secondary.

## Core vs Stretch

Do **not** expand into Stretch unless the user explicitly asks:

- Agent vs viewer roles / fine-grained ACLs beyond the module’s custom permissions
- Full user CRUD UI
- Docker/CI pipelines, OpenAPI/API docs sites
- Contrib State Machine / Workflow modules
- Pagination/sort beyond default Views / JSON:API

When unsure, implement the smallest Core change. Prefer updating `spec.md` / `TASKS.md` over inventing features.

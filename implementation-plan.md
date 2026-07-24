# Implementation plan

Same Core build plan as `TASKS.md` / `tool-specific/cursor-workflow/tasks.md` — organized under overview, task breakdown, milestones, AI usage, and risks. Task text is reused, not rewritten.

## Overview

Ordered checklist for the Support Tickets Core tier (~**9–12 hours**).  
Stretch is out of scope (agent/viewer roles, user CRUD UI, Docker/CI, API docs, contrib workflow modules).

Work top to bottom. Pause for review between groups if pairing.

**Budget summary**

| Group | Estimate |
|-------|----------|
| Module scaffold | 45–60 min |
| Entities | 1.5–2 h |
| State machine enforcement | 1–1.5 h |
| Forms | 1–1.5 h |
| Views | 1.5–2 h |
| API layer | 45–60 min |
| Seed data | 30–45 min |
| Tests | 1.5–2 h |
| README | 30–45 min |
| **Total** | **~9–12 h** |

---

## Task breakdown

### 1. Module scaffold (~45–60 min)

- [ ] **1.1** Composer Drupal 10 project + Drush  
  - *~20 min*  
  - **Done:** `composer.json` uses `web/` docroot; `vendor/bin/drush` (or `ddev drush`) bootstraps; site can be installed.

- [ ] **1.2** DDEV config (optional but recommended for “run locally”)  
  - *~15 min*  
  - **Done:** `ddev start` works; `.ddev/config.yaml` committed; docroot `web`, type `drupal10`.

- [ ] **1.3** Empty `support_tickets` module shell  
  - *~15 min*  
  - **Done:** `web/modules/custom/support_tickets/support_tickets.info.yml` (deps: `user`, `views`, `jsonapi`, `serialization`, `options`); module enables with `drush en support_tickets -y` without errors.

- [ ] **1.4** Permissions + PSR-4 layout  
  - *~10 min*  
  - **Done:** `access/create/edit/delete support tickets` + `add support ticket comments` in `*.permissions.yml`; `src/` folders exist for Entity/Form/etc.

### 2. Entities (~1.5–2 h)

- [ ] **2.1** Ticket content entity (`support_ticket`)  
  - *~45–60 min*  
  - **Done:** Base fields match spec (title, description, priority, status, optional `assigned_to`, `created_by`, created/changed); defaults `status=open`, `priority=medium`; tables exist after enable; can create/load via entity API.

- [ ] **2.2** Comment content entity (`support_ticket_comment`)  
  - *~30–40 min*  
  - **Done:** Fields `ticket_id`, `message`, `created_by`, `created`; no clash with core `comment`; can save a comment referencing a ticket.

- [ ] **2.3** Access control handlers  
  - *~20 min*  
  - **Done:** View/create/update/delete gated by the custom permissions (not admin-only); anonymous denied without those perms.

- [ ] **2.4** Entity routes + stub forms/list builder  
  - *~20 min*  
  - **Done:** Canonical/add/edit/delete routes resolve; module install creates schema with no manual SQL.

### 3. State machine enforcement (~1–1.5 h)

- [ ] **3.1** Transition map service  
  - *~25–35 min*  
  - **Done:** Service (e.g. `TicketStatusTransitionValidator`) encodes allowed edges from the spec; same-status allowed; `closed`/`cancelled` terminal; unit-testable without HTTP.

- [ ] **3.2** Constraint / validation plugin wired to Ticket  
  - *~30–40 min*  
  - **Done:** Invalid transition on save fails entity validation with a clear message (not a raw exception); works from `$entity->save()` / `validate()`, not only forms.

- [ ] **3.3** New tickets forced to `open` (if not already)  
  - *~10–15 min*  
  - **Done:** Create path cannot start as `resolved`/`closed`/etc.; default + validation agree with spec.

### 4. Forms (~1–1.5 h)

- [ ] **4.1** Ticket create/edit form (`TicketForm`)  
  - *~30–40 min*  
  - **Done:** Can create/edit title, description, priority, assignee, status; required fields reject empty submit; invalid status transition shows a **user-facing** error (messenger / form error), not a WSOD.

- [ ] **4.2** Ticket detail presentation  
  - *~25–35 min*  
  - **Done:** Canonical page shows ticket fields clearly; status visible; update control available to permitted users.

- [ ] **4.3** Comment form on ticket (or dedicated add form)  
  - *~20–30 min*  
  - **Done:** Authenticated user with permission can add a comment tied to the ticket; redirects back to ticket detail; `ticket_id` not clumsily re-picked when already known.

### 5. Views (~1.5–2 h)

- [ ] **5.1** Ticket list View at `/support-tickets`  
  - *~40–50 min*  
  - **Done:** Table lists tickets; title links to detail; status + priority columns; default sort usable; path does not fight entity collection route.

- [ ] **5.2** Exposed filters: status + keyword  
  - *~25–35 min*  
  - **Done:** Status filter (select) and search over title/description work; empty result shows a clear message (e.g. “No tickets match your search”).

- [ ] **5.3** Sortable columns (Title, Status, Priority)  
  - *~10–15 min*  
  - **Done:** Clicking those headers sorts via default Views behavior.

- [ ] **5.4** Light presentational polish (optional within Core budget)  
  - *~20–30 min*  
  - **Done:** Badges/CSS via module library + Twig only; no new fields/filters; scoped to the module (no global theme rewrite).

### 6. API layer (~45–60 min)

JSON:API only — no custom REST resources.

- [ ] **6.1** Confirm JSON:API resources for both entities  
  - *~15–20 min*  
  - **Done:** With module enabled, `/jsonapi/support_ticket/support_ticket` and `/jsonapi/support_ticket_comment/support_ticket_comment` respond (auth as needed).

- [ ] **6.2** Exercise CRUD via API  
  - *~20–25 min*  
  - **Done:** Authenticated request can list, GET-by-uuid, POST create, PATCH update ticket; POST comment with `ticket_id` relationship; missing required fields → 422-style validation error.

- [ ] **6.3** Confirm state machine on API updates  
  - *~10–15 min*  
  - **Done:** PATCH with an illegal status transition is rejected (same validation as entity layer); legal transition succeeds.

### 7. Seed data (~30–45 min)

- [ ] **7.1** Drush seed command  
  - *~30–45 min*  
  - **Done:** e.g. `drush support_tickets:seed` creates a handful of users + tickets covering `open` / `in_progress` / `resolved` / `closed` / `cancelled` (and at least one comment); safe to re-run (skip or update existing); grants ticket permissions to `authenticated` if needed.

### 8. Tests (~1.5–2 h)

Mandatory: prove the state machine.

- [ ] **8.1** Kernel tests — valid transitions  
  - *~30–40 min*  
  - **Done:** Each allowed edge (`open→in_progress`, `open→cancelled`, `in_progress→resolved`, `in_progress→cancelled`, `resolved→closed`) succeeds on save.

- [ ] **8.2** Kernel tests — invalid transitions  
  - *~25–35 min*  
  - **Done:** At least 2–3 illegal cases fail validation (e.g. `open→resolved`, `resolved→in_progress`, `closed→open`) with an assertion on the error — not just “save threw something.”

- [ ] **8.3** API-level rejection (Functional or Kernel + HTTP)  
  - *~30–40 min*  
  - **Done:** At least one test shows JSON:API PATCH of an illegal transition is rejected; optional: one legal PATCH succeeds.

- [ ] **8.4** Run suite green  
  - *~10 min*  
  - **Done:** `drush`/`phpunit` for the module’s tests passes locally (document the exact command in README).

### 9. README (~30–45 min)

- [ ] **9.1** Primary path: DDEV from fresh clone  
  - *~20–25 min*  
  - **Done:** Steps: `ddev start` → `ddev composer install` → `site:install` → `en support_tickets` → seed → URL via `ddev launch` / `ddev describe`; admin + demo logins noted.

- [ ] **9.2** Secondary path: without DDEV  
  - *~10–15 min*  
  - **Done:** Composer + Drush + SQLite or MySQL install instructions; how to enable module and seed.

- [ ] **9.3** Sanity pointers  
  - *~5–10 min*  
  - **Done:** Links/paths for UI (`/support-tickets`), JSON:API base paths, test command; explicitly no Stretch setup.

---

## Milestones

Definition of “Core done” — you can stop when all of the following are true:

1. Tickets + comments persist in the DB and survive restart.  
2. UI: create, list (Views + search/status filter), detail + comments, edit fields, status changes.  
3. Illegal status transitions fail server-side with a clear UI/API error.  
4. JSON:API supports list/detail/create/update ticket + add comment.  
5. Seed command loads demo data.  
6. PHPUnit proves valid + invalid transitions (entity + at least one API case).  
7. README gets a stranger from clone → working site without guessing.

Budget milestones map 1:1 to the Task breakdown groups (scaffold → entities → state machine → forms → Views → API → seed → tests → README), totaling ~9–12 hours.

---

## AI usage plan

Execute the Task breakdown in Cursor, top to bottom, pausing for review between groups when pairing.

- Persistent project rules: `.cursor/rules/` (stack, domain, Drupal standards).
- Same checklist copy: `TASKS.md` and `tool-specific/cursor-workflow/tasks.md`.
- Prompt / decision log: `ai-prompts/` (planning, design, implementation, testing, debugging, documentation).
- Vertical-slice order (from planning): module → Ticket entity → Comment → state machine → forms polish → Views → JSON:API → seed / tests / README — smoke-test on a running site after each slice.

---

## Risks / mitigations

**Explicitly skip (Stretch)** — do not expand Core into:

- Agent vs viewer roles / fine-grained ACLs  
- User management UI  
- Custom REST plugins / OpenAPI docs  
- Docker/CI beyond DDEV  
- Contrib State Machine / Workflow modules  
- Pagination/sort beyond default Views / JSON:API  

**Drupal gotchas called out in planning** (mitigations already chosen):

| Risk | Mitigation |
|------|------------|
| Views path vs entity `collection` | Views owns `/support-tickets`; collection at `/admin/content/support-tickets` |
| `list_field` on base fields | String filter + exposed form alter to select |
| Config install vs already-enabled module | Verify active config; update hooks / reinstall as needed |
| State machine only in forms | Service + Constraint shared by forms and JSON:API |
| `status` vs Entity Published | Workflow field not mapped as published |
| Comment entity id clash | Use `support_ticket_comment` |
| JSON:API empty/`omitted` / curl from `ddev exec` | Permissions + curl from host / `http://127.0.0.1` |

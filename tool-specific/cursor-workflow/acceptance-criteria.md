# Acceptance Criteria (Core)

Unchecked checklist for manual verification. Based on `tool-specific/cursor-workflow/spec.md`
and the exercise guide’s Core features / mandatory tests. Phrased as testable statements.

Leave boxes unchecked until you verify each item yourself.

---

## Ticket CRUD

- [ ] A user with create permission can create a ticket via the form (`/support-tickets/add`) with title, description, priority, optional assignee, and default status `open`.
- [ ] A user with access permission can view the ticket list at `/support-tickets` (Views page).
- [ ] A user with access permission can open a ticket detail page and see title, description, status, priority, assignee, and metadata.
- [ ] A user with edit permission can update ticket fields (title, description, priority, assignee) via the edit form or the detail-page update form.
- [ ] A user with delete permission can delete a ticket via the delete form.
- [ ] An authenticated API client can list tickets via `GET /jsonapi/support_ticket/support_ticket`.
- [ ] An authenticated API client can fetch one ticket via `GET /jsonapi/support_ticket/support_ticket/{uuid}`.
- [ ] An authenticated API client can create a ticket via `POST /jsonapi/support_ticket/support_ticket`.
- [ ] An authenticated API client can update a ticket via `PATCH /jsonapi/support_ticket/support_ticket/{uuid}`.

---

## Status transitions

- [ ] A user can change status along allowed edges only: `open → in_progress`, `open → cancelled`, `in_progress → resolved`, `in_progress → cancelled`, `resolved → closed`.
- [ ] A user can save a ticket without changing status (same-status no-op is allowed).
- [ ] The backend rejects illegal transitions (e.g. `open → resolved`, `closed → open`, `cancelled → in_progress`) with a clear validation error — not a raw exception / WSOD.
- [ ] The UI surfaces an illegal status change as a user-facing form/messenger error.
- [ ] JSON:API `PATCH` with an illegal status transition is rejected (HTTP `422`) with an error detail about the transition.
- [ ] JSON:API `PATCH` with a legal status transition succeeds and persists the new status.
- [ ] A new ticket cannot be created with a non-`open` status; the backend rejects it with a clear validation message.
- [ ] Terminal statuses `closed` and `cancelled` cannot be moved to any other status.

---

## Comments

- [ ] A user with add-comment permission can add a comment from the ticket detail page (message required; parent ticket preset/hidden).
- [ ] After saving a comment, the user is returned to the ticket detail page and the new comment appears in the comments list.
- [ ] Comment messages are shown on the ticket detail page in chronological order.
- [ ] An authenticated API client can add a comment via `POST /jsonapi/support_ticket_comment/support_ticket_comment` with `message` and a `ticket_id` relationship.
- [ ] The backend rejects a comment create missing required `message` or `ticket_id` with a validation / `422` error.

---

## Search/filter

- [ ] A user can filter the ticket list by status using the exposed status select on `/support-tickets`.
- [ ] A user can search tickets by keyword across title and description using the exposed search field.
- [ ] When no tickets match the filter/search, the list shows a clear empty message (e.g. “No tickets match your search.”).
- [ ] A user can sort the list by Title, Status, and Priority by clicking the Views column headers.

---

## Persistence

- [ ] Created tickets and comments are stored in the Drupal database (`support_ticket`, `support_ticket_comment` tables created when the module is enabled — not via Migrate).
- [ ] Ticket and comment data survives a site/process restart (e.g. `ddev restart` or web/container restart) and still appears in the UI and API.
- [ ] Demo seed data can be loaded with `drush support_tickets:seed` (alias `st-seed`) and includes tickets covering `open`, `in_progress`, `resolved`, `closed`, and `cancelled`.

---

## Validation

- [ ] The backend rejects ticket create/update missing required `title` or `description` at the entity/API level (not only client-side).
- [ ] The backend rejects invalid `priority` or `status` values outside the allowed lists.
- [ ] Status transition rules are enforced by the shared service + entity validation constraint (not form-only checks), so form saves and JSON:API writes share the same gate.
- [ ] Meaningful error states are surfaced in the UI (form/messenger messages) rather than raw PHP exceptions.

---

## Testing

- [ ] PHPUnit Kernel tests prove each valid status transition succeeds on entity validate/save.
- [ ] PHPUnit Kernel tests prove at least 2–3 invalid transitions are rejected with a clear validation error (not a generic exception).
- [ ] PHPUnit proves new tickets cannot start in a non-`open` status.
- [ ] PHPUnit Functional (or equivalent HTTP) tests prove JSON:API rejects an illegal status `PATCH` and accepts at least one legal `PATCH`.
- [ ] The module’s state-machine test suite can be run locally (commands documented in README / `test-strategy.md`) and passes.

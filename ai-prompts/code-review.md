# Code review — Support Tickets module

Review of `web/modules/custom/support_tickets/` for: unvalidated input reaching the
database, missing error handling, state-machine bypass (including JSON:API), and
hardcoded values that should be config. Issues listed by severity.

**Scope:** Core tier only. Findings reflect the codebase as of this review (state machine
service + constraint not yet implemented).

---

## Critical

### C1 — Status transitions are completely unenforced

The `status` field is a plain `list_string` with allowed *values* only. There is no
service, Constraint, or `preSave()` guard. Every write path accepts any status → any
status:

| Path | Behavior |
|------|----------|
| JSON:API `PATCH` | Illegal transitions (e.g. `closed → open`) succeed |
| `TicketForm` / embedded edit on detail | Status select lists all five statuses with no filtering by current state |
| `$entity->save()` / `setStatusValue()` | No transition validation |
| Seed command | Writes arbitrary statuses via `$ticket->save()` |

**Why it matters:** Spec and `.cursor/rules` require server-side enforcement shared by
forms and JSON:API. Today the state machine can be bypassed by any API or programmatic
save — there is effectively no gate.

**Fix:** Implement `TicketStatusTransitionValidator` service + a validation Constraint on
the Ticket (or `status` field). Form API and JSON:API both run entity validation, so one
constraint covers both. Same-status saves allowed; `closed` / `cancelled` terminal.

---

## High

### H1 — Seed grants full CRUD (including delete) to every authenticated user

`SupportTicketsCommands::seed()` calls `user_role_grant_permissions('authenticated', …)`
with access, create, edit, **delete**, and add-comment permissions.

Any logged-in user can edit and delete any ticket. Seeding demo data should not silently
widen the site-wide permission model.

**Fix:** Move permission grants out of seed (install hook or documented manual step). Do
not grant `delete support tickets` to `authenticated` by default for demo.

### H2 — Weak hardcoded demo password

`ensureUsers()` sets `'pass' => 'password'` for all seeded accounts. Combined with H1,
seeded users are broadly privileged with a trivial password.

**Fix:** Generate a random password (print once) or read from an env var; document as
demo-only. Never reuse this pattern outside local/DDEV.

### H3 — No ownership checks; `created_by` spoofable via JSON:API

`TicketAccessControlHandler` gates update/delete on permission only — no owner check. A
user with `edit support tickets` can modify any ticket. JSON:API `POST` can also set
`created_by` / `assigned_to` to arbitrary users; authorship is not forced to the current
user on create.

**Note:** Acceptable under Core “no agent vs viewer” if intentional — record that decision.
If not: force `created_by` on create; consider owner-or-permission checks for update.

---

## Medium

### M1 — Programmatic `save()` skips field/entity validation

Required fields and `allowed_values` are enforced when Form API / JSON:API call
`validate()`. Seed (and any future service code) calls `$entity->save()` without
`validate()`, so invalid programmatic data can persist.

**Fix:** Call `$entity->validate()` before `save()` in seed (and any non-form writers).

### M2 — Over-broad `admin_permission`

Both entities use `admin_permission = "administer site configuration"`, tying entity admin
bypass to a generic site permission.

**Fix:** Dedicated `administer support tickets` permission, or remove if unused.

### M3 — Missing null guards; fragile embedded edit form

- `template_preprocess_support_ticket()` assumes `#support_ticket` is always set — a
  malformed render array fatals.
- Canonical view embeds the full edit form (`entity.form_builder->getForm($entity, 'edit')`),
  which can cause duplicate field UX and form-state edge cases.

**Fix:** Guard with `instanceof TicketInterface`; consider a lighter status/update panel
instead of the full edit form on detail (optional polish).

---

## Low

### L1 — Hardcoded defaults / duplicated constants

Priority default is the literal `'medium'` rather than a named constant. Statuses and
priorities are PHP consts reused in entity, Views form alter, and seed — fine for Core,
but label/value drift is possible if one site is updated and others are not. Documenting
“consts are the source of truth (not config entities)” is enough for Core.

### L2 — Manual HTML in Views field preprocess

`support_tickets_preprocess_views_view_field()` builds badge/link markup with
`Markup::create()` + `htmlspecialchars()`. Correctly escaped (no XSS found), but a Twig
partial would be more idiomatic.

### L3 — Comment `label()` returns message substring

`TicketComment::label()` can return raw message text. Current templates escape it; keep
an eye on any unescaped usage.

### L4 — No `changed` on comments

Intentional (append-only). Confirm in review notes as by design.

---

## Summary

| Sev | ID | Issue |
|-----|-----|--------|
| Critical | C1 | State machine unenforced on all write paths |
| High | H1 | Seed grants edit+delete to all authenticated users |
| High | H2 | Weak hardcoded demo password |
| High | H3 | No ownership checks; `created_by` spoofable via API |
| Medium | M1 | Programmatic `save()` skips validation |
| Medium | M2 | Over-broad `admin_permission` |
| Medium | M3 | Missing null guards; embedded edit form fragility |
| Low | L1–L4 | Hardcoding, manual markup, label/escaping, by-design omissions |

---

## Recommended fix order

1. **C1** — State machine service + constraint (Core judgment piece; unblocks tests).
2. **H1 / H2** — Seed permission and password hygiene.
3. **H3** — Document Core decision or force owner on create.
4. **M1–M3** — Validation on seed, admin permission, preprocess guards.
5. **L\*** — As time allows; not blockers for Core submission.

---

## What was reviewed / not found

- **SQL injection:** No raw queries; Entity API / Views only.
- **XSS in badges/links:** `htmlspecialchars` used in Views preprocess.
- **Secrets in module code:** No API keys; only demo password in seed (H2).
- **Custom REST bypass:** None — JSON:API only; risk is missing constraint (C1), not a
  parallel controller.

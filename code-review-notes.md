# Code review notes — Support Tickets module

Review target: `web/modules/custom/support_tickets/`  
Focus: unvalidated input reaching the DB, missing error handling, state-machine bypass
(including direct API writes), hardcoded values that belong in config, and leftover debug
code. Reflects the code **as it stands now** (state machine service + constraint are wired).

---

## AI-Assisted Review Summary

### State machine — is it bypassable?

The transition rule is enforced as an **entity constraint** (`TicketStatusTransition` on the
`support_ticket` entity type) backed by `TicketStatusTransitionValidator`. Because it runs
during `$entity->validate()`, both **Form API** (`TicketForm`) and **JSON:API** PATCH share
it — the classic form-only bypass is closed. New-ticket initial-status (`open`) and
same-status no-op are handled. That is the correct Drupal-idiomatic design.

Residual gaps below (M1, M2) are where it can still be skipped.

### Findings by severity

#### High

- **H1 — Seed widens the site-wide permission model (incl. delete) for all authenticated users.**  
  `SupportTicketsCommands::seed()` calls
  `user_role_grant_permissions('authenticated', [... 'delete support tickets' ...])`
  (`src/Commands/SupportTicketsCommands.php`, ~line 44–51). Any logged-in user can then edit
  and delete **any** ticket. A demo seeder should not silently broaden authorization.  
  *Fix:* grant demo perms in an install hook or documented manual step; do not grant
  `delete` to `authenticated` by default.

- **H2 — Weak hardcoded demo password.**  
  `ensureUsers()` sets `'pass' => 'password'` for every seeded account
  (`SupportTicketsCommands.php`, ~line 190). Combined with H1, broadly-privileged accounts
  share a trivial password.  
  *Fix:* generate a random password (print once) or read from an env var; document as
  local/DDEV-only.

- **H3 — No ownership checks; `created_by` spoofable via JSON:API.**  
  `TicketAccessControlHandler` gates update/delete on permission only, with no owner check,
  so anyone with `edit support tickets` can modify any ticket. JSON:API `POST` can also set
  `created_by` / `assigned_to` to arbitrary users (form display is disabled, but the API
  attribute/relationship is not forced to the current user).  
  *Note:* acceptable under Core "no agent vs viewer" **if intentional** — record the
  decision. Otherwise force `created_by` = current user on create and add owner-or-permission
  checks on update.

#### Medium

- **M1 — Direct `$entity->save()` bypasses the state machine (unvalidated input reaches DB).**  
  Drupal does not auto-run entity validation on `save()`. The constraint only fires when a
  caller invokes `validate()` (Form API and JSON:API do; the seed correctly uses
  `saveValidated()`). Any future service/hook that calls `$ticket->save()` directly will
  skip both the transition rule **and** required/allowed-values field validation, persisting
  invalid data.  
  *Fix:* keep a single validated save path (e.g. reuse `saveValidated()` / a `preSave()`
  guard that calls the transition service) for all programmatic writers, and document the
  expectation.

- **M2 — Unresolvable original status silently allows the change.**  
  `TicketStatusTransitionConstraintValidator::resolveOriginalStatus()` returns `NULL` when
  neither `$entity->original` nor `loadUnchanged()` yields a ticket, and `validate()` then
  returns without a violation (`...ConstraintValidator.php`, ~line 60–64). This is a
  defensive no-op, but it means an edge case where the prior status can't be loaded is
  treated as "allowed" rather than "reject / re-check."  
  *Fix:* log and/or fail closed when the original status genuinely cannot be resolved for a
  non-new entity.

- **M3 — Over-broad `admin_permission`.**  
  Both entities set `admin_permission = "administer site configuration"`
  (`Entity/Ticket.php` ~line 42, `Entity/TicketComment.php` ~line 44), tying entity admin
  bypass to a generic site permission.  
  *Fix:* dedicated `administer support tickets` permission, or drop it if unused.

- **M4 — Missing null guard in ticket preprocess (error handling).**  
  `template_preprocess_support_ticket()` reads
  `$variables['elements']['#support_ticket']` with no `isset` / `instanceof` guard
  (`support_tickets.module` ~line 153). A malformed render array fatals instead of degrading.  
  *Fix:* guard with `instanceof TicketInterface` and bail early.

#### Low

- **L1 — Hardcoded values that could be constants/config.**  
  Priority default is the literal `'medium'` in `Ticket::baseFieldDefinitions()`
  (`Entity/Ticket.php` ~line 162) rather than a named constant like the `STATUS_OPEN`
  pattern; seed hardcodes the `@example.com` mail domain. Statuses/priorities are PHP consts
  reused across entity, module form-alter, and seed — acceptable as the single source of
  truth for Core, but note the drift risk.  
  *Fix:* add a `PRIORITY_MEDIUM` constant; treat consts as the documented source of truth.

- **L2 — Manual HTML built in Views field preprocess.**  
  `support_tickets_preprocess_views_view_field()` composes badge/link markup with
  `Markup::create()` + `htmlspecialchars()` (`support_tickets.module` ~line 74–115).
  Correctly escaped (no XSS found), but a Twig partial would be more idiomatic.

- **L3 — Leftover scaffolding comments (not debug code).**  
  No `var_dump` / `dpm` / `error_log` / `console.log` / `die()` were found. However several
  stale "will be added in a later step" comments remain:
  - `TicketListBuilder` docblock: "Temporary list builder … (Step 4)".
  - `TicketCommentForm` docblock: "Ticket-detail comment UX lands in the Forms + Views step."
  - `support_tickets.routing.yml` contains only placeholder comments (no routes).
  - `support_tickets_install()` is an empty body with a comment.  
  *Fix:* update/remove now-inaccurate comments so they don't mislead reviewers.

- **L4 — `TicketComment::label()` returns a message substring.**  
  Current templates escape it; keep an eye on any future unescaped usage.

### Severity table

| Sev | ID | Issue |
|-----|----|-------|
| High | H1 | Seed grants edit+delete to all authenticated users |
| High | H2 | Weak hardcoded demo password (`password`) |
| High | H3 | No ownership checks; `created_by` spoofable via JSON:API |
| Medium | M1 | Direct `$entity->save()` skips validation / state machine |
| Medium | M2 | Unresolvable original status silently allows the change |
| Medium | M3 | Over-broad `admin_permission` |
| Medium | M4 | Missing null guard in `template_preprocess_support_ticket()` |
| Low | L1 | Hardcoded `'medium'` / mail domain; const drift risk |
| Low | L2 | Manual HTML in Views preprocess (escaped, but not idiomatic) |
| Low | L3 | Leftover scaffolding comments (no actual debug code) |
| Low | L4 | Comment `label()` returns raw message substring |

### What was checked and looked OK

- **State-machine sharing:** constraint runs on `validate()`, so Form API + JSON:API PATCH
  are both covered (illegal transition → validation error / `422`).
- **SQL injection:** none — Entity API / Views / entity query only, no raw SQL.
- **XSS:** Views badge/link markup and Twig output are escaped.
- **Secrets:** no API keys/tokens in module code (only the demo password, H2).
- **Debug code:** none found (`var_dump`/`dpm`/`error_log`/`die`/`console.log` all absent).

### Suggested fix order

1. H1 / H2 — seed permission + password hygiene.
2. H3 — document the Core decision or force owner on create.
3. M1–M4 — single validated save path, fail-closed on unresolved status, scoped admin
   permission, preprocess guard.
4. L1–L4 — as time allows; not Core blockers.

---

## My Review Observations

_(Reserved for reviewer — add your own findings here.)_

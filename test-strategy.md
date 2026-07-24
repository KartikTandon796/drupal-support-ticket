# Test strategy — Support Tickets (Core)

This module’s automated tests focus on the **status state machine** — the Core judgment
piece — at both the **entity validation** layer and the **JSON:API** layer. Everything else
is covered by manual smoke checks or left to Stretch.

Related Core vs Stretch rules: `.cursor/rules/support-tickets-stack.mdc`, `TASKS.md`,
exercise brief (mandatory PHPUnit for valid + invalid transitions; API rejection if an API
is used).

---

## Goals

1. Prove every **allowed** status edge can succeed via `$entity->validate()` / `save()`.
2. Prove **illegal** transitions fail with a **clear validation message** (not a generic
   exception / HTTP 500).
3. Prove JSON:API **PATCH** uses the **same** constraint (invalid → `422`; valid → `200`).
4. Stay inside an **8–12 hour Core** budget — no exhaustive matrix of auth, CRUD, or Views.

---

## What we test

### Entity layer — Kernel

**File:** `tests/src/Kernel/TicketStatusTransitionTest.php`  
**Base:** `KernelTestBase` (entity schemas only; no full browser)

| Case | Why |
|------|-----|
| `open → in_progress` | Happy path edge |
| `open → cancelled` | Alternate open exit |
| `in_progress → resolved` | Forward progress |
| `in_progress → cancelled` | Cancel from in progress |
| `resolved → closed` | Terminal close path |
| Same-status no-op (`open → open`) | Explicitly allowed by the service map |
| New ticket with non-`open` status | Initial-status rule in the constraint |
| `open → resolved` | Invalid skip of `in_progress` |
| `closed → open` | Terminal reopen blocked |
| `cancelled → in_progress` | Terminal exit blocked |

Assertions: violation count, message contains transition language, and DB status unchanged
after a rejected validate (we do not call `save()` on invalid).

### JSON:API layer — Functional

**File:** `tests/src/Functional/TicketStatusTransitionJsonApiTest.php`  
**Base:** `BrowserTestBase` + `JsonApiRequestTestTrait`  
**Auth:** Basic Auth as a user with ticket permissions; `jsonapi.settings:read_only` set to
`false` in `setUp()` so writes are allowed.

| Case | Expectation |
|------|-------------|
| `PATCH` `open → resolved` | **422**, error detail mentions transition, status stays `open` |
| `PATCH` `open → in_progress` | **200**, status becomes `in_progress` |

This is the minimum to show the API is not a bypass around form-only checks — the same
`TicketStatusTransition` entity constraint runs on JSON:API saves.

### How to run

```bash
# Kernel
ddev exec bash -c 'export SIMPLETEST_DB="sqlite://localhost//tmp/st-test.sqlite"; cd /var/www/html/web/core && /var/www/html/vendor/bin/phpunit --configuration=/var/www/html/web/core/phpunit.xml.dist /var/www/html/web/modules/custom/support_tickets/tests/src/Kernel/TicketStatusTransitionTest.php'

# JSON:API
ddev exec bash -c 'export SIMPLETEST_DB="mysql://db:db@db/db"; export SIMPLETEST_BASE_URL="http://support-tickets.ddev.site"; cd /var/www/html/web/core && /var/www/html/vendor/bin/phpunit --configuration=/var/www/html/web/core/phpunit.xml.dist /var/www/html/web/modules/custom/support_tickets/tests/src/Functional/TicketStatusTransitionJsonApiTest.php'
```

Requires `drupal/core-dev` (PHPUnit) via Composer.

---

## What we deliberately do **not** test (Core)

| Out of scope | Why (Core vs Stretch) |
|--------------|------------------------|
| **Auth edge cases** (anonymous vs role matrix, Basic Auth failure modes, CSRF, session expiry, permission combinations per operation) | Core uses Drupal’s default user system + a small custom permission set. Agent vs viewer ACLs and fine-grained auth testing are **Stretch**. We only need a permitted user for the API write smoke tests. |
| **Full CRUD permutations** (every field combo for create/update/delete on tickets and comments; comment edit/delete; assignee spoofing; ownership rules) | Core requires create/list/detail/update/comment and field requiredness via the Entity API — not an exhaustive combinatorial suite. Delete and comment edit are secondary routes, not the graded state-machine story. |
| **Pagination / sort / Views filter matrices** | Core allows default Views / JSON:API behavior. Custom pagination/sort beyond defaults is **Stretch**. Manual check of list + exposed filters is enough. |
| **Form UI browser tests** (Twig badges, empty state copy, embedded forms) | Presentational; covered by manual QA against `ui-flow.md`. Kernel/Functional focus stays on domain rules. |
| **Seed command / Drush** | Demo convenience, not product correctness under test. |
| **JSON:API read-only config, filtering, includes, sparse fieldsets** | Core uses stock JSON:API; documenting read-only in `api-contract.md` is enough. |
| **Performance, concurrency, migration upgrades** | Outside Core exercise scope. |
| **Docker/CI pipeline greenness** | Explicit Stretch exclusion; tests are runnable locally via DDEV/phpunit. |

---

## Why this boundary

| Core mandate | How tests map |
|--------------|---------------|
| State machine enforced server-side (not form-only) | Kernel `validate()` + JSON:API PATCH both hit the same constraint |
| Valid transitions succeed; ≥2–3 invalid rejected with clear errors | Five valid edges + three invalid + initial-status + no-op in Kernel |
| Real API layer | Functional JSON:API test, not a mocked controller |
| No Stretch ACL / pagination / CI | Those suites omitted on purpose |

Investing more suite surface area without expanding Core features would burn the
timebox on low-signal coverage (auth matrices, Views paging) while the graded risk —
**illegal status changes via API** — is already pinned down.

---

## Manual / complementary checks (not automated in Core)

- Enable module + seed; browse `/support-tickets`, filter/search, open detail, add comment.
- Confirm form status select only offers legal targets; illegal save shows a form error.
- Optional: curl examples in README / `api-contract.md` against DDEV.

---

## Future Stretch test ideas (do not implement for Core)

- Permission matrix (viewer cannot PATCH; agent can).
- Comment CRUD and ticket delete Functional coverage.
- Views exposed-filter / empty-state Functional assertions.
- CI job running PHPUnit on every PR.

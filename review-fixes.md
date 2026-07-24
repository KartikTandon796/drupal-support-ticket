# Review fixes — triage for Core scope

Decision triage for the findings in `code-review-notes.md`. Each item is marked **Fix**,
**Fix (optional)**, or **Accept as-is for Core**, with reasoning. This is a starting
recommendation — edit the verdicts you disagree with before acting.

Legend: 🔴 Fix now · 🟡 Fix if time · 🟢 Accept as-is (document the decision)

---

## Should fix

### H1 — Seed grants edit+delete to all authenticated users · 🔴 Fix
`seed()` calling `user_role_grant_permissions('authenticated', [... 'delete ...'])` changes
the site-wide authorization model as a side effect of loading demo data. That is surprising
and unsafe even for a demo, and it directly weakens the permission story the module is
supposed to demonstrate. Low effort to fix.  
*Recommended:* move perm grants to a documented manual step or install hook; at minimum drop
`delete support tickets` from the authenticated grant.

### H2 — Weak hardcoded demo password · 🔴 Fix (cheap) / 🟡 acceptable if clearly scoped
`'pass' => 'password'` is fine *only* if it can never leave local/DDEV. Because it pairs with
H1 (broadly-privileged accounts), it is worth changing. Cheapest safe version: generate a
random password and print it once from the Drush command.  
*Reasoning:* keeps the "seed = local demo convenience" framing without shipping a trivially
guessable, broadly-privileged login. If you prefer to keep `password` for grader
convenience, that's defensible — but then it must be explicitly labeled demo-only in README.

### M1 — Direct `$entity->save()` bypasses validation / state machine · 🔴 Fix (guard)
This is the one real remaining way to bypass the state machine. Form API and JSON:API are
covered, and the seed already uses `saveValidated()`, so nothing is broken today — but the
protection depends on every future caller remembering to validate. A `preSave()` (or a
single shared validated-save helper) that runs the transition check makes the rule
save-path-independent and matches the spec's "enforce in service + constraint, not
form-only" intent.  
*Reasoning:* small, high-value hardening that closes the last conceptual bypass; also
protects required/allowed-values fields on programmatic writes.  
*Caveat:* if you add a `preSave()` guard, make it throw only for genuinely invalid data so
you don't double-report alongside the constraint on the form path.

### M4 — Missing null guard in `template_preprocess_support_ticket()` · 🔴 Fix (trivial)
One-line `instanceof TicketInterface` guard prevents a fatal on a malformed render array.
Trivial, pure robustness, no scope expansion.

---

## Fix if time (not Core blockers)

### M2 — Unresolvable original status silently allows the change · 🟡 Fix if time
The `resolveOriginalStatus() === NULL → allow` branch is a defensive no-op that, in theory,
lets an edge case through. In practice `$entity->original` / `loadUnchanged()` almost always
resolves for a non-new entity, so real-world risk is low. Worth a log + fail-closed if you're
already touching the validator; otherwise fine to defer.

### M3 — Over-broad `admin_permission` · 🟡 Fix if time
`administer site configuration` as the entity admin permission is broader than ideal, but it
only affects users who already hold a very powerful core permission, so it is not a
meaningful privilege-escalation path. A dedicated `administer support tickets` permission is
cleaner; low urgency.

### L3 — Leftover scaffolding comments · 🟡 Fix if time
Not debug code and no runtime impact, but "Temporary… (Step 4)" / "will be added in the
Forms + Views step" comments are now inaccurate and can mislead a reviewer. Cheap to tidy;
purely cosmetic.

---

## Acceptable to leave as-is for Core (document the decision)

### H3 — No ownership checks; `created_by` spoofable via JSON:API · 🟢 Accept (with a written decision)
This is the judgment call. The workspace Core rules explicitly put "agent vs viewer roles /
fine-grained ACLs beyond the module's custom permissions" and "ownership rules" out of Core
scope. Core uses a flat custom-permission model, so "any user with `edit support tickets` can
edit any ticket" is the *intended* Core behavior, not a bug.  
*Condition for accepting:* record this explicitly (README / spec / here) as a deliberate Core
decision. If you'd rather harden cheaply without entering Stretch, forcing `created_by` to the
current user on create is a small, reasonable addition — but full owner-based ACLs are
Stretch and should stay out.

### L1 — Hardcoded `'medium'` / mail domain; const drift risk · 🟢 Accept
Statuses/priorities live as PHP consts used as the single source of truth; a literal default
of `'medium'` and an `@example.com` seed domain are harmless. Adding a `PRIORITY_MEDIUM`
constant is nice-to-have, not required. No config entity is warranted for Core (contrib
workflow/config is explicitly Stretch).

### L2 — Manual HTML in Views preprocess · 🟢 Accept
Markup is correctly escaped (`htmlspecialchars`), so there's no security issue. Refactoring to
a Twig partial is idiomatic polish, not a correctness fix — out of the "smallest change"
Core budget.

### L4 — `TicketComment::label()` returns message substring · 🟢 Accept
Templates escape the label and it's only used as a display label. No action needed beyond
keeping the note; changing it risks churn for no benefit.

---

## Summary table

| ID | Verdict | One-line reason |
|----|---------|-----------------|
| H1 | 🔴 Fix | Seed shouldn't silently grant delete to all authenticated users |
| H2 | 🔴 Fix (cheap) | Trivial password on broadly-privileged demo accounts |
| M1 | 🔴 Fix | Close the last state-machine bypass (programmatic save) |
| M4 | 🔴 Fix | One-line guard prevents a fatal |
| M2 | 🟡 If time | Low real-world risk; fail-closed if already editing validator |
| M3 | 🟡 If time | Only affects already-powerful admins |
| L3 | 🟡 If time | Stale comments mislead reviewers; cosmetic |
| H3 | 🟢 Accept | Flat permission model is intended Core scope — document it |
| L1 | 🟢 Accept | Consts are the source of truth; no config for Core |
| L2 | 🟢 Accept | Escaped output; refactor is polish only |
| L4 | 🟢 Accept | Escaped in templates; display-only |

**Net for Core:** fix H1, H2, M1, M4; optionally M2/M3/L3; consciously accept H3 (with a
recorded decision) plus L1/L2/L4.

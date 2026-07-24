# Debugging — Support Ticket System (Drupal)

## Issue 1: DDEV "could not find a project"
### Problem
Running `ddev start` from the wrong directory (~/Users/kartiktandon instead of the project
root) — DDEV requires being run from inside (or below) the folder containing .ddev/config.yaml.

### How I Investigated
Read the error message directly: "could not find a project ... no .ddev/config.yaml file
was found in this directory or any parent."

### How AI Helped
Confirmed the fix was simply `cd` into the project directory before running ddev commands.

### What I Validated
Ran `ls .ddev/config.yaml` inside the project folder to confirm the config existed before
retrying.

### Final Fix
cd into the project root, then `ddev start` succeeded.

---

## Issue 2: git push hanging silently
### Problem
`git push -u origin main` produced no output and appeared to hang indefinitely.

### How I Investigated
Ran with `GIT_CURL_VERBOSE=1` to see the actual HTTP exchange, which revealed an
HTTP/2 401 response with `www-authenticate: Basic realm="GitHub"` — the push wasn't
hanging, it was silently failing auth and re-prompting in a way that wasn't visible
in the terminal.

### How AI Helped
Identified that macOS Keychain likely had stale/cached credentials, suggested clearing
them with `git credential-osxkeychain erase` and retrying, or embedding a fresh token
directly in the remote URL as a fallback.

### What I Validated
Confirmed the correct target repo URL and username before retrying, generated a new
Personal Access Token after the old one had been accidentally exposed in chat and revoked
it immediately.

### Final Fix
Set the remote URL to the correct repo (https://github.com/KartikTandon796/drupal-support-ticket.git)
and authenticated with a fresh token.

---

## Issue 3: File/directory structure mismatches during submission scaffolding
### Problem
`cp .cursor/rules/*.mdc tool-specific/cursor-workflow/cursor-rules-or-instructions.md`
failed with "not a directory" (target was an existing file, not a folder), and a
subsequent `cat ... > ...` redirect failed with "No such file or directory" because the
target directory didn't exist yet.

### How I Investigated
Ran `ls -la` on the target paths to see what actually existed vs. what the scaffold
prompt was supposed to have created.

### How AI Helped
Explained the difference between copying into an existing file vs. a directory, and
that `mkdir -p` needed to run before the redirect would work.

### What I Validated
Confirmed with `ls -la tool-specific/cursor-workflow/` that the directory and file
existed correctly after the fix, and `cat`'d the result to check content.

### Final Fix
`mkdir -p tool-specific/cursor-workflow` followed by `cat .cursor/rules/*.mdc >
tool-specific/cursor-workflow/cursor-rules-or-instructions.md`.

## Issue 4: JSON:API returning empty results / looked like "no tickets" but was actually a permissions issue

### Problem
Querying the JSON:API tickets endpoint returned an empty `data` array with a
`meta.omitted` block, which initially looked like the seed data hadn't persisted or the
resource config was wrong — but was actually Drupal correctly hiding tickets the
requesting user didn't have permission to view.

### How I Investigated
Checked the `meta.omitted` detail in the JSON:API response rather than assuming the
empty array meant "no data" — that field explains why records were excluded.
Cross-checked by hitting the same endpoint as an authenticated user with the
`access support tickets` permission vs. an anonymous request.

### How AI Helped
Flagged in advance (during planning) that this exact symptom — empty data + meta.omitted
— is a known Drupal JSON:API gotcha that reads as "no data" but is almost always
permissions/auth, not missing records. Also flagged that authenticating via
`ddev exec` curl against the public HTTPS hostname often fails outright
(curl exit 7 / connection refused), and that testing should go through
`http://<project>.ddev.site` from the host machine, or `http://127.0.0.1:<port>`
from inside the container, not a mix of the two.

### What I Validated
Confirmed the ticket data actually existed in the database via `ddev drush sql-cli`
before trusting the "it's a permissions issue, not a data issue" diagnosis. Re-ran the
JSON:API request as a user with the correct permission and confirmed tickets appeared.

### Final Fix
Granted the `access support tickets` permission to the authenticated user role (or the
test user used for API checks), and standardized on the correct base URL for local
JSON:API testing so future 401/empty-result symptoms aren't misdiagnosed as data bugs.

---

## Issue 5: Invalid status transition attempted via JSON:API PATCH

### Problem
A direct JSON:API PATCH request setting `status` from `closed` to `open` needed to be
rejected exactly like the entity form does — this was the actual test of whether the
state machine constraint was truly shared, not just enforced in the form's
validateForm().

### How I Investigated
Sent a PATCH request with an invalid transition and checked whether the response was a
clean 422/validation error (constraint working) or a 200 (constraint bypassed via API).

### How AI Helped
Confirmed the constraint plugin approach (from the design.md decision) applies
automatically during entity validation regardless of the save path, so no separate
API-specific rejection logic should be needed — if the API had let it through, that
would point to the constraint not actually being wired into the JSON:API write path.

### What I Validated
Ran the PATCH request myself and confirmed the response was a rejection with a clear
error message, not a silent 200 — matching the same message format used on the entity
form.

### Final Fix
No code change needed once confirmed — this became the PHPUnit
"API-level rejection" test case (Prompt 2 in testing.md) rather than a bug fix, proving
the shared-constraint design decision actually held up under a real API call.
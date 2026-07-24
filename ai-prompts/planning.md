# Planning — Support Ticket System (Drupal)

## Prompt to Cursor
Given the tasks.md breakdown, what's the right order to build the entities vs. the state
machine vs. the UI, so I can test each piece incrementally in a running Drupal site rather
than building everything before testing anything? What are the risks in this plan — where
are we most likely to hit a Drupal-specific gotcha?

## Response

### Recommended build order (test after each slice)

Think **vertical slices**, not “all entities → all logic → all UI.”

| Step | Build | Smoke-test on a running site |
|------|--------|------------------------------|
| **1** | Module enable + permissions | `drush en support_tickets`; perms visible |
| **2** | Ticket entity only (fields, access, add/edit/canonical) | Create a ticket in UI or `drush php:eval`; row in DB |
| **3** | Comment entity + link to ticket | Save a comment; load by `ticket_id` |
| **4** | State machine (service + constraint) | Change status via form/`$entity->save()`; illegal transition fails with a clear message **before** Views polish |
| **5** | Forms polish (errors, comment on detail, hide `ticket_id`) | Happy path + one bad transition in the UI |
| **6** | Views list (path, columns, filters, empty text) | `/support-tickets` list/search/filter |
| **7** | JSON:API check (list/create/PATCH + bad status) | Same rules as entity layer |
| **8** | Seed → PHPUnit → README | Reproducible demo + automated proof |

**Entities before state machine:** you need a real `status` field and saves to hang validation on.  
**State machine before Views polish:** workflow is the hard judgment; prove it on the entity form first. Views is presentational and easy to thrash if you’re still changing fields.  
**Forms before Views:** create/edit/detail prove the domain; the list can stay `TicketListBuilder` for a day.  
**API after entity validation:** JSON:API should ride the same constraint — don’t duplicate rules in a controller.

You already have 1–3 and much of 5–6 in the repo; the next incremental win is **step 4 (state machine)**, then API rejection tests.

---

### Risks / Drupal gotchas (highest first)

1. **Views path vs entity `collection` route**  
   Both want `/support-tickets`. One wins; the other 404s or shows the wrong page.  
   *Mitigation:* Views owns `/support-tickets`; entity collection → e.g. `/admin/content/support-tickets`.

2. **`list_field` Views filter on base fields**  
   Base `list_string` often registers as a **string** filter; forcing `list_field` can fatal (`FieldStorageDefinitionInterface` null).  
   *Mitigation:* string/`=` filter + exposed form alter to a select (what we did), or a tiny custom filter plugin.

3. **Config install vs already-enabled module**  
   New `config/install/*.yml` (Views) does **not** import on `drush cr`.  
   *Mitigation:* `hook_update_N`, reinstall, or partial config import; always verify active config, not just the file in git.

4. **State machine placement**  
   Form-only checks are bypassed by JSON:API/`$entity->save()`. `preSave()` throwing is awkward for forms.  
   *Mitigation:* Constraint + service; assert via `$entity->validate()` and API PATCH.

5. **`status` naming**  
   Easy to confuse with Entity Published `status`. Keep workflow off `entity_keys` published/status.  
   *Mitigation:* document machine names; never reuse Node’s published pattern.

6. **Comment entity id**  
   Type id `comment` collides with core Comment.  
   *Mitigation:* keep `support_ticket_comment`.

7. **Owner / timestamps**  
   `getCreatedTime()` isn’t free on every content entity; comments without the trait need `$entity->get('created')->value`.

8. **JSON:API access**  
   Empty `data` + `meta.omitted` looks like “no tickets” but is usually permissions/auth. Basic auth from `ddev exec` to the public HTTPS hostname often fails (curl exit 7).  
   *Mitigation:* grant perms; curl from host over `http://…ddev.site` or `ddev exec` → `http://127.0.0.1`.

9. **Embedding entity forms on detail**  
   Full edit form under the card can confuse UX (duplicate fields) and form IDs; still fine for Core if labeled “Update ticket.”

10. **Text formats / `string_long` vs `text_long`**  
    `text_long` pulls in filter formats for forms and JSON:API. Prefer `string_long` for Core unless you need rich text.

---

**Practical rhythm:** after each step, one `drush cr` + one browser or curl check before starting the next. Don’t wait for “whole module green.”

## What I kept / changed
- Adopted the vertical-slice order as-is (module -> entity -> comment -> state machine ->
  forms polish -> Views -> JSON:API -> seed/tests/README).
- Confirmed state-machine-before-Views-polish sequencing matches our earlier decision
  (service + constraint shared by forms and API).

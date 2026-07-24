# UI flow — Support Tickets module

Based on routes registered in the running site and the forms/templates in
`web/modules/custom/support_tickets/`. Requires an authenticated user with the module
permissions (e.g. after `drush support_tickets:seed`, or admin).

```
/support-tickets  (Views list)
       │
       ├─ New ticket ──────────────► /support-tickets/add
       │                                    │
       │                                    └─ save ──► /support-tickets/{id}
       │
       ├─ click title ─────────────► /support-tickets/{id}  (detail)
       │                                    │
       │                                    ├─ Update ticket (embedded edit form)
       │                                    ├─ Add a comment (embedded comment form)
       │                                    └─ Edit link ──► /support-tickets/{id}/edit
       │
       └─ row Edit link ───────────► /support-tickets/{id}/edit
```

---

## 1. Ticket list (search + filter)

| | |
|--|--|
| **Route name** | `view.support_tickets.page_list` |
| **Path** | `/support-tickets` |
| **Implementation** | Views display `support_tickets` / `page_list` (`config/install/views.view.support_tickets.yml`) |
| **Permission** | `access support tickets` |

**What the user sees**

- Page title **Support tickets**
- Toolbar: exposed filter form + **New ticket** button (if user has `create support tickets`)
- Exposed filters (inline):
  - **Status** — select (`- Any -`, Open, In Progress, Resolved, Closed, Cancelled); query param `status`
  - **Search** — text field (placeholder “Keyword…”); searches title + description; query param `search`
  - **Filter** / **Reset** buttons
- Table columns: Title (link), Status (badge), Priority (badge), Assignee, Updated, Edit
- Sortable headers: Title, Status, Priority, Updated
- Empty results: “No tickets match your search.”

**What the user does**

1. Open `/support-tickets` (also linked from the main menu as “Support tickets”).
2. Optionally set Status and/or Search, click **Filter**.
3. Click a **title** to open detail, **Edit** for the edit form, or **New ticket** to create.

**Note:** Entity collection `entity.support_ticket.collection` is a separate admin list at
`/admin/content/support-tickets` (`TicketListBuilder`). The primary UI list is the Views
page above.

---

## 2. Ticket detail

| | |
|--|--|
| **Route name** | `entity.support_ticket.canonical` |
| **Path** | `/support-tickets/{support_ticket}` |
| **Implementation** | Entity view builder + `templates/support-ticket.html.twig` + `support_tickets_support_ticket_view()` |
| **Permission** | `access support tickets` (view); update/comment panels need extra perms |

**What the user sees**

1. **← All tickets** link back to `/support-tickets`
2. **Read-only card:** title, status + priority badges, description, meta (assignee, created by, created, updated)
3. **Comments** panel — chronological list (oldest first): author, timestamp, message; or “No comments yet.”
4. **Update ticket** panel (if `edit support tickets`) — full `TicketForm` embedded (`entity.form_builder->getForm($entity, 'edit')`)
5. **Add a comment** panel (if `add support ticket comments`) — `TicketCommentForm` with `ticket_id` preset and hidden

**What the user does**

- Read the ticket and existing comments.
- Change fields/status in **Update ticket** and save (stays in the workflow; illegal transitions are rejected with a validation error).
- Type a message under **Add a comment** and save — redirects back to this same detail page.

---

## 3. Create ticket form

| | |
|--|--|
| **Route name** | `entity.support_ticket.add_form` |
| **Path** | `/support-tickets/add` |
| **Form** | `Drupal\support_tickets\Form\TicketForm` (`add` / `default` handler) |
| **Permission** | `create support tickets` |

**What the user sees**

Entity form fields from base-field display options:

- Title (required)
- Description (required)
- Priority (select; default medium)
- Status (select; limited to allowed targets for a new ticket — effectively **Open**)
- Assigned to (optional user autocomplete)

**What the user does**

1. From the list, click **New ticket** (or go to `/support-tickets/add`).
2. Fill required fields; leave status as Open (required by state machine for creates).
3. Save → messenger success → redirect to **`/support-tickets/{id}`** (canonical).

---

## 4. Edit ticket form

| | |
|--|--|
| **Route name** | `entity.support_ticket.edit_form` |
| **Path** | `/support-tickets/{support_ticket}/edit` |
| **Form** | `Drupal\support_tickets\Form\TicketForm` (`edit` handler) |
| **Permission** | `edit support tickets` |

**What the user sees**

Same fields as create. Status select options are **restricted to legal transitions** from the
current status (`TicketForm::form()` + `TicketStatusTransitionValidator::getAllowedTargets()`).

Also reachable via:

- List row **Edit** link
- Detail page **Update ticket** panel (same form class, embedded on canonical)

**What the user does**

1. Open `/support-tickets/{id}/edit` (or use the embedded panel on detail).
2. Change title, description, priority, assignee, and/or status within allowed options.
3. Save → success message → redirect to **`/support-tickets/{id}`**.

Invalid status (e.g. if forced via API or a stale form) fails entity validation with a
user-facing transition error — not a raw exception.

**Related:** delete is available at `/support-tickets/{support_ticket}/delete`
(`entity.support_ticket.delete_form`, `ContentEntityDeleteForm`) but is not part of the
primary Core flow above.

---

## 5. Add comment flow

### Primary (on ticket detail) — preferred Core UX

| | |
|--|--|
| **Host route** | `entity.support_ticket.canonical` → `/support-tickets/{support_ticket}` |
| **Form** | `Drupal\support_tickets\Form\TicketCommentForm` (`add`) |
| **Permission** | `add support ticket comments` |

**What the user sees**

Panel **Add a comment** with a **Message** textarea. Parent ticket field (`ticket_id`) is
hidden because it was set when building the form
(`TicketComment::create(['ticket_id' => $entity->id()])` + `#access` FALSE in the form).

**What the user does**

1. Open a ticket detail page.
2. Enter a message; submit.
3. Success message “Comment has been saved.” → redirect to the **same ticket detail** URL.
4. New comment appears at the **bottom** of the comments list (sorted by `created` ASC).

### Standalone add form (also registered)

| | |
|--|--|
| **Route name** | `entity.support_ticket_comment.add_form` |
| **Path** | `/support-tickets/comment/add` |
| **Form** | `TicketCommentForm` |

**What the user sees**

Same form, but **Ticket** autocomplete is visible (must pick a parent ticket) unless
`ticket_id` was already set on the entity.

**What the user does**

Choose ticket + message, save → redirect to that ticket’s canonical page (or admin
collection if no ticket id).

Comment edit/delete routes exist (`/support-tickets/comment/{id}/edit|delete`) but Core UI
treats comments as append-oriented; the main flow is create-from-detail.

---

## Route cheat sheet

| Step | Path | Route name |
|------|------|------------|
| List + search/filter | `/support-tickets` | `view.support_tickets.page_list` |
| Detail | `/support-tickets/{support_ticket}` | `entity.support_ticket.canonical` |
| Create | `/support-tickets/add` | `entity.support_ticket.add_form` |
| Edit | `/support-tickets/{support_ticket}/edit` | `entity.support_ticket.edit_form` |
| Add comment (standalone) | `/support-tickets/comment/add` | `entity.support_ticket_comment.add_form` |
| Admin entity list (secondary) | `/admin/content/support-tickets` | `entity.support_ticket.collection` |

HTML entity routes come from `DefaultHtmlRouteProvider` on the entity annotations; the list
page comes from Views. `support_tickets.routing.yml` does not define these paths itself.

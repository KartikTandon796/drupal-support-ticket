Create the file ai-prompts/documentation.md with the following content — write it exactly
as given, don't regenerate or paraphrase it:

# Documentation — Support Ticket System (Drupal)

## Prompt
Write the README setup section: composer requires, drush commands to enable the module and
run migrations/seed data, and how to log in and see seeded tickets. Assume a reader with a
fresh Drupal install and no context on this module.

## Response

[paste the actual README.md content you just gave me here, in full]

## What I kept / changed
- Kept the DDEV path as primary, non-DDEV as secondary, matching our earlier decision to
  make DDEV the recommended setup.
- Corrected a potential point of confusion: since the exercise guide expects
  "migration scripts," added an explicit note clarifying this module uses Drupal's entity
  schema installation (not the Migrate API) so a reviewer doesn't go looking for a
  migration that doesn't exist.
- Verified the full DDEV setup sequence end-to-end on a clean checkout before accepting
  the README as accurate (see debugging-notes.md for the DDEV path issue hit during this
  verification).
- Added both PHPUnit test-run commands (Kernel + Functional/JSON:API) with the exact
  environment variables needed, since these aren't obvious defaults in a Drupal project.
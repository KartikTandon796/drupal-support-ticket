# Support Ticket Management System

Internal **Core-tier** support ticket app built as a Drupal 10 custom module
(`support_tickets`). Tickets and comments are content entities; the UI is Form API +
Views (no separate JS frontend); the API is core JSON:API.

Module path: `web/modules/custom/support_tickets/`

---

## What you need

- PHP 8.1–8.3, Composer 2, and a database (MariaDB/MySQL/SQLite), **or**
- [DDEV](https://ddev.readthedocs.io/) (recommended — includes PHP, DB, and web server)

This repository is a full Composer Drupal project (`drupal/recommended-project` layout).
Docroot is `web/`.

---

## Composer dependencies

From the project root, install PHP dependencies:

```bash
composer install
# or with DDEV:
ddev composer install
```

`composer.json` already requires:

| Package | Purpose |
|---------|---------|
| `drupal/core-recommended` (^10.6) | Drupal core |
| `drush/drush` (^12) | Site install, module enable, seed |
| `drupal/core-composer-scaffold` | Places files under `web/` |

There is **no** Packagist package for this feature — the custom module ships in-tree at
`web/modules/custom/support_tickets/`. Enabling it (below) also pulls in its Drupal
module dependencies (`user`, `views`, `jsonapi`, `serialization`, `options`).

Optional for running PHPUnit:

```bash
composer require --dev drupal/core-dev:^10.6 --with-all-dependencies
```

---

## Setup (DDEV — primary)

```bash
git clone <repo-url> support-tickets
cd support-tickets

ddev start
ddev composer install

# Fresh Drupal site (Standard profile)
ddev drush site:install standard \
  --site-name="Support Tickets" \
  --account-name=admin \
  --account-pass=admin \
  -y

# Enable the module (creates entity tables — there is no Migrate migration to run)
ddev drush en support_tickets -y

# Load demo users + tickets in various statuses
ddev drush support_tickets:seed

# Open the ticket list in the browser
ddev launch /support-tickets
```

Site URL (also from `ddev describe`): `https://support-tickets.ddev.site`

---

## Setup without DDEV (secondary)

```bash
git clone <repo-url> support-tickets
cd support-tickets
composer install

# SQLite example (no separate DB server):
./vendor/bin/drush site:install standard \
  --db-url="sqlite://localhost/web/sites/default/files/.ht.sqlite" \
  --site-name="Support Tickets" \
  --account-name=admin \
  --account-pass=admin \
  -y

# Or MySQL/MariaDB:
# ./vendor/bin/drush site:install standard \
#   --db-url="mysql://USER:PASS@127.0.0.1:3306/DBNAME" \
#   --site-name="Support Tickets" \
#   --account-name=admin \
#   --account-pass=admin \
#   -y

./vendor/bin/drush en support_tickets -y
./vendor/bin/drush support_tickets:seed

./vendor/bin/drush runserver
# Then open http://127.0.0.1:8888/support-tickets
```

---

## Log in and see seeded tickets

1. Open the site (DDEV: `https://support-tickets.ddev.site` or `ddev launch`).
2. Go to `/user/login` (or use a one-time link: `ddev drush uli` / `./vendor/bin/drush uli`).
3. Sign in with either:
   - **Admin:** `admin` / `admin` (from `site:install` above), or
   - **Demo user:** `agent.alice` / `password` (created by the seed command; also
     `agent.bob`, `reporter.cara` — same password).
4. Visit **`/support-tickets`**.

You should see a table of demo tickets (open, in progress, resolved, closed, cancelled),
with status/priority badges, search, and a status filter. Click a title to open the detail
page (comments + update form).

**Note on “migrations”:** This module does **not** use the Drupal Migrate API. Enabling
`support_tickets` installs the entity schema (`support_ticket`, `support_ticket_comment`
tables). Demo content comes only from `drush support_tickets:seed` (alias: `st-seed`).

---

## Useful Drush commands

```bash
# DDEV
ddev drush en support_tickets -y
ddev drush support_tickets:seed
ddev drush cr
ddev drush uli

# Without DDEV
./vendor/bin/drush en support_tickets -y
./vendor/bin/drush support_tickets:seed
./vendor/bin/drush cr
./vendor/bin/drush uli
```

---

## Tests (optional)

```bash
# Kernel state-machine tests
ddev exec bash -c 'export SIMPLETEST_DB="sqlite://localhost//tmp/st-test.sqlite"; cd /var/www/html/web/core && /var/www/html/vendor/bin/phpunit --configuration=/var/www/html/web/core/phpunit.xml.dist /var/www/html/web/modules/custom/support_tickets/tests/src/Kernel/TicketStatusTransitionTest.php'

# JSON:API transition tests (needs SIMPLETEST_BASE_URL)
ddev exec bash -c 'export SIMPLETEST_DB="mysql://db:db@db/db"; export SIMPLETEST_BASE_URL="http://support-tickets.ddev.site"; cd /var/www/html/web/core && /var/www/html/vendor/bin/phpunit --configuration=/var/www/html/web/core/phpunit.xml.dist /var/www/html/web/modules/custom/support_tickets/tests/src/Functional/TicketStatusTransitionJsonApiTest.php'
```

---

## More documentation

- [`spec.md`](spec.md) — domain model and API surface
- [`TASKS.md`](TASKS.md) — build checklist
- [`code-review.md`](code-review.md) — review findings

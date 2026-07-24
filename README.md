# Support Ticket Management System

Drupal 10 Core-tier support tickets module (`support_tickets`) with Form API / entity UI and JSON:API.

## Requirements

- [DDEV](https://ddev.readthedocs.io/) (recommended), **or** PHP 8.1–8.3, Composer 2, a database, and Drush
- Docker (required by DDEV)

Project layout matches `drupal/recommended-project`: Composer root at the repo root, Drupal docroot at `web/`. DDEV `docroot` is set to `web` to match `composer.json` → `extra.drupal-scaffold.locations.web-root`.

Database: **MariaDB 10.11** via DDEV (default choice — no reason to prefer MySQL or Postgres for this project).

---

## Setup with DDEV (primary)

From a fresh clone:

```bash
git clone <repo-url> support-tickets
cd support-tickets

ddev start
ddev composer install

ddev drush site:install standard \
  --site-name="Support Tickets" \
  --account-name=admin \
  --account-pass=admin \
  -y

ddev drush en support_tickets -y
ddev drush support_tickets:seed
ddev launch /support-tickets
```

| Item | Value |
|------|--------|
| Site URL | `https://support-tickets.ddev.site` (also shown by `ddev describe` / opened by `ddev launch`) |
| Admin login | `admin` / `admin` |
| Demo users (seed) | `agent.alice`, `agent.bob`, `reporter.cara` — password: `password` |
| Tickets UI | `/support-tickets` |
| Seed command | `ddev drush support_tickets:seed` (alias: `st-seed`) |

Re-check the URL anytime:

```bash
ddev describe
# or
ddev launch
```

---

## Setup without DDEV (secondary)

Use this if you cannot run DDEV. You need PHP 8.1+, Composer 2, a MySQL/MariaDB/SQLite database, and the Drush binary from `vendor/bin/drush` after install.

```bash
git clone <repo-url> support-tickets
cd support-tickets

composer install

# Example with SQLite (no separate DB server):
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

# Run PHP’s built-in server (or point Apache/nginx at web/):
./vendor/bin/drush runserver
# then open http://127.0.0.1:8888/support-tickets
```

---

## Module

- Path: `web/modules/custom/support_tickets`
- Entities: `support_ticket`, `support_ticket_comment`
- Spec: see [`spec.md`](spec.md)

---

## Useful commands

```bash
# DDEV
ddev drush cr
ddev drush uli
ddev drush support_tickets:seed

# Without DDEV
./vendor/bin/drush cr
./vendor/bin/drush uli
./vendor/bin/drush support_tickets:seed
```

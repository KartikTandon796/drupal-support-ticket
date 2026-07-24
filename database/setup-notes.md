# Database setup notes

Database-focused companion to the **Setup** and **Useful Drush Commands** sections in
[`README.md`](../README.md). Steps below match that README — they are not a separate
install path.

---

## Database choice

| Path | Engine | How it is chosen |
|------|--------|------------------|
| **Primary (DDEV)** | **MariaDB 10.11** | `.ddev/config.yaml` (`database.type: mariadb`, `version: "10.11"`). DDEV starts the DB container and wires Drupal’s connection automatically. |
| **Secondary (no DDEV)** | **SQLite** (example in README) or **MySQL/MariaDB** | Passed to `drush site:install` via `--db-url=...` (see [Environment / connection](#environment--connection)). |

No other database drivers are documented for this project.

---

## How the schema is created

This project does **not** use the Drupal **Migrate API**. There are **no** migration YAML
files, `migrate_*` modules, or `drush migrate:import` steps to run.

Schema is created by Drupal’s **entity API** when the custom module is enabled:

```bash
# DDEV
ddev drush en support_tickets -y

# Without DDEV
./vendor/bin/drush en support_tickets -y
```

That installs the content entity tables (including `support_ticket` and
`support_ticket_comment`) along with the module’s `config/install` Views config. Fresh
tables come from entity definitions in `web/modules/custom/support_tickets/`, not from a
hand-written SQL dump or a migrate pipeline.

---

## How seed data is loaded

Demo users and tickets are loaded only by the module’s Drush command:

| | |
|--|--|
| **Command** | `support_tickets:seed` |
| **Alias** | `st-seed` |
| **DDEV** | `ddev drush support_tickets:seed` |
| **Without DDEV** | `./vendor/bin/drush support_tickets:seed` |

Safe to re-run: tickets that already exist (matched by title) are skipped.

---

## What the seed command creates

### Demo users

Password for all: `password` (as printed by the seed command / noted in README).

| Username | Display name |
|----------|--------------|
| `agent.alice` | Alice Agent |
| `agent.bob` | Bob Agent |
| `reporter.cara` | Cara Reporter |

The seed also grants the `authenticated` role the module’s ticket permissions (access,
create, edit, delete, add comments) so demo logins can use the UI.

### Sample tickets (one per status)

| Title | Priority | Final status | Assignee |
|-------|----------|--------------|----------|
| Cannot reset password | high | `open` | — |
| Slow search results on intranet | medium | `in_progress` | agent.alice |
| Update VPN instructions | low | `resolved` | agent.bob |
| Printer jam on floor 3 | low | `closed` | agent.alice |
| Request for custom emoji pack | urgent | `cancelled` | — |

Each ticket is created as `open`, then walked through legal transitions to the target
status (so the state machine is respected). Each ticket also gets one demo comment.

Admin from site install (separate from seed): `admin` / `admin`.

---

## Environment / connection

### DDEV

No manual DB environment variables are required for day-to-day use. DDEV provides MariaDB
and injects the connection settings Drupal needs. Use `ddev start` then the Drush commands
above.

### Without DDEV

Connection is supplied at install time with Drush `--db-url` (from README):

**SQLite example (no separate DB server):**

```bash
./vendor/bin/drush site:install standard \
  --db-url="sqlite://localhost/web/sites/default/files/.ht.sqlite" \
  --site-name="Support Tickets" \
  --account-name=admin \
  --account-pass=admin \
  -y
```

**MySQL/MariaDB example:**

```bash
./vendor/bin/drush site:install standard \
  --db-url="mysql://USER:PASS@127.0.0.1:3306/DBNAME" \
  --site-name="Support Tickets" \
  --account-name=admin \
  --account-pass=admin \
  -y
```

Replace `USER`, `PASS`, and `DBNAME` with your local credentials. After install, enable the
module and seed as usual (no further `--db-url` on those commands — settings are already in
`web/sites/default/settings.php`).

---

## Exact steps from a fresh checkout

### Primary: DDEV

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

Site URL: `https://support-tickets.ddev.site` (also from `ddev describe`).

### Secondary: without DDEV

```bash
git clone <repo-url> support-tickets
cd support-tickets
composer install

./vendor/bin/drush site:install standard \
  --db-url="sqlite://localhost/web/sites/default/files/.ht.sqlite" \
  --site-name="Support Tickets" \
  --account-name=admin \
  --account-pass=admin \
  -y

./vendor/bin/drush en support_tickets -y
./vendor/bin/drush support_tickets:seed

./vendor/bin/drush runserver
# Then open http://127.0.0.1:8888/support-tickets
```

### Useful Drush reminders (from README)

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

Again: **no Migrate imports** — schema via `drush en support_tickets`; demo rows via
`drush support_tickets:seed` / `st-seed`.

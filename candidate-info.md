# Candidate Information

Name: Kartik Tandon
Role: Backend Developer
Primary Technology Stack: PHP / Drupal
Primary AI Tool Used: Cursor
Project Option Selected: Option 1 — Support Ticket Management System (Backend-Heavy)
Assessment Start Date: 2026-07-22
Submission Date: 2026-07-24

## Project Summary
Core-tier Support Ticket Management System built as a Drupal 10 custom module, with
tickets and comments as content entities, a status state machine enforced via a shared
validation constraint (forms + JSON:API), Form API/Views for the UI, and core JSON:API
for the backend API surface.

## Tools Used
Cursor (primary AI tool), DDEV (local environment), Drupal 10, PHPUnit, Drush, GitHub.

## Setup Summary
Full Composer Drupal project; run via `ddev start`, `ddev composer install`,
`ddev drush site:install`, `ddev drush en support_tickets -y`,
`ddev drush support_tickets:seed`. See README.md for full setup instructions.

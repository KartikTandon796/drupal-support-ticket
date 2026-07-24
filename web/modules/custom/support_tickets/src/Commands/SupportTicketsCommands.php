<?php

declare(strict_types=1);

namespace Drupal\support_tickets\Commands;

use Drupal\support_tickets\Entity\TicketInterface;
use Drupal\user\Entity\User;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for Support Tickets.
 */
class SupportTicketsCommands extends DrushCommands {

  /**
   * Seed demo users, tickets, and comments.
   *
   * @command support_tickets:seed
   * @aliases st-seed
   * @usage support_tickets:seed
   *   Creates demo users and tickets in various statuses.
   */
  public function seed(): void {
    $users = $this->ensureUsers([
      'agent.alice' => 'Alice Agent',
      'agent.bob' => 'Bob Agent',
      'reporter.cara' => 'Cara Reporter',
    ]);

    $authenticated_perms = [
      'access support tickets',
      'create support tickets',
      'edit support tickets',
      'delete support tickets',
      'add support ticket comments',
    ];
    user_role_grant_permissions('authenticated', $authenticated_perms);

    $tickets_spec = [
      [
        'title' => 'Cannot reset password',
        'description' => 'Password reset emails are not arriving for several users.',
        'priority' => 'high',
        'status' => 'open',
        'created_by' => $users['reporter.cara']->id(),
        'assigned_to' => NULL,
        'comment' => 'Started triage; waiting on mail logs.',
      ],
      [
        'title' => 'Slow search results on intranet',
        'description' => 'Keyword search takes 10+ seconds during peak hours.',
        'priority' => 'medium',
        'status' => 'in_progress',
        'created_by' => $users['reporter.cara']->id(),
        'assigned_to' => $users['agent.alice']->id(),
        'comment' => 'Profiling the Views query now.',
      ],
      [
        'title' => 'Update VPN instructions',
        'description' => 'Docs still reference the old VPN gateway hostname.',
        'priority' => 'low',
        'status' => 'resolved',
        'created_by' => $users['agent.bob']->id(),
        'assigned_to' => $users['agent.bob']->id(),
        'comment' => 'Docs PR merged; pending close.',
      ],
      [
        'title' => 'Printer jam on floor 3',
        'description' => 'Facilities resolved the hardware issue last week.',
        'priority' => 'low',
        'status' => 'closed',
        'created_by' => $users['reporter.cara']->id(),
        'assigned_to' => $users['agent.alice']->id(),
        'comment' => 'Confirmed fixed by reporter.',
      ],
      [
        'title' => 'Request for custom emoji pack',
        'description' => 'Out of scope for IT support; cancelled after review.',
        'priority' => 'urgent',
        'status' => 'cancelled',
        'created_by' => $users['reporter.cara']->id(),
        'assigned_to' => NULL,
        'comment' => 'Declined — not an IT ticket.',
      ],
    ];

    $storage = \Drupal::entityTypeManager()->getStorage('support_ticket');
    $comment_storage = \Drupal::entityTypeManager()->getStorage('support_ticket_comment');
    $created = 0;

    foreach ($tickets_spec as $spec) {
      $existing = $storage->loadByProperties(['title' => $spec['title']]);
      if ($existing) {
        $this->logger()->notice(dt('Skipping existing ticket: @title', ['@title' => $spec['title']]));
        continue;
      }

      /** @var \Drupal\support_tickets\Entity\TicketInterface $ticket */
      $ticket = $storage->create([
        'title' => $spec['title'],
        'description' => $spec['description'],
        'priority' => $spec['priority'],
        'status' => $spec['status'],
        'created_by' => $spec['created_by'],
        'assigned_to' => $spec['assigned_to'],
      ]);
      $ticket->save();
      $created++;

      $comment_storage->create([
        'ticket_id' => $ticket->id(),
        'message' => $spec['comment'],
        'created_by' => $spec['assigned_to'] ?: $spec['created_by'],
      ])->save();

      $this->logger()->success(dt('Created ticket @id (@status): @title', [
        '@id' => $ticket->id(),
        '@status' => $ticket->getStatusValue(),
        '@title' => $ticket->getTitle(),
      ]));
    }

    $this->logger()->success(dt('Seed complete. Created @count new ticket(s). Statuses covered: @statuses', [
      '@count' => $created,
      '@statuses' => implode(', ', array_keys(TicketInterface::STATUSES)),
    ]));
    $this->logger()->notice(dt('Demo logins (password for all: "password"): @users', [
      '@users' => implode(', ', array_keys($users)),
    ]));
  }

  /**
   * Ensures demo users exist and returns them keyed by username.
   *
   * @param array<string, string> $accounts
   *   Map of username => display name.
   *
   * @return array<string, \Drupal\user\UserInterface>
   *   Loaded or created users.
   */
  protected function ensureUsers(array $accounts): array {
    $users = [];
    foreach ($accounts as $name => $display_name) {
      $existing = user_load_by_name($name);
      if ($existing) {
        $users[$name] = $existing;
        $this->logger()->notice(dt('Using existing user @name', ['@name' => $name]));
        continue;
      }

      $user = User::create([
        'name' => $name,
        'mail' => $name . '@example.com',
        'status' => 1,
        'pass' => 'password',
      ]);
      $user->save();
      $users[$name] = $user;
      $this->logger()->success(dt('Created user @name (@display)', [
        '@name' => $name,
        '@display' => $display_name,
      ]));
    }
    return $users;
  }

}

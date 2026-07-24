<?php

declare(strict_types=1);

namespace Drupal\support_tickets\Service;

use Drupal\support_tickets\Entity\TicketInterface;

/**
 * Validates ticket status transitions against the Core state machine.
 */
class TicketStatusTransitionValidator {

  /**
   * Allowed target statuses keyed by current status (includes same-status no-op).
   *
   * @var array<string, list<string>>
   */
  private const ALLOWED = [
    TicketInterface::STATUS_OPEN => [
      TicketInterface::STATUS_OPEN,
      'in_progress',
      'cancelled',
    ],
    'in_progress' => [
      'in_progress',
      'resolved',
      'cancelled',
    ],
    'resolved' => [
      'resolved',
      'closed',
    ],
    'closed' => [
      'closed',
    ],
    'cancelled' => [
      'cancelled',
    ],
  ];

  /**
   * Whether a transition from $from to $to is allowed.
   */
  public function isTransitionAllowed(string $from, string $to): bool {
    if (!isset(TicketInterface::STATUSES[$from], TicketInterface::STATUSES[$to])) {
      return FALSE;
    }
    return in_array($to, self::ALLOWED[$from] ?? [], TRUE);
  }

  /**
   * Allowed next statuses for a given current status (including no-op).
   *
   * @return list<string>
   */
  public function getAllowedTargets(string $from): array {
    return self::ALLOWED[$from] ?? [];
  }

  /**
   * Human-readable violation message for an invalid transition.
   */
  public function getViolationMessage(string $from, string $to): string {
    $from_label = TicketInterface::STATUSES[$from] ?? $from;
    $to_label = TicketInterface::STATUSES[$to] ?? $to;
    return sprintf(
      'Invalid status transition from "%s" to "%s". Allowed transitions follow the ticket state machine.',
      $from_label,
      $to_label
    );
  }

  /**
   * Whether a brand-new ticket may use this status (must be open).
   */
  public function isValidInitialStatus(string $status): bool {
    return $status === TicketInterface::STATUS_OPEN;
  }

}

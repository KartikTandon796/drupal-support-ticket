<?php

declare(strict_types=1);

namespace Drupal\support_tickets\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validates ticket status transitions against the Core state machine.
 *
 * @Constraint(
 *   id = "TicketStatusTransition",
 *   label = @Translation("Ticket status transition", context = "Validation"),
 *   type = "entity:support_ticket"
 * )
 */
class TicketStatusTransitionConstraint extends Constraint {

  /**
   * Message for an invalid transition between two statuses.
   *
   * @var string
   */
  public string $message = 'Invalid status transition from %from to %to. Allowed transitions follow the ticket state machine.';

  /**
   * Message when a new ticket is not created in open status.
   *
   * @var string
   */
  public string $initialMessage = 'New tickets must start in the "Open" status.';

}

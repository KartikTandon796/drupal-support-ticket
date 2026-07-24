<?php

declare(strict_types=1);

namespace Drupal\support_tickets\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a Ticket entity.
 */
interface TicketInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Allowed priority machine names.
   */
  public const PRIORITIES = [
    'low' => 'Low',
    'medium' => 'Medium',
    'high' => 'High',
    'urgent' => 'Urgent',
  ];

  /**
   * Allowed status machine names.
   */
  public const STATUSES = [
    'open' => 'Open',
    'in_progress' => 'In Progress',
    'resolved' => 'Resolved',
    'closed' => 'Closed',
    'cancelled' => 'Cancelled',
  ];

  /**
   * Default status for newly created tickets.
   */
  public const STATUS_OPEN = 'open';

  /**
   * Gets the ticket title.
   */
  public function getTitle(): string;

  /**
   * Sets the ticket title.
   */
  public function setTitle(string $title): self;

  /**
   * Gets the ticket status machine name.
   */
  public function getStatusValue(): string;

  /**
   * Sets the ticket status machine name.
   */
  public function setStatusValue(string $status): self;

  /**
   * Gets the ticket priority machine name.
   */
  public function getPriority(): string;

  /**
   * Sets the ticket priority machine name.
   */
  public function setPriority(string $priority): self;

}

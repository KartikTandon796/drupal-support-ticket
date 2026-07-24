<?php

declare(strict_types=1);

namespace Drupal\support_tickets\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a Ticket Comment entity.
 */
interface TicketCommentInterface extends ContentEntityInterface, EntityOwnerInterface {

  /**
   * Gets the parent ticket ID.
   */
  public function getTicketId(): ?int;

  /**
   * Gets the comment message.
   */
  public function getMessage(): string;

  /**
   * Sets the comment message.
   */
  public function setMessage(string $message): self;

}

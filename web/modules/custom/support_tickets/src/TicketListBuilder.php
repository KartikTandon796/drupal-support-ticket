<?php

declare(strict_types=1);

namespace Drupal\support_tickets;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Temporary list builder for tickets.
 *
 * The primary list UI will be a Views page (Step 4). This builder backs the
 * entity collection route so the entity type is installable and browsable.
 */
class TicketListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['title'] = $this->t('Title');
    $header['status'] = $this->t('Status');
    $header['priority'] = $this->t('Priority');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\support_tickets\Entity\TicketInterface $entity */
    $row['id'] = $entity->id();
    $row['title'] = Link::createFromRoute(
      $entity->label(),
      'entity.support_ticket.canonical',
      ['support_ticket' => $entity->id()]
    );
    $row['status'] = $entity->getStatusValue();
    $row['priority'] = $entity->getPriority();
    return $row + parent::buildRow($entity);
  }

}

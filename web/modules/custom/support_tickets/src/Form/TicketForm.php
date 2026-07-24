<?php

declare(strict_types=1);

namespace Drupal\support_tickets\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Ticket create/edit.
 *
 * Status-transition UX refinements land in the Forms + Views step.
 */
class TicketForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $entity = $this->entity;

    $this->messenger()->addStatus($this->t('Ticket %title has been saved.', [
      '%title' => $entity->label(),
    ]));
    $form_state->setRedirect('entity.support_ticket.canonical', [
      'support_ticket' => $entity->id(),
    ]);

    return $result;
  }

}

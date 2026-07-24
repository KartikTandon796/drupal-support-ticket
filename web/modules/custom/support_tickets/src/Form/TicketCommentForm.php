<?php

declare(strict_types=1);

namespace Drupal\support_tickets\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Ticket Comment create/edit.
 *
 * Ticket-detail comment UX lands in the Forms + Views step.
 */
class TicketCommentForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $entity = $this->entity;

    $this->messenger()->addStatus($this->t('Comment has been saved.'));

    $ticket_id = $entity->get('ticket_id')->target_id;
    if ($ticket_id) {
      $form_state->setRedirect('entity.support_ticket.canonical', [
        'support_ticket' => $ticket_id,
      ]);
    }
    else {
      $form_state->setRedirect('entity.support_ticket.collection');
    }

    return $result;
  }

}

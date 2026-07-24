<?php

declare(strict_types=1);

namespace Drupal\support_tickets\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\support_tickets\Entity\TicketInterface;
use Drupal\support_tickets\Service\TicketStatusTransitionValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for Ticket create/edit.
 *
 * Status options are limited to legal transitions; invalid saves still fail
 * entity validation (shared with JSON:API).
 */
class TicketForm extends ContentEntityForm {

  /**
   * Status transition validator.
   */
  protected TicketStatusTransitionValidator $statusTransitionValidator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->statusTransitionValidator = $container->get('support_tickets.status_transition_validator');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\support_tickets\Entity\TicketInterface $ticket */
    $ticket = $this->entity;
    if (isset($form['status']['widget'][0]['value'])) {
      $current = $ticket->isNew()
        ? TicketInterface::STATUS_OPEN
        : $ticket->getStatusValue();
      $allowed = $this->statusTransitionValidator->getAllowedTargets($current);
      $options = array_intersect_key(TicketInterface::STATUSES, array_flip($allowed));
      $form['status']['widget'][0]['value']['#options'] = $options;
      if ($ticket->isNew()) {
        $form['status']['widget'][0]['value']['#default_value'] = TicketInterface::STATUS_OPEN;
      }
    }

    return $form;
  }

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

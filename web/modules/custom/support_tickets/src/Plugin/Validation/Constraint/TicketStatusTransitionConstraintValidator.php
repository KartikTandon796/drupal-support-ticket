<?php

declare(strict_types=1);

namespace Drupal\support_tickets\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\support_tickets\Entity\TicketInterface;
use Drupal\support_tickets\Service\TicketStatusTransitionValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates TicketStatusTransitionConstraint.
 */
class TicketStatusTransitionConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs the validator.
   */
  public function __construct(
    protected TicketStatusTransitionValidator $transitionValidator,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('support_tickets.status_transition_validator'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $entity, Constraint $constraint): void {
    if (!$entity instanceof TicketInterface || !$constraint instanceof TicketStatusTransitionConstraint) {
      return;
    }

    $new_status = $entity->getStatusValue();
    if ($new_status === '') {
      return;
    }

    if ($entity->isNew()) {
      if (!$this->transitionValidator->isValidInitialStatus($new_status)) {
        $this->context->buildViolation($constraint->initialMessage)
          ->atPath('status')
          ->addViolation();
      }
      return;
    }

    $original_status = $this->resolveOriginalStatus($entity);
    if ($original_status === NULL || $original_status === $new_status) {
      // Same-status no-op is allowed; skip if original cannot be resolved.
      return;
    }

    if (!$this->transitionValidator->isTransitionAllowed($original_status, $new_status)) {
      $from_label = TicketInterface::STATUSES[$original_status] ?? $original_status;
      $to_label = TicketInterface::STATUSES[$new_status] ?? $new_status;
      $this->context->buildViolation($constraint->message)
        ->setParameter('%from', $from_label)
        ->setParameter('%to', $to_label)
        ->atPath('status')
        ->addViolation();
    }
  }

  /**
   * Resolves the persisted status before this save.
   */
  protected function resolveOriginalStatus(TicketInterface $entity): ?string {
    if (isset($entity->original) && $entity->original instanceof TicketInterface) {
      return $entity->original->getStatusValue();
    }

    $unchanged = $this->entityTypeManager
      ->getStorage('support_ticket')
      ->loadUnchanged($entity->id());

    return $unchanged instanceof TicketInterface ? $unchanged->getStatusValue() : NULL;
  }

}

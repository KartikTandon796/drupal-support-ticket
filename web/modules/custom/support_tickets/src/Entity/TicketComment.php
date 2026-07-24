<?php

declare(strict_types=1);

namespace Drupal\support_tickets\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Ticket Comment content entity.
 *
 * Entity type id is support_ticket_comment to avoid colliding with core
 * comment module's "comment" entity type.
 *
 * @ContentEntityType(
 *   id = "support_ticket_comment",
 *   label = @Translation("Ticket comment"),
 *   label_collection = @Translation("Ticket comments"),
 *   label_singular = @Translation("ticket comment"),
 *   label_plural = @Translation("ticket comments"),
 *   label_count = @PluralTranslation(
 *     singular = "@count ticket comment",
 *     plural = "@count ticket comments"
 *   ),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\support_tickets\TicketCommentAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\support_tickets\Form\TicketCommentForm",
 *       "add" = "Drupal\support_tickets\Form\TicketCommentForm",
 *       "edit" = "Drupal\support_tickets\Form\TicketCommentForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider"
 *     }
 *   },
 *   base_table = "support_ticket_comment",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "created_by",
 *     "uid" = "created_by"
 *   },
 *   links = {
 *     "canonical" = "/support-tickets/comment/{support_ticket_comment}",
 *     "add-form" = "/support-tickets/comment/add",
 *     "edit-form" = "/support-tickets/comment/{support_ticket_comment}/edit",
 *     "delete-form" = "/support-tickets/comment/{support_ticket_comment}/delete",
 *     "collection" = "/admin/content/support-ticket-comments"
 *   }
 * )
 */
class TicketComment extends ContentEntityBase implements TicketCommentInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function getTicketId(): ?int {
    $value = $this->get('ticket_id')->target_id;
    return $value !== NULL ? (int) $value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getMessage(): string {
    return (string) $this->get('message')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setMessage(string $message): TicketCommentInterface {
    $this->set('message', $message);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $message = $this->getMessage();
    if (mb_strlen($message) > 50) {
      return mb_substr($message, 0, 47) . '...';
    }
    return $message !== '' ? $message : (string) $this->id();
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['ticket_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Ticket'))
      ->setDescription(t('The ticket this comment belongs to.'))
      ->setSetting('target_type', 'support_ticket')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -10,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['message'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Message'))
      ->setDescription(t('The comment body.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
        'settings' => [
          'rows' => 5,
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'basic_string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created_by']->setLabel(t('Created by'))
      ->setDescription(t('The user who wrote this comment.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', FALSE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the comment was created.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}

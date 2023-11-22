<?php

namespace Drupal\mass_entityaccess_userreference\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;

/**
 * Defines the User Ref Access entity.
 *
 * @ingroup mass_entityaccess_userreference
 *
 * @ContentEntityType(
 *   id = "user_ref_access",
 *   label = @Translation("User Reference Access"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *   },
 *   base_table = "user_ref_access",
 *   admin_permission = "administer user access reference entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   }
 * )
 */
class UserRefAccess extends ContentEntityBase implements ContentEntityInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the User Reference Access entity.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the User Reference Access entity.'))
      ->setReadOnly(TRUE);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the user_ref_access entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['additional_users'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Additional Users'))
      ->setDescription(t('The additional users IDs to restrict access too.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['enabled'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Enable'))
      ->setDescription(t('On/Off'))
      ->setDefaultValue(0)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'hidden',
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type'))
      ->setDescription(t('The type of token belongs to.'))
      ->setRevisionable(TRUE)
      ->setReadOnly(TRUE);

    $fields['entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Entity id'))
      ->setDescription(t('The id the entity belongs to.'))
      ->setRevisionable(TRUE)
      ->setReadOnly(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

  /**
   * Determines if the user should have access to the entity.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The entity to check the access on.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check access with.
   *
   * @return bool
   *   Returns TRUE if the user's access to the entity should be revoked.
   */
  public function revokeUserAccess(Node $node, AccountInterface $account) {
    $additional_users = [];
    $enabled = FALSE;
    $author = '';

    $enabled_field = $this->get('enabled');
    if ($enabled_field->count() > 0) {
      $enabled = $enabled_field->first()->getValue();
    }

    if ($enabled['value'] == FALSE) {
      return FALSE;
    }

    // Only alter access if the operation is view and if the user does not have the bypass permission.
    if (!$account->hasPermission('bypass entityaccess userreference')) {

      $additional_users_field = $this->get('additional_users');
      if ($additional_users_field->count() > 0) {
        $additional_users = $additional_users_field->getValue();
      }

      $author_field = $this->get('user_id');
      if ($author_field->count() > 0) {
        $author = $author_field->first()->getValue();
      }

      if ($author['target_id'] == $account->id()) {
        return FALSE;
      }

      foreach ($additional_users as $user) {
        if ($user['target_id'] == $account->id()) {
          // Grant access if the current user is not in the users list.
          return FALSE;
        }
      }

      // Revoke access if the current user is not in the users list.
      return TRUE;
    }

    return FALSE;
  }

}

<?php

namespace Drupal\mass_moderation_migration\Plugin\migrate\destination;

use Drupal\migrate\Plugin\migrate\destination\EntityRevision as BaseEntityRevision;
use Drupal\migrate\Row;
use Drupal\content_moderation\Entity\ContentModerationState;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Map keys as destinations for a given migration.
 */
class EntityRevision extends BaseEntityRevision {

  /**
   * {@inheritdoc}
   */
  protected function getEntity(Row $row, array $old_destination_id_values) {
    $vid = $row->getDestinationProperty($this->getKey('revision'));
    $mod_state = $row->getDestinationProperty('moderation_state');
    $entity = $this->storage->loadRevision($vid);
    content_moderation_entity_insert($entity);
    $moderated = ContentModerationState::loadFromModeratedEntity($entity);
    $moderated->set('content_entity_revision_id', $vid);
    $moderated->set('moderation_state', $mod_state);
    ContentModerationState::updateOrCreateFromEntity($moderated);
    return $moderated;
  }

  /**
   * {@inheritdoc}
   */
  protected function save(ContentEntityInterface $entity, array $old_destination_id_values = []) {
    return [$entity->content_entity_revision_id->value];
  }

}

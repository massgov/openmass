<?php

/**
 * @file
 * Deploy.
 */

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\scheduled_transitions\Entity\ScheduledTransition;
use Drupal\workflows\Entity\Workflow;

/**
 * Migrate media to scheduled transitions.
 */
function mass_scheduled_transitions_deploy_scheduled_transitions_media(&$sandbox) {
  return mass_scheduled_transitions_do_migration($sandbox, 'media');
}

/**
 * Migrate nodes to scheduled transitions.
 */
function mass_scheduled_transitions_deploy_scheduled_transitions_nodes(&$sandbox) {
  return mass_scheduled_transitions_do_migration($sandbox, 'node');
}

/**
 * Migrate a content type.
 */
function mass_scheduled_transitions_do_migration(&$sandbox, $entity_type) {
  $now = (new DrupalDateTime())->getTimeStamp();
  $query = \Drupal::entityQuery($entity_type)->accessCheck(FALSE)
    ->condition('status', 1);
  $group = $query->orConditionGroup();
  $group->condition('unpublish_on', $now, '>');
  $group->condition('publish_on', $now, '>');
  $query->condition($group);

  if (empty($sandbox)) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $count = clone $query;
    $sandbox['max'] = $count->count()->execute();
  }

  $batch_size = 50;
  $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
  $idKey = $entity_type == 'node' ? 'nid' : 'mid';
  $eids = $query->accessCheck(FALSE)->condition($idKey, $sandbox['current'], '>')
    ->sort($idKey)
    ->range(0, $batch_size)
    ->execute();

  /** @var \Drupal\Core\Entity\ContentEntityInterface[] $entities */
  $entities = $storage->loadMultiple($eids);
  foreach ($entities as $entity) {
    $sandbox['current'] = $entity->id();
    // Default to documents.
    $workflow = 'media_states';
    if ($entity_type == 'node') {
      // Override for each node type.
      $workflow = $entity->bundle() == 'campaign_landing' ? 'campaign_landing_page' : 'editorial';
    }
    if ($entity->unpublish_on->getString() > $now) {
      $transition = ScheduledTransition::createFrom(Workflow::load($workflow), MassModeration::UNPUBLISHED, $entity, (new DrupalDateTime('@' . $entity->unpublish_on->getString()))->getPhpDateTime(), $entity->getOwner());
      $transition->setEntityRevisionId(0)
        ->setOptions([MASS_SCHEDULED_TRANSITIONS_OPTIONS]);
      $transition->save();
    }
    if ($entity->publish_on->getString() > $now) {
      $transition = ScheduledTransition::createFrom(Workflow::load($workflow), MassModeration::PUBLISHED, $entity, (new DrupalDateTime('@' . $entity->publish_on->getString()))->getPhpDateTime(), $entity->getOwner());
      $transition->setEntityRevisionId(0)
        ->setOptions([MASS_SCHEDULED_TRANSITIONS_OPTIONS]);
      $transition->save();
    }
    $sandbox['progress']++;
  }

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    return "All $entity_type scheduled transitions migrated.";
  }
}

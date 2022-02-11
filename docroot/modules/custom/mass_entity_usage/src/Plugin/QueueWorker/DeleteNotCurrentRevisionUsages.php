<?php

namespace Drupal\mass_entity_usage\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes the entity usage tracking via a queue.
 *
 * @QueueWorker(
 *   id = "mass_entity_usage_delete_not_current_revision_usages",
 *   title = @Translation("Deletes entity usage data for non current revisions"),
 * )
 */
class DeleteNotCurrentRevisionUsages extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $this->keepOnlyCurrentVid($data['id'], $data['entity_type']);
  }

  /**
   * Deletes usage data from revisions that are not the current revision.
   *
   * Entity usage also stores data from other revisions than the current one,
   * but we don't want/need that data, we don't want to see that data in the
   * entity_usage table either.
   */
  private function keepOnlyCurrentVid($id, $entity_type) {
    $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
    /** @var \Drupal\Core\Entity\ContentEntityBase */
    $entity = $storage->load($id);

    if (
      !$entity ||
      !$entity->getEntityType()->isRevisionable()
    ) {
      return;
    }

    // Only intervene on tracked entities.
    $to_track = \Drupal::config('entity_usage.settings')->get('track_enabled_source_entity_types');

    /** @var Drupal\Core\Entity\ContentEntityType */
    $entity_type = $entity->getEntityType();

    if (is_array($to_track) && !in_array($entity_type->id(), $to_track, TRUE)) {
      return;
    }

    $vid = $entity->getRevisionId();
    $delete = \Drupal::database()->delete('entity_usage');

    // Condition to delete reference to itself.
    $c1 = $delete->andConditionGroup()
        ->condition('target_id', $entity->id())
        ->condition('source_id', $entity->id());

    // Condition to delete reference to itself when entity uses string id.
    $c2 = $delete->andConditionGroup()
      ->condition('target_id_string', $entity->id())
      ->condition('source_id_string', $entity->id());

    // Condition to delete references from other revisions.
    $c3 = $delete->andConditionGroup()
      ->condition('source_id', $entity->id())
      ->condition('source_vid', $vid, '<>');

    // Condition to delete references from other when entity uses string id.
    $c4 = $delete->andConditionGroup()
      ->condition('source_id_string', $entity->id())
      ->condition('source_vid', $vid, '<>');

    $delete->orConditionGroup()
      ->condition($c1)
      ->condition($c2)
      ->condition($c3)
      ->condition($c4);

    $delete->execute();
  }

}

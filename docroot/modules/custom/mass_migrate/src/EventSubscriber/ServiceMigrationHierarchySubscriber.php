<?php

namespace Drupal\mass_migrate\EventSubscriber;

use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigrateEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class MyCustomMigrationSubscriber.
 */
class ServiceMigrationHierarchySubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      MigrateEvents::POST_ROW_SAVE => 'afterRowImport',

    ];
  }

  /**
   * Callback to run after a row has been imported.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   The row import event.
   */
  public function afterRowImport(MigratePostRowSaveEvent $event) {
    if ($event->getMigration()->id() == 'service_details') {
      $old_nid = $event->getRow()->getSourceIdValues()['nid'];
      $migrated_nid = $event->getDestinationIdValues()[0];
      $migrated_vid = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->getLatestRevisionId($migrated_nid);
      $database = \Drupal::database();
      $query = $database->update('nested_set_field_primary_parent_node');
      $query->fields([
        'id' => (int) $migrated_nid,
        'revision_id' => (int) $migrated_vid,
      ]);
      $query->condition('id', $old_nid);
      $query->execute();
    }
  }

}

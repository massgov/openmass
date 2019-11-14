<?php

namespace Drupal\mass_utility\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Driver\Exception\Exception;

/**
 * Transfers data from taxonomy field to new Organization reference field.
 *
 * @QueueWorker(
 *   id = "mass_utility_doc_organization_transfer",
 *   title = @Translation("Transfers old organization fields to new one."),
 *   cron = {"time" = 180}
 * )
 */
class OrganizationTransfer extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
    // Allow batching items to queue for faster initial processing.
    if (isset($data->ids)) {
      $storage_manager = \Drupal::entityTypeManager()->getStorage('media');
      $rows = $storage_manager->loadMultiple($data->ids);
      /** @var \Drupal\media\Entity\Media $media */
      foreach ($rows as $media) {
        try {
          $org_term = $media->get('field_contributing_organization')->referencedEntities();
          foreach ($org_term as $org) {
            $org_ref = $org->get('field_state_organization')->getValue();
            $media->get('field_organizations')->setValue($org_ref);
            $media->save();
          }
        }
        catch (Exception $e) {
          \Drupal::logger('mass_utility')->error($e->getMessage());
        }
      }
    }
  }

}

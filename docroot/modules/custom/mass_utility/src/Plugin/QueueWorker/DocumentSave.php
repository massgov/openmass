<?php

namespace Drupal\mass_utility\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Driver\Exception\Exception;
use Drupal\file\Entity\File;

/**
 * Saves unpublished documents that have public files so the files become private.
 *
 * @QueueWorker(
 *   id = "mass_utility_document_save",
 *   title = @Translation("Saves unpublished documents with public files to change file to private."),
 *   cron = {"time" = 180}
 * )
 */
class DocumentSave extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
    if (isset($data->ids)) {
      $storage_manager = \Drupal::entityTypeManager()->getStorage('media');
      $entities = $storage_manager->loadMultiple($data->ids);
      /** @var \Drupal\media\Entity\Media $media */
      foreach ($entities as $entity) {
        try {
          $file = File::load($entity->field_upload_file->target_id);
          if ($file) {
            $file_uri = $file->getFileUri();
            /** @var \Drupal\Core\File\FileSystem $file_system */
            $file_system = \Drupal::service('file_system');
            $file_scheme = $file_system->uriScheme($file_uri);
            if ($file_scheme == 'public') {
              $entity->save();
            }
          }
        }
        catch (Exception $e) {
          \Drupal::logger('mass_utility')->error($e->getMessage());
        }
      }
    }
  }

}

<?php

namespace Drupal\mass_media\Entity\Bundle;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\file\Entity\File;

/**
 * A bundle class for media entities.
 */
class DocumentBundle extends MediaBundle {

  /**
   * Get the changed time for the first referenced file.
   *
   * @param $field_name
   */
  public function getFileChangedTime($field_name = 'field_upload_file'): string {
    $file = $this->{$field_name}->entity;
    if (!$file instanceof File) {
      \Drupal::logger('media.documents')->error('Media bundle is missing file for media entity: @entity_label (@entity_id)', [
        '@entity_label' => $this->label(),
        '@entity_id' => $this->id(),
      ]);
      return '';
    }
    return DrupalDateTime::createFromTimestamp($file->getChangedTime())->format('Y-m-d');
  }

}

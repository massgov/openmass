<?php

namespace Drupal\mass_content\Entity\Bundle\media;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\file\Entity\File;

/**
 * A bundle class for media entities.
 */
class DocumentBundle extends MediaBundle {

  /**
   * Get title.
   */
  public function getTitle(): FieldItemListInterface {
    return $this->get('field_title');
  }

  /**
   * Get uploaded file.
   */
  public function getUploadFile(): FieldItemListInterface {
    return $this->get('field_upload_file');
  }

  /**
   * Get the changed time for the first referenced file.
   */
  public function getFileChangedTime($field_name = 'field_upload_file'): string {
    $file = $this->{$field_name}->entity;
    if (!$file instanceof File) {
      // This log has poor signal/noise ratio. Uncomment when needed, or use the report at https://edit.mass.gov/admin/reports/missing-files
      // \Drupal::logger('media.documents')->notice('Document is missing its File: @entity_label (@entity_id)', [
      // '@entity_label' => $this->label(),
      // '@entity_id' => $this->id(),
      // ]);
      return '';
    }
    return DrupalDateTime::createFromTimestamp($file->getChangedTime())->format('Y-m-d');
  }

}

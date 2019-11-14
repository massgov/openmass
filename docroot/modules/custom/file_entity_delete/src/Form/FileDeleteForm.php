<?php

namespace Drupal\file_entity_delete\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Url;

/**
 * Delete form override for file entities.
 *
 * Passes the user back to the files listing, otherwise they get
 * a 500 when viewing this form because files don't have a canonical
 * URL.
 */
class FileDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('view.files.page_1');
  }

}

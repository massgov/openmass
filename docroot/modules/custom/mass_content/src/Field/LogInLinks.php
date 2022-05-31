<?php

namespace Drupal\mass_content\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Generates the contextual log in links for a page's header.
 */
class LogInLinks extends FieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  public function computeValue() {
    $entity = $this->getEntity();
    if (!$entity->isNew()) {
      /** @var \Drupal\mass_content\LogInLinksBuilder */
      $logInLinksBuilder = \Drupal::service('mass_content.log_in_links_builder');
      $this->list = $logInLinksBuilder->getContextualLoginLinks($entity);
    }
  }

}

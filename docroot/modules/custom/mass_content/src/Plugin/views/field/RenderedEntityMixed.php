<?php

namespace Drupal\mass_content\Plugin\views\field;

use Drupal\views\Plugin\views\field\RenderedEntity;
use Drupal\views\ResultRow;

/**
 * Renders content or other entity type in a certain view mode.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("rendered_entity_mixed")
 */
class RenderedEntityMixed extends RenderedEntity {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity_type_id = $values->entity_type;
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $entity = $storage->load($values->nid);

    $entity = $this->getEntityTranslation($entity, $values);
    $build = [];
    if (!isset($entity)) {
      return $build;
    }

    $access = $entity->access('view', NULL, TRUE);
    $build['#access'] = $access;
    if (!$access->isAllowed()) {
      return $build;
    }

    $view_builder = $this->entityTypeManager->getViewBuilder($entity_type_id);
    $build += $view_builder->view($entity, $this->options['view_mode'], $entity->language()->getId());
    return $build;
  }

}

<?php

namespace Drupal\mayflower\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * OOP theme-layer hook implementations for mayflower.
 */
class MayflowerThemeHooks {

  /**
   * Forces an empty alt on mosaic featured item images.
   *
   * Mosaic images are decorative: the featured item link text carries the
   * content, so screen readers must skip the image. Authors can no longer
   * enter alt text for these fields, and alt values stored before that
   * change must not render either.
   */
  #[Hook('preprocess_responsive_image_formatter')]
  public function preprocessResponsiveImageFormatter(array &$variables): void {
    $definition = $variables['item']->getFieldDefinition();
    $decorative_fields = ['field_featured_item_image', 'field_featured_item_highlight'];
    if ($definition->getTargetEntityTypeId() === 'paragraph' && in_array($definition->getName(), $decorative_fields, TRUE)) {
      $variables['responsive_image']['#attributes']['alt'] = '';
    }
  }

}

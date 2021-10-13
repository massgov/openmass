<?php

namespace Drupal\mass_views\Plugin\views\field;

use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Field handler to count the org_page featured message references.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("org_featured_messages_count")
 */
class OrgPageFeaturedMessagesCount extends OrgPageCountBase {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    $this->pseudoTableAlias = 'pifd_fm_count';
    $this->pseudoFieldName = 'org_featured_messages_count';
    $this->paragraphBundle = 'featured_message';
    parent::init($view, $display, $options);
  }

}

<?php

namespace Drupal\mass_views\Plugin\views\field;

use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Field handler to count the org_page featured message references.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("org_wwyltd_count")
 */
class OrgPageWwyltdCount extends OrgPageCountBase {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    $this->pseudoTableAlias = 'pifd_wwyltd_count';
    $this->pseudoFieldName = 'org_wwyltd_count';
    $this->paragraphBundle = 'what_would_you_like_to_do';
    parent::init($view, $display, $options);
  }

}

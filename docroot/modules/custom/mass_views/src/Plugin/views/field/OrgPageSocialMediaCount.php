<?php

namespace Drupal\mass_views\Plugin\views\field;

use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Field handler to count the org_page featured message references.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("org_social_media_count")
 */
class OrgPageSocialMediaCount extends OrgPageCountBase {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    $this->pseudoTableAlias = 'pifd_sm_count';
    $this->pseudoFieldName = 'org_social_media_count';
    $this->paragraphBundle = 'social_media';
    parent::init($view, $display, $options);
  }

}

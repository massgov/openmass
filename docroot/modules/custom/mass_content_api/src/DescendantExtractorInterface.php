<?php

namespace Drupal\mass_content_api;

use Drupal\node\Entity\Node;

/**
 * Defines the interface for extracting relationships for the DM.
 */
interface DescendantExtractorInterface {

  /**
   * Extract relationships for the node.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node you want to extract data for.
   *
   * @return array
   *   An associative array, keyed on 'parent', 'child', and 'linking_pages'.
   */
  public function extract(Node $node);

}

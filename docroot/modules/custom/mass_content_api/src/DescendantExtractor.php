<?php

namespace Drupal\mass_content_api;

use Drupal\node\Entity\Node;

/**
 * Descendant extractor.
 *
 * This class is responsible for extracting all of the relationships we care
 * about for a particular entity.  Its primary entrypoint is the `extract`
 * method, which traverses through sets of predefined fields to find the
 * nodes that match given conditions.
 *
 * The fields we traverse are defined in the node type configuration.
 *
 * @see \Drupal\mass_content_api\FieldProcessingTrait::fetchNodeTypeConfig().
 *
 * The traversal happens in `FieldProcessingTrait`.
 *
 * @see \Drupal\mass_content_api\FieldProcessingTrait::fetchRelations()
 */
class DescendantExtractor implements DescendantExtractorInterface {

  use FieldProcessingTrait;

  /**
   * {@inheritdoc}
   */
  public function extract(Node $node) {
    $fields = $this->fetchNodeTypeConfig($node);
    // Because we loop through all of the dependency_status configuration fields
    // of a node type in fetchRelations() we only need to call it once.
    return $this->fetchRelations($node, $fields);
  }

}

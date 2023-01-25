<?php

namespace Drupal\mass_content\Entity\Bundle\node;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\mass_fields\MassCollectionTrait;
use Drupal\mass_fields\MassSearchTrait;
use Drupal\mass_content_moderation\MassModerationTrait;
use Drupal\node\Entity\Node;

/**
 * A base bundle class for node entities.
 */
abstract class NodeBundle extends Node {
  use MassSearchTrait;
  use MassCollectionTrait;
  use MassModerationTrait;

  /**
   * Get search nosnippet value. Media doesn't have this field.
   */
  public function getSearchNoSnippet(): FieldItemListInterface {
    return $this->get('search_nosnippet');
  }

}

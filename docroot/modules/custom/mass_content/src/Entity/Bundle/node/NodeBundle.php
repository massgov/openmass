<?php

namespace Drupal\mass_content\Entity\Bundle\node;

use Drupal\mass_fields\MassSearchTrait;
use Drupal\node\Entity\Node;

/**
 * A base bundle class for node entities.
 */
abstract class NodeBundle extends Node {
  use MassSearchTrait;
}

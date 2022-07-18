<?php

namespace Drupal\mass_content\Entity\Bundle\node;


use Drupal\mass_fields\MassSearchTrait;
use Drupal\mass_content_moderation\MassModerationTrait;
use Drupal\node\Entity\Node;

/**
 * A base bundle class for node entities.
 */
abstract class NodeBundle extends Node {
  use MassSearchTrait;
  use MassModerationTrait;
}

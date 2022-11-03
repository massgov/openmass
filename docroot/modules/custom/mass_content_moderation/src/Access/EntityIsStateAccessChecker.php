<?php

namespace Drupal\mass_content_moderation\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Symfony\Component\Routing\Route;

class EntityIsStateAccessChecker implements AccessInterface {

  public function access(Route $route, ContentEntityInterface $node): AccessResultInterface {
    return AccessResult::allowedIf($node->get('moderation_state')->getString() == $route->getRequirement('_entity_is_state'));
  }

}

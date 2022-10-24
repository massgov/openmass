<?php

namespace Drupal\mass_content_moderation\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\Routing\Route;

class EntityIsStateAccessChecker implements AccessInterface {

  /**
   * Access callback.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   */
  public function access(Route $route, ContentEntityInterface $node): AccessResultInterface {
    return AccessResult::allowedIf($node->get('moderation_state')->getString() == $route->getRequirement('_entity_is_state'));
  }

}

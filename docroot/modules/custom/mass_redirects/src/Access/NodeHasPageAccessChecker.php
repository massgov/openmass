<?php

namespace Drupal\mass_redirects\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\node\NodeInterface;
use Drupal\simple_sitemap\Manager\Generator;
use Symfony\Component\Routing\Route;

class NodeHasPageAccessChecker implements AccessInterface {

  private Generator $sitemap;

  public function __construct(Generator $sitemap) {
    $this->sitemap = $sitemap;
  }

  public function access(Route $route, NodeInterface $node): AccessResultInterface {
    $has_page = $this->sitemap->entityManager()->bundleIsIndexed($node->getEntityTypeId(), $node->bundle());
    return AccessResult::allowedIf($has_page);
  }

}

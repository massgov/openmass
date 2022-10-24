<?php

namespace Drupal\mass_redirects\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\node\NodeInterface;
use Drupal\simple_sitemap\Simplesitemap;
use Symfony\Component\Routing\Route;

class NodeHasPageAccessChecker implements AccessInterface {

  /**
   * @var \Drupal\simple_sitemap\Simplesitemap
   */
  private Simplesitemap $sitemap;

  public function __construct(Simplesitemap $sitemap) {
    $this->sitemap = $sitemap;
  }

  /**
   * Access callback.
   */
  public function access(Route $route, NodeInterface $node): AccessResultInterface {
    $settings = $this->sitemap->getBundleSettings();
    $has_page = $settings[$node->getEntityTypeId()][$node->bundle()]['index'];
    return AccessResult::allowedIf($has_page);
  }

}

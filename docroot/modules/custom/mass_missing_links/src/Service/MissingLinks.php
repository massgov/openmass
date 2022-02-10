<?php

namespace Drupal\mass_jsonapi\EventSubscriber;

use Drupal\Component\Utility\Html;
use Drupal\node\Entity\Node;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Mass JSONAPI event subscriber.
 */
class MissingLinks {

  const MASS_THEME = "mass_theme";
  private $theme_to_restore;

  private function changeTheme() {
    /**
     * See original implementation http://cgit.drupalcode.org/mailsystem/tree/src/MailsystemManager.php#n60
     */
    // Switch the theme to the configured mail theme.
    $theme_manager = \Drupal::service('theme.manager');
    $this->theme_to_restore = $theme_manager->getActiveTheme();
    if (self::MASS_THEME != $this->theme_to_restore->getName()) {
      $theme_initialization = \Drupal::service('theme.initialization');
      $theme_manager->setActiveTheme($theme_initialization->initTheme());
    }

  }

  private function restoreTheme() {
    $theme_manager = \Drupal::service('theme.manager');

    if (self::MASS_THEME != $this->theme_to_restore->getName()) {
      $theme_manager->setActiveTheme($this->theme_to_restore);
    }
  }

  private function parseLinks($html) {
    $dom = Html::load($html);

    /** @var \DOMNodeList $links */
    $links = $dom->getElementsByTagName('a');
    foreach ($links as $link) {
      /** @var \DOMElement $link */
      $href = $link->getAttribute('href');
      dump($href);
    }
    // Do something.

    // Return links.

    return [];
  }

  private function getChildren($nid) {
    $res = \views_get_view_result('mass_missing_links_children', NULL, $nid);
    $children = [];
    foreach ($res as $child) {
      // @TODO: modify = $child.
      $children[] = $child;
    }
    return $children;
  }

  private function removeLinkedChildren($links, $children) {

  }

  private function getRenderedNodeInFull($node) {
    $builder = \Drupal::entityTypeManager()->getViewBuilder('node');
    $renderable = $builder->view($node, 'full');
    $html = \Drupal::service('renderer')->render($renderable);
    return $html;
  }

  public function getUnlinkedChildren(Node $node) {

    $this->changeTheme();

    try {
      $html = $this->getRenderedNodeInFull($node);
      $links = $this->parseLinks($html);
      $children = $this->getChildren($node->id());
      return $this->removeLinkedChildren($links, $children);
    }
    finally {
      $this->restoreTheme();
    }
  }

}

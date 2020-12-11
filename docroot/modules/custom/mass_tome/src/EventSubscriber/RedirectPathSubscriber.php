<?php

namespace Drupal\mass_tome\EventSubscriber;

use Drupal\tome_static\Event\CollectPathsEvent;
use Drupal\tome_static\EventSubscriber\RedirectPathSubscriber as RedirectPathSubscriberBase;

/**
 * Overrides the class provided by Tome Static
 */
class RedirectPathSubscriber extends RedirectPathSubscriberBase {

  /**
   * Adds
   *  - Skips all for now.
   *
   * @param \Drupal\tome_static\Event\CollectPathsEvent $event
   */
  public function collectPaths(CollectPathsEvent $event) {

  }

}

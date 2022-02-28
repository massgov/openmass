<?php

namespace Drupal\mass_caching;

use Drupal\akamai\Event\AkamaiPurgeEvents;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;

/**
 * Honor URLs that are passed, *without normalization*.
 *
 * This purger class gets swapped in via mass_caching_purge_purgers_alter().
 */
class AkamaiPurger extends \Drupal\akamai\Plugin\Purge\Purger\AkamaiPurger {

  /**
   * {@inheritdoc}
   */
  public function invalidate(array $invalidations) {
    $urls_to_clear = [];
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      $invalidation_type = $invalidation->getPluginId();

      switch ($invalidation_type) {
        case 'path':
          $urls_to_clear[] = $this->normalizePath($invalidation->getExpression());
          break;

        case 'url':
          // Mass: We simplified the code in this branch to not do normalization.
          $urls_to_clear[] = $invalidation->getUrl()->toString();
          break;
      }
    }

    // Instantiate event and alter tags with subscribers.
    $event = new AkamaiPurgeEvents($urls_to_clear);
    $this->eventDispatcher->dispatch(AkamaiPurgeEvents::PURGE_CREATION, $event);
    $urls_to_clear = $event->data;

    // Mass: Added an array_unique() here as quick fix for dupes.
    $urls_to_clear = array_unique($urls_to_clear);

    // Mass: Go right to purgeRequest(), bypassing unwanted check in purgeUrls().
    $method = new \ReflectionMethod($this->client, 'purgeRequest');
    $method->setAccessible(TRUE);
    if ($method->invoke($this->client, $urls_to_clear)) {
      // Now mark all URLs as cleared.
      foreach ($invalidations as $invalidation) {
        $invalidation->setState(InvalidationInterface::SUCCEEDED);
      }
    }
    else {
      $msg = 'AkamaiPurger: Failed to purge ' . count($urls_to_clear) . ' url(s): ' . implode(', ', $urls_to_clear);
      $this->logger()->error($msg);
    }
  }

}

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
    $this->eventDispatcher->dispatch($event, AkamaiPurgeEvents::PURGE_CREATION);
    $urls_to_clear = $event->data;

    // Mass: Added an array_unique() here as quick fix for dupes.
    $urls_to_clear = array_unique(array_filter($urls_to_clear));
    // Mass: Add back normalization just in case that caused the earlier errors.
    // $urls_to_clear = $this->client->normalizeUrls($urls_to_clear);
    // Log if a bad URL is passed.
    foreach ($urls_to_clear as $key => $url) {
      if (!is_string($url)) {
        $this->logger()->warning('Skipping the purge of a non-string: ' . var_export($url, TRUE));
        unset($urls_to_clear[$key]);
      }
    }

    // json_encode() needs numeric indices without holes.
    // https://www.sitepoint.com/community/t/json-encode-sometimes-does-or-does-not-add-keys-for-array-elements/116226/2
    $urls_to_clear = array_values($urls_to_clear);

    // Mass: Go right to purgeRequest(), bypassing unwanted check in purgeUrls().
    $method = new \ReflectionMethod($this->client, 'purgeRequest');
    $method->setAccessible(TRUE);

    // Chunk the urls in order to avoid timeouts due to edgeworker.
    foreach (array_chunk($urls_to_clear, 6) as $chunk) {
      $response = $method->invoke($this->client, $chunk);
      if ($response) {
        // Now mark all URLs as cleared.
        foreach ($invalidations as $key => $invalidation) {
          if (in_array($invalidation->getUrl()->toString(), $chunk)) {
            $invalidation->setState(InvalidationInterface::SUCCEEDED);
          }
        }
      }
      else {
        $msg = 'AkamaiPurger: Failed to purge ' . count($chunk) . ' url(s): ' . implode("\n", $chunk);
        $this->logger()->error($msg);
      }
    }
  }

}

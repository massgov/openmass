<?php

namespace Drupal\mass_caching;

use Drupal\akamai\Event\AkamaiPurgeEvents;
use Drupal\akamai\Plugin\Purge\Purger\AkamaiPurger as BaseAkamaiPurger;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;

/**
 * Honor URLs that are passed, *without normalization*.
 *
 * This purger class gets swapped in via mass_caching_purge_purgers_alter().
 */
class AkamaiPurger extends BaseAkamaiPurger {

  use AkamaiPurgeRetryTrait;

  /**
   * {@inheritdoc}
   */
  public function invalidate(array $invalidations) {
    $targets = [];
    $urls_to_clear = [];
    foreach ($invalidations as $key => $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      $invalidation_type = $invalidation->getPluginId();
      switch ($invalidation_type) {
        case 'path':
          $target = $this->normalizePath($invalidation->getExpression());
          break;

        case 'url':
          $target = $invalidation->getUrl()->toString();
          break;

        default:
          $invalidation->setState(InvalidationInterface::NOT_SUPPORTED);
          continue 2;
      }
      $targets[$key] = $target;
      // A leading slash prevents relative paths from being hashed as tags.
      $urls_to_clear[$key] = $invalidation_type === 'path' && $target !== '' ? '/' . ltrim($target, '/') : $target;
    }

    $event = new AkamaiPurgeEvents($urls_to_clear);
    $this->eventDispatcher->dispatch($event, AkamaiPurgeEvents::PURGE_CREATION);
    $purge_urls = [];
    foreach ($event->data as $key => $url) {
      if (!is_string($url)) {
        $this->logger()->warning('Skipping the purge of a non-string: ' . var_export($url, TRUE));
        continue;
      }
      // Restore unchanged event entries to the original Akamai targets.
      if (isset($targets[$key]) && $url === $urls_to_clear[$key]) {
        $url = $targets[$key];
      }
      $purge_urls[] = $url;
    }

    $purge_urls = array_values(array_unique(array_filter($purge_urls)));

    // Mass: Go right to purgeRequest(), bypassing unwanted checks in
    // purgeUrls().
    $method = new \ReflectionMethod($this->client, 'purgeRequest');

    // Chunk the urls in order to avoid timeouts due to edgeworker.
    $purge_results = [];
    foreach (array_chunk($purge_urls, 6) as $chunk) {
      try {
        $response = $method->invoke($this->client, $chunk);
      }
      catch (\Throwable $e) {
        $this->logger()->error('Akamai URL purge request failed: @message', ['@message' => $e->getMessage()]);
        $response = FALSE;
      }
      foreach ($chunk as $url) {
        $purge_results[$url] = (bool) $response;
      }
    }

    foreach ($targets as $key => $target) {
      if ($purge_results[$target] ?? FALSE) {
        $invalidations[$key]->setState(InvalidationInterface::SUCCEEDED);
      }
      else {
        $this->recordFailedPurgeAttempts([$invalidations[$key]], $this->logger(), 'URL');
      }
    }
  }

}

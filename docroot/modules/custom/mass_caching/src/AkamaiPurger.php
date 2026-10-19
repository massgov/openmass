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
      if (!in_array($invalidation_type, ['path', 'url'], TRUE)) {
        $invalidation->setState(InvalidationInterface::NOT_SUPPORTED);
        continue;
      }
      $target = $invalidation_type === 'path'
        ? $this->normalizePath($invalidation->getExpression())
        : $invalidation->getUrl()->toString();
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
    $purge_results = $this->sendPurgeRequests($purge_urls);
    foreach ($targets as $key => $target) {
      if ($purge_results[$target] ?? FALSE) {
        $invalidations[$key]->setState(InvalidationInterface::SUCCEEDED);
      }
      else {
        $this->recordFailedPurgeAttempts([$invalidations[$key]], $this->logger(), 'URL');
      }
    }
  }

  /**
   * Sends URLs in chunks and records each URL's request result.
   */
  private function sendPurgeRequests(array $urls): array {
    $purge_results = [];
    // Limit each Akamai request to six URLs.
    foreach (array_chunk($urls, 6) as $chunk_urls) {
      try {
        $succeeded = $this->purgeUrls($chunk_urls);
      }
      catch (\Throwable $e) {
        $this->logger()->error('Akamai URL purge request failed: @message', ['@message' => $e->getMessage()]);
        $succeeded = FALSE;
      }
      foreach ($chunk_urls as $url) {
        $purge_results[$url] = $succeeded;
      }
    }
    return $purge_results;
  }

  /**
   * Sends one URL chunk to Akamai.
   */
  private function purgeUrls(array $urls): bool {
    // Bypass the unwanted checks in AkamaiClientBase::purgeUrls().
    $method = new \ReflectionMethod($this->client, 'purgeRequest');
    return (bool) $method->invoke($this->client, $urls);
  }

}

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
    $invalidations_by_url = $this->groupInvalidationsByUrl($invalidations);
    $purge_urls_by_source = [];
    $combined_purge_urls = [];

    // Dispatch each source URL separately so event replacements and additions
    // remain associated with the invalidations that requested them.
    foreach ($invalidations_by_url as $url => $url_invalidations) {
      $purge_urls_by_source[$url] = $this->preparePurgeUrls($url, $url_invalidations[0]->getPluginId() === 'path');
      foreach ($purge_urls_by_source[$url] as $purge_url) {
        $combined_purge_urls[$purge_url] = $purge_url;
      }
    }

    $purge_results = $this->sendPurgeRequests(array_values($combined_purge_urls));
    $this->recordInvalidationResults($invalidations_by_url, $purge_urls_by_source, $purge_results);
  }

  /**
   * Records each invalidation's result from the URLs produced for it.
   */
  private function recordInvalidationResults(array $invalidations_by_url, array $purge_urls_by_source, array $purge_results): void {
    $successful_urls = array_keys($purge_results, TRUE, TRUE);
    foreach ($invalidations_by_url as $url => $url_invalidations) {
      if (!$purge_urls_by_source[$url] || array_diff($purge_urls_by_source[$url], $successful_urls)) {
        $this->recordFailedPurgeAttempts($url_invalidations, $this->logger(), 'URL');
        continue;
      }
      foreach ($url_invalidations as $invalidation) {
        $invalidation->setState(InvalidationInterface::SUCCEEDED);
      }
    }
  }

  /**
   * Sends URLs in chunks and records each URL's request result.
   */
  private function sendPurgeRequests(array $combined_purge_urls): array {
    $purge_results = [];
    // Limit each Akamai request to six URLs.
    foreach (array_chunk($combined_purge_urls, 6) as $chunk_urls) {
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
   * Groups invalidations by the URL sent to Akamai.
   */
  private function groupInvalidationsByUrl(array $invalidations): array {
    $invalidations_by_url = [];
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      if (!in_array($invalidation->getPluginId(), ['path', 'url'], TRUE)) {
        $invalidation->setState(InvalidationInterface::NOT_SUPPORTED);
        continue;
      }
      $url = $this->getInvalidationUrl($invalidation);
      $invalidations_by_url[$url][] = $invalidation;
    }
    return $invalidations_by_url;
  }

  /**
   * Returns the URL sent to Akamai for an invalidation.
   */
  private function getInvalidationUrl(InvalidationInterface $invalidation): string {
    if ($invalidation->getPluginId() === 'path') {
      return $this->normalizePath($invalidation->getExpression());
    }
    if ($invalidation->getPluginId() === 'url') {
      // Honor URL invalidations without normalization.
      return $invalidation->getUrl()->toString();
    }
    return '';
  }

  /**
   * Allows subscribers to alter one source URL and removes invalid values.
   */
  private function preparePurgeUrls(string $url, bool $is_path): array {
    if ($url === '') {
      return [];
    }

    // A leading slash lets the shared purge-event subscriber recognize paths
    // instead of hashing them as tags. Restore the original path for Akamai.
    $event_url = $is_path ? '/' . ltrim($url, '/') : $url;
    $event = new AkamaiPurgeEvents([$event_url]);
    $this->eventDispatcher->dispatch($event, AkamaiPurgeEvents::PURGE_CREATION);

    $urls = array_unique(array_filter($event->data));
    foreach ($urls as $key => $purge_url) {
      if (!is_string($purge_url)) {
        $this->logger()->warning('Skipping the purge of a non-string: ' . var_export($purge_url, TRUE));
        unset($urls[$key]);
      }
      elseif ($is_path && $purge_url === $event_url) {
        $urls[$key] = $url;
      }
    }
    return array_values($urls);
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

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
    // Keep each invalidation associated with the URL it requests.
    $invalidations_by_url = $this->groupInvalidationsByUrl($invalidations);
    // Send the URLs remaining after subscribers and validation alter the list.
    $purge_urls = $this->preparePurgeUrls(array_keys($invalidations_by_url));

    // URLs removed by subscribers or validation never reach a request chunk.
    foreach (array_diff(array_keys($invalidations_by_url), $purge_urls) as $url) {
      $this->recordFailedPurgeAttempts($invalidations_by_url[$url], $this->logger(), 'URL');
    }

    // Limit each Akamai request to six URLs.
    foreach (array_chunk($purge_urls, 6) as $chunk_urls) {
      // Keep this request's result tied to its matching invalidations.
      $chunk_invalidations = $this->getInvalidationsForChunk($chunk_urls, $invalidations_by_url);
      try {
        $succeeded = $this->purgeUrls($chunk_urls);
      }
      catch (\Throwable $e) {
        $this->logger()->error('Akamai URL purge request failed: @message', ['@message' => $e->getMessage()]);
        $succeeded = FALSE;
      }
      if ($succeeded) {
        foreach ($chunk_invalidations as $invalidation) {
          $invalidation->setState(InvalidationInterface::SUCCEEDED);
        }
        continue;
      }

      $this->recordFailedPurgeAttempts($chunk_invalidations, $this->logger(), 'URL');
    }
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
   * Allows subscribers to alter URLs and removes invalid values.
   */
  private function preparePurgeUrls(array $urls): array {
    $event = new AkamaiPurgeEvents($urls);
    $this->eventDispatcher->dispatch($event, AkamaiPurgeEvents::PURGE_CREATION);

    $urls = array_unique(array_filter($event->data));
    foreach ($urls as $key => $url) {
      if (!is_string($url)) {
        $this->logger()->warning('Skipping the purge of a non-string: ' . var_export($url, TRUE));
        unset($urls[$key]);
      }
    }
    return array_values($urls);
  }

  /**
   * Gets the invalidations represented by a request chunk.
   */
  private function getInvalidationsForChunk(array $chunk, array $invalidations_by_url): array {
    $invalidations = [];
    foreach ($chunk as $url) {
      array_push($invalidations, ...($invalidations_by_url[$url] ?? []));
    }
    return $invalidations;
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

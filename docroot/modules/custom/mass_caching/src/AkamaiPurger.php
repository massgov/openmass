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
    $invalidation_groups = $this->groupInvalidationsBySource($invalidations);
    $purge_urls_by_source = [];
    $combined_purge_urls = [];

    // Dispatch each source path or URL separately so replacements and additions
    // remain associated with the invalidations that requested them.
    foreach ($invalidation_groups as $source_key => $group) {
      $purge_urls_by_source[$source_key] = $this->preparePurgeUrls($group['target'], $group['type'] === 'path');
      foreach ($purge_urls_by_source[$source_key] as $purge_url) {
        $combined_purge_urls[$purge_url] = $purge_url;
      }
    }

    $purge_results = $this->sendPurgeRequests(array_values($combined_purge_urls));
    $this->recordInvalidationResults($invalidation_groups, $purge_urls_by_source, $purge_results);
  }

  /**
   * Records each invalidation's result from the URLs produced for it.
   */
  private function recordInvalidationResults(array $invalidation_groups, array $purge_urls_by_source, array $purge_results): void {
    $successful_urls = array_keys($purge_results, TRUE, TRUE);
    foreach ($invalidation_groups as $source_key => $group) {
      if (!$purge_urls_by_source[$source_key] || array_diff($purge_urls_by_source[$source_key], $successful_urls)) {
        $this->recordFailedPurgeAttempts($group['invalidations'], $this->logger(), 'URL');
        continue;
      }
      foreach ($group['invalidations'] as $invalidation) {
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
   * Groups invalidations by plugin type and target path or URL.
   *
   * Each group carries its type explicitly, so preparing its purge payload
   * never depends on inspecting a representative invalidation.
   *
   * @return array
   *   Groups keyed by type and target, each containing the type, target,
   *   and matching invalidations.
   */
  private function groupInvalidationsBySource(array $invalidations): array {
    $groups = [];
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      $type = $invalidation->getPluginId();
      if (!in_array($type, ['path', 'url'], TRUE)) {
        $invalidation->setState(InvalidationInterface::NOT_SUPPORTED);
        continue;
      }
      $target = $this->getInvalidationTarget($invalidation);
      $source_key = $type . ':' . $target;
      if (!isset($groups[$source_key])) {
        $groups[$source_key] = [
          'type' => $type,
          'target' => $target,
          'invalidations' => [],
        ];
      }
      $groups[$source_key]['invalidations'][] = $invalidation;
    }
    return $groups;
  }

  /**
   * Returns the target path or URL sent to Akamai for an invalidation.
   */
  private function getInvalidationTarget(InvalidationInterface $invalidation): string {
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
   * Alters one source path or URL via subscribers and removes invalid values.
   */
  private function preparePurgeUrls(string $target, bool $is_path): array {
    if ($target === '') {
      return [];
    }

    // A leading slash lets the shared purge-event subscriber recognize paths
    // instead of hashing them as tags. Restore the original path for Akamai.
    $event_url = $is_path ? '/' . ltrim($target, '/') : $target;
    $event = new AkamaiPurgeEvents([$event_url]);
    $this->eventDispatcher->dispatch($event, AkamaiPurgeEvents::PURGE_CREATION);

    $urls = array_unique(array_filter($event->data));
    foreach ($urls as $key => $purge_url) {
      if (!is_string($purge_url)) {
        $this->logger()->warning('Skipping the purge of a non-string: ' . var_export($purge_url, TRUE));
        unset($urls[$key]);
      }
      elseif ($is_path && $purge_url === $event_url) {
        $urls[$key] = $target;
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

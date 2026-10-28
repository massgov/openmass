<?php

declare(strict_types=1);

namespace Drupal\mass_caching;

use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use Psr\Log\LoggerInterface;

/**
 * Tracks failed Akamai purge attempts and logs exhausted retries.
 *
 * After three failed attempts, marks the invalidation as succeeded to remove
 * it from the queue.
 */
trait AkamaiPurgeRetryTrait {

  /**
   * The maximum number of failed attempts before abandoning an invalidation.
   */
  public const MAX_ATTEMPTS = 3;

  /**
   * Records failed attempts and removes invalidations at the retry limit.
   */
  protected function recordFailedPurgeAttempts(array $invalidations, LoggerInterface $logger, string $type): void {
    foreach ($invalidations as $invalidation) {
      $attempts = (int) $invalidation->getProperty('attempts') + 1;
      $invalidation->setProperty('attempts', $attempts);

      if ($attempts < self::MAX_ATTEMPTS) {
        $invalidation->setState(InvalidationInterface::FAILED);
        continue;
      }

      $logger->error('Akamai @type purge failed after @attempts attempts for %item. Removing the invalidation from the queue.', [
        '@type' => $type,
        '@attempts' => $attempts,
        '%item' => $invalidation->getExpression(),
      ]);
      $invalidation->setState(InvalidationInterface::SUCCEEDED);
    }
  }

}

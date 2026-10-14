<?php

declare(strict_types=1);

namespace Drupal\mass_caching;

use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;

/**
 * Limits retries for failed Akamai cache tag invalidations.
 *
 * This purger class gets swapped in via mass_caching_purge_purgers_alter().
 */
class AkamaiTagPurger extends \Drupal\akamai\Plugin\Purge\Purger\AkamaiTagPurger {

  /**
   * The maximum number of failed attempts before abandoning an invalidation.
   */
  public const MAX_ATTEMPTS = 3;

  /**
   * The invalidation property used to persist the attempt count.
   */
  public const ATTEMPTS_PROPERTY = 'attempts';

  /**
   * {@inheritdoc}
   */
  public function invalidate(array $invalidations) {
    parent::invalidate($invalidations);

    foreach ($invalidations as $invalidation) {
      if ($invalidation->getState() !== InvalidationInterface::FAILED) {
        continue;
      }

      $attempts = (int) $invalidation->getProperty(self::ATTEMPTS_PROPERTY) + 1;
      $invalidation->setProperty(self::ATTEMPTS_PROPERTY, $attempts);

      if ($attempts >= self::MAX_ATTEMPTS) {
        $this->logger->error('Akamai tag purge failed after @attempts attempts for cache tag %tag. Removing the invalidation from the queue.', [
          '@attempts' => $attempts,
          '%tag' => $invalidation->getExpression(),
        ]);
        $invalidation->setState(InvalidationInterface::SUCCEEDED);
      }
    }
  }

}

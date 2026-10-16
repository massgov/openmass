<?php

declare(strict_types=1);

namespace Drupal\mass_caching;

use Drupal\akamai\Plugin\Purge\Purger\AkamaiTagPurger as BaseAkamaiTagPurger;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;

/**
 * Limits retries for failed Akamai cache tag invalidations.
 *
 * This purger class gets swapped in via mass_caching_purge_purgers_alter().
 */
class AkamaiTagPurger extends BaseAkamaiTagPurger {

  use AkamaiPurgeRetryTrait;

  /**
   * {@inheritdoc}
   */
  public function invalidate(array $invalidations) {
    parent::invalidate($invalidations);

    $failed_invalidations = [];
    foreach ($invalidations as $invalidation) {
      if ($invalidation->getState() === InvalidationInterface::FAILED) {
        $failed_invalidations[] = $invalidation;
      }
    }

    $this->recordFailedPurgeAttempts(
      $failed_invalidations,
      $this->logger,
      'cache tag'
    );
  }

}

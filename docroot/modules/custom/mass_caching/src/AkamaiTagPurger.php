<?php

declare(strict_types=1);

namespace Drupal\mass_caching;

use Drupal\akamai\Plugin\Purge\Purger\AkamaiTagPurger as BaseAkamaiTagPurger;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use GuzzleHttp\Exception\TransferException;

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
    try {
      parent::invalidate($invalidations);
    }
    catch (TransferException $e) {
      // The client allows connection failures to escape. Count them toward the
      // retry limit so repeated timeouts cannot block the queue indefinitely.
      $this->logger->error('Akamai cache tag purge request failed: @message', ['@message' => $e->getMessage()]);
      foreach ($invalidations as $invalidation) {
        $invalidation->setState(InvalidationInterface::FAILED);
      }
    }

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

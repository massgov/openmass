<?php

namespace Drupal\mass_caching;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Site\Settings;
use Drupal\purge\Plugin\Purge\Invalidation\Exception\TypeUnsupportedException;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationsServiceInterface;
use Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface;
use Drupal\purge\Plugin\Purge\Queuer\QueuersServiceInterface;

/**
 * Helper service to do manual path and URL invalidation.
 *
 * Can be used from custom code by calling:
 *     \Drupal::service('manual_purger')->purgePath('/foo/bar')
 */
class ManualPurger {

  private $queuers;
  private $invalidations;
  private $queue;
  private $settings;

  /**
   * Constructor.
   */
  public function __construct(QueuersServiceInterface $queuers, InvalidationsServiceInterface $invalidations, QueueServiceInterface $queue, Settings $settings) {
    $this->queuers = $queuers;
    $this->invalidations = $invalidations;
    $this->queue = $queue;
    $this->settings = $settings;
  }

  /**
   * Purge a single relative path.
   *
   * Note: Acquia's purger doesn't support this, but we fake it by accumulating
   * a list of schemes and hosts for each environment.
   *
   * @param string $path
   *   The relative path to purge.
   */
  public function purgePath(string $path) {
    if ($queuer = $this->getQueuer()) {
      $invalidations = [];
      try {
        foreach ($this->getSchemes() as $scheme) {
          foreach ($this->getHosts() as $host) {
            $url = sprintf('%s://%s%s', $scheme, $host, $path);
            $invalidations[] = $this->invalidations->get('url', $url);
          }
        }
      }
      catch (TypeUnsupportedException $e) {
        // We can't do anything if this kind of invalidation is disabled.
        return;
      }
      catch (PluginNotFoundException $e) {
        // We can't do anything if this kind of invalidation is disabled.
        return;
      }
      if ($invalidations) {
        $this->queue->add($queuer, $invalidations);
      }
    }
  }

  /**
   * Purge a single absolute URL.
   *
   * @param string $url
   *   The absolute URL to clear.
   */
  public function purgeUrl(string $url) {
    if ($queuer = $this->getQueuer()) {
      try {
        $invalidation = $this->invalidations->get('url', $url);
      }
      catch (TypeUnsupportedException $e) {
        // We can't do anything if this kind of invalidation is disabled.
        return;
      }
      catch (PluginNotFoundException $e) {
        // We can't do anything if this kind of invalidation is disabled.
        return;
      }
      $this->queue->add($queuer, [$invalidation]);
    }
  }

  /**
   * Get the list of domains applicable to this site.
   *
   * These domains will be cleared when using path purging.
   *
   * @return array
   *   An array of domains.
   */
  private function getHosts() {
    return $this->settings->get('mass_caching.hosts', [
      parse_url(\Drupal::request()->getUri(), PHP_URL_HOST),
    ]);
  }

  /**
   * Get the list of URL schemes applicable to this site.
   *
   * These schemes will be cleared when using path purging.
   *
   * @return array
   *   An array of schemes.
   */
  private function getSchemes() {
    return $this->settings->get('mass_caching.schemes', [
      parse_url(\Drupal::request()->getUri(), PHP_URL_SCHEME),
    ]);
  }

  /**
   * Get the manual queuer, if it is enabled.
   *
   * @return \Drupal\purge\Plugin\Purge\Queuer\QueuerInterface|false
   *   The Queuer, or FALSE if not enabled.
   */
  private function getQueuer() {
    return $this->queuers->get('manual');
  }

}

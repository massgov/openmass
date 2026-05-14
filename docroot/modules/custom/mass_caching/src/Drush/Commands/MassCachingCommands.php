<?php

namespace Drupal\mass_caching\Drush\Commands;

use Drupal\mass_caching\ManualPurger;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for Mass.gov caching.
 */
final class MassCachingCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Constructs a MassCachingCommands object.
   */
  public function __construct(
    protected ManualPurger $manualPurger,
  ) {
    parent::__construct();
  }

  /**
   * Enqueue purge invalidations for a relative path.
   *
   * @param string $path
   *   The path to purge. Use "/" for the homepage.
   *
   * @throws \InvalidArgumentException
   *
   * @command mass-caching:purge-path
   */
  public function purgePath(string $path): void {
    if ($path === '/') {
      $path = '';
    }

    if ($path !== '' && !str_starts_with($path, '/')) {
      throw new \InvalidArgumentException('Path must be relative and begin with "/".');
    }

    $this->manualPurger->purgePath($path);
    $this->logger()->success(dt('Purge enqueued for @path.', [
      '@path' => $path === '' ? '/' : $path,
    ]));
  }

}

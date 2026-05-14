<?php

declare(strict_types=1);

namespace Drupal\mass_caching\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\State\StateInterface;
use Drupal\mass_caching\AkamaiPurger;

/**
 * Hook implementations for purge plugin definitions.
 */
class PurgerHooks {

  /**
   * Constructs a PurgerHooks object.
   */
  public function __construct(
    protected StateInterface $state,
  ) {}

  /**
   * Acquia Purger and Akamai need conditional disable, so we use plugin alter.
   *
   * This alter is added by a patch to purge module. See
   * https://www.drupal.org/project/purge/issues/2757155#comment-14335663.
   *
   * @param array $definitions
   *   All plugin definitions.
   */
  #[Hook('purge_purgers_alter')]
  public function purgePurgersAlter(array &$definitions): void {
    if ($this->state->get('mass_caching.purger', FALSE)) {
      // For now, we enable this purger via State. When disabled, the standard
      // Akamai purger is active.
      if (isset($definitions['akamai'])) {
        $definitions['akamai']['class'] = AkamaiPurger::class;
      }
    }

    // Change handled types depending on env.
    $purger_enabled = [
      'akamai' => FALSE,
      'acquia_purge' => FALSE,
    ];

    $env = getenv('AH_SITE_ENVIRONMENT');
    if ($env) {
      // We are in an Acquia env.
      $purger_enabled['acquia_purge'] = TRUE;
      if (in_array($env, ['test', 'prod'], TRUE)) {
        $purger_enabled['akamai'] = TRUE;
      }
    }

    if ($purger = getenv('MASS_PURGERS')) {
      // Force the specified purger to on. Used for local testing.
      if (array_key_exists($purger, $purger_enabled)) {
        $purger_enabled[$purger] = TRUE;
      }
    }

    foreach ($purger_enabled as $name => $enabled) {
      // We need to run queue invalidations during testing so that tests pass
      // like AutomatedPurgingTest.
      if (!$enabled && isset($definitions[$name]) && !defined('PHPUNIT_COMPOSER_INSTALL')) {
        // To disable a purger, make it capable of an operation we don't use.
        // It can't be an empty array as we get ValueError in
        // \Drupal\purge\Plugin\Purge\Purger\CapacityTracker::getTimeHintTotal.
        // Purge bug reported at:
        // https://www.drupal.org/project/purge/issues/3298855
        $definitions[$name]['types'] = ['everything'];
      }
    }
  }

}

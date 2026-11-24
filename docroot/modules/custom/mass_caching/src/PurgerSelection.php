<?php

declare(strict_types=1);

namespace Drupal\mass_caching;

/**
 * Determines which purgers are enabled for an environment.
 */
final class PurgerSelection {

  public const PURGERS = ['akamai', 'akamai_tag', 'acquia_purge'];

  /**
   * Returns enabled purger IDs for an Acquia environment and local override.
   *
   * @return string[]
   *   The enabled purger IDs.
   */
  public static function enabledPurgers(string|false $environment, string|false $override): array {
    $enabled = [];

    if ($environment) {
      $enabled[] = 'acquia_purge';
      if (in_array($environment, ['test', 'prod'], TRUE)) {
        $enabled[] = 'akamai';
        $enabled[] = 'akamai_tag';
      }
    }

    if ($override) {
      foreach (explode(',', $override) as $purger) {
        $purger = trim($purger);
        if (in_array($purger, self::PURGERS, TRUE) && !in_array($purger, $enabled, TRUE)) {
          $enabled[] = $purger;
        }
      }
    }

    return $enabled;
  }

}

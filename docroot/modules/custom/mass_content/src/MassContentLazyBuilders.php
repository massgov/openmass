<?php

namespace Drupal\mass_content;

use Drupal\Core\Render\Markup;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Defines a service for #lazy_builder callbacks.
 */
class MassContentLazyBuilders implements TrustedCallbackInterface {

  /**
   * Return a render array containing the meta robots HTML.
   *
   * @param $search
   * @param $search_nosnippet
   *
   * @return array
   */
  public function renderRobots($search, $search_nosnippet) {
    if ($search) {
      $values = ['noindex', 'nofollow'];
    }
    elseif ($search_nosnippet) {
      $values = ['nosnippet'];
    }
    else {
      return [];
    }

    $build = [
      '#markup' => Markup::create(implode(', ', $values)),
      '#cache' => [
        'max-age' => 0,
      ],
    ];
    return $build;
  }

  public static function trustedCallbacks() {
    return ['renderRobots'];
  }

}

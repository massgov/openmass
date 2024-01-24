<?php

declare(strict_types=1);

namespace Drupal\mass_utility;

use Behat\Mink\Session;

/**
 * Enable debug cachability headers in a DTT test.
 *
 * This class:
 *   - Provides a home for the new header we define to trigger cache debug
 *     headers in tests. We can't use an event subscriber in the test because
 *     the request back to the site will not execute in the test context, and
 *     won't have a subscriber enabled. Drupal's built-in functional tests
 *     work around this by editing services.yml directly on disk. This method
 *     avoids having to write files to execute the test.
 *   - Provides a method to enable cache tags for the remainder of the test
 *     case.
 *
 * This class can't be a trait since we want to access the header in
 * settings.php without having to somehow instantiate the class.
 *
 * @see docroot/sites/default/settings.vm.php
 */
class DebugCachability {

  /**
   * The header used to trigger debug headers.
   */
  public const HEADER = 'X-Request-Debug-Cachability-Headers';

  /**
   * Enable cache debugging headers for the remainder of this session.
   *
   * @param \Behat\Mink\Session $session
   */
  public function requestDebugCachabilityHeaders(Session $session): void {
    $session->setRequestHeader(self::HEADER, "1");
  }

}

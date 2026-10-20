<?php

namespace MassGov\Dtt;

use Drupal\Core\Url;

/**
 * Overrides test authentication helpers for OpenID Connect.
 */
trait AuthTrait {

  /**
   * Logs a test user out through OpenID Connect.
   *
   * openid_connect replaces user.logout with a controller that ends the Drupal
   * session. There is no confirm form on that route.
   */
  protected function drupalLogout(): void {
    $this->drupalGet(Url::fromRoute('openid_connect.logout'));
    $this->drupalResetSession();
  }

}

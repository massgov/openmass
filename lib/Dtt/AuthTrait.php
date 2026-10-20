<?php

namespace MassGov\Dtt;

use Drupal\Core\Url;

/**
 * Overrides test authentication helpers for OpenID Connect.
 */
trait AuthTrait {

  /**
   * Logs a test user out through the core confirm form.
   *
   * openid_connect replaces user.logout with a redirect controller. A GET of
   * that route ends the PHP session but the test browser keeps the old
   * session cookie, so the next login still acts as the previous user.
   * user.logout.confirm is unchanged and submitting it clears the cookie.
   */
  protected function drupalLogout(): void {
    $destination = Url::fromRoute('user.page')->toString();
    $this->drupalGet(Url::fromRoute('user.logout.confirm', options: [
      'query' => ['destination' => $destination],
    ]));
    // Submit by button name. The local login form is not on the page this
    // site shows after logout, so the username and password fields are not
    // a usable confirmation.
    $this->submitForm([], 'op', 'user-logout-confirm');
    $this->drupalResetSession();
  }

}

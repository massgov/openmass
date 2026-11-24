<?php

namespace MassGov\Dtt;

use Drupal\Core\Url;

/**
 * Overrides test authentication helpers for OpenID Connect.
 */
trait AuthTrait {

  /**
   * Logs a test user out of the server session and the Mink browser.
   *
   * openid_connect replaces user.logout with a redirect that calls
   * user_logout() and leaves the test client's cookie in place, so the next
   * login still acts as the previous user. Submitting user.logout.confirm
   * destroys the PHP session and clears that cookie when the form is in the
   * response. CircleCI sometimes returns another submit button and no confirm
   * form. Request user.logout while the session cookie is still sent, which
   * destroys the PHP session, then drop the Mink cookies.
   */
  protected function drupalLogout(): void {
    $destination = Url::fromRoute('user.page')->toString();
    $this->drupalGet(Url::fromRoute('user.logout.confirm', options: [
      'query' => ['destination' => $destination],
    ]));
    if ($this->getSession()->getPage()->find('css', '#user-logout-confirm')) {
      $this->submitForm([], 'op', 'user-logout-confirm');
    }
    else {
      $this->drupalGet(Url::fromRoute('user.logout'));
    }
    $this->getSession()->reset();
    $this->drupalResetSession();
  }

}

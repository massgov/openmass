<?php

namespace MassGov\Dtt;

use Drupal\Core\Url;

/**
 * Overrides test authentication helpers for OpenID Connect.
 */
trait AuthTrait {

  /**
   * Logs a test user out of the Mink browser.
   *
   * openid_connect replaces user.logout with a redirect that ends the PHP
   * session without clearing the test client's cookie, so the next login
   * still acts as the previous user. user.logout.confirm does clear the
   * cookie when that form is in the response. CircleCI sometimes returns a
   * page that has another submit button and no confirm form, and requiring
   * the form then aborts the next login. Drop the Mink cookies either way.
   */
  protected function drupalLogout(): void {
    $destination = Url::fromRoute('user.page')->toString();
    $this->drupalGet(Url::fromRoute('user.logout.confirm', options: [
      'query' => ['destination' => $destination],
    ]));
    if ($this->getSession()->getPage()->find('css', '#user-logout-confirm')) {
      $this->submitForm([], 'op', 'user-logout-confirm');
    }
    $this->getSession()->reset();
    $this->drupalResetSession();
  }

}

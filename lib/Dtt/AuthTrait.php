<?php

namespace MassGov\Dtt;

use Drupal\Core\Url;

/**
 * Overrides test authentication helpers for OpenID Connect.
 */
trait AuthTrait {

  /**
   * Logs out through the OpenID Connect confirmation form.
   */
  protected function drupalLogout(): void {
    $destination = Url::fromRoute('user.page')->toString();
    $this->drupalGet(Url::fromRoute('openid_connect.logout.confirm', options: [
      'query' => ['destination' => $destination],
    ]));
    $this->submitForm([], 'op', 'openid-connect-user-logout');
    $this->drupalResetSession();
  }

}

<?php

namespace Drupal\Tests\mass_utility\Traits;

use Drupal\user\Entity\User;

/**
 * Methods related to users to be used in phpunit tests.
 *
 * @package Drupal\Tests\mass_utility\Traits
 */
trait UserTestTrait {

  /**
   * This site cannot use drupalLogin() because of TFA. Here is the replacement.
   *
   * @param \Drupal\user\Entity\User $account
   *   The target user account.
   */
  public function massgovLogin(User $account) {
    $login = user_pass_reset_url($account) . '/login';
    $this->getSession()->visit($login);

    // Do same as bottom of \Drupal\Tests\UiHelperTrait::drupalLogin.
    $account->sessionId = $this->getSession()->getCookie(\Drupal::service('session_configuration')->getOptions(\Drupal::request())['name']);
    $this->assertTrue($this->drupalUserIsLoggedIn($account));
    $this->loggedInUser = $account;
    $this->container->get('current_user')->setAccount($account);
  }

}

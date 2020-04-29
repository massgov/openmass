<?php

namespace Drupal\mass_admin_pages\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Provides route responses for the Example module.
 */
class AuthorHomePageController extends ControllerBase {

  /**
   * Returns a home page to welcome authors.
   *
   * @return array
   *   A simple renderable array.
   */
  public function authorHome() {
    $user_data = \Drupal::service('user.data');
    if (empty($user_data->get('tfa', \Drupal::currentUser()->id(), 'tfa_totp_seed'))) {
      /** @var \Drupal\Core\Messenger\MessengerInterface $messenger */
      $messenger = \Drupal::messenger();
      $messenger->addError(t('
      <p><strong>IMPORTANT:</strong></p>
      <p>Please <a href=":url">set up 2-factor authentication</a> NOW or you will not be able to log in again.</p>
    ', [':url' => '/user/' . \Drupal::currentUser()->id() . '/security/tfa']));
    }
    return [];
  }

}

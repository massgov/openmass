<?php

namespace Drupal\mass_utility\Plugin\Mail;

use Drupal\mailchimp_transactional\Plugin\Mail\Mail;

/**
 * Allow Drupal mailsystem to use Mailchimp Transactional when sending emails.
 *
 * @Mail(
 *   id = "mass_mail",
 *   label = @Translation("Mass mailer"),
 *   description = @Translation("Mass customized - sends the message through Mailchimp Transactional.")
 * )
 */
class MassMail extends Mail {

  /**
   * Concatenate and wrap the email body for either plain-text or HTML emails.
   *
   * @param array $message
   *   A message array, as described in hook_mail_alter().
   *
   * @return array
   *   The formatted $message.
   */
  public function format(array $message) {
    // Join the body array into one string.
    if (is_array($message['body'])) {
      // Do nothing. The parent adds weird '&#13;' at end of many lines.
      $message['body'] = implode("\n\n", $message['body']);
    }

    return $message;
  }

}

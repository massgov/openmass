<?php

namespace Drupal\mass_utility\Plugin\Mail;

use Drupal\sendgrid\Plugin\Mail\SendgridMail;

/**
 * Sends Mass.gov email through SendGrid.
 *
 * @Mail(
 *   id = "mass_mail",
 *   label = @Translation("Mass mailer"),
 *   description = @Translation("Mass customized - sends the message through SendGrid.")
 * )
 */
class MassMail extends SendgridMail {

  /**
   * {@inheritdoc}
   */
  protected function buildMessage(array $message) {
    foreach ($message['headers'] ?? [] as $name => $value) {
      if (strtolower($name) === 'reply-to' && is_string($value)) {
        $message['reply-to'] = $value;
        break;
      }
    }
    if (!is_string($message['reply-to'] ?? NULL)) {
      $message['reply-to'] = NULL;
    }

    $sendgrid_message = parent::buildMessage($message);
    $sendgrid_message['from_email'] = 'noreply@noreply.mass.gov';
    $sendgrid_message['from_name'] = 'Mass.gov';

    return $sendgrid_message;
  }

}

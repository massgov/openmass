<?php

namespace Drupal\mass_sendgrid\Plugin\Mail;

use Drupal\sendgrid\Plugin\Mail\SendgridMail;

/**
 * Sends Mass.gov email through SendGrid.
 *
 * @Mail(
 *   id = "mass_sendgrid_mail",
 *   label = @Translation("Mass SendGrid mailer"),
 *   description = @Translation("Sends Mass.gov email through SendGrid.")
 * )
 */
class MassSendgridMail extends SendgridMail {

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
    $from_email = $this->configFactory
      ->get('mass_sendgrid.settings')
      ->get('from_email');
    if (!is_string($from_email) || $from_email === '') {
      throw new \LogicException('The mass_sendgrid.settings from_email configuration value is required.');
    }
    $sendgrid_message['from_email'] = $from_email;
    $sendgrid_message['from_name'] = 'Mass.gov';

    return $sendgrid_message;
  }

}

<?php

namespace Drupal\mass_utility\Plugin\Mail;

use Drupal\amazon_ses\AmazonSesHandlerInterface;
use Drupal\amazon_ses\MessageBuilderInterface;
use Drupal\amazon_ses\Plugin\Mail\AmazonSes;
use Drupal\Component\Utility\Html;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formats Mass.gov email and sends it through Amazon SES.
 *
 * @Mail(
 *   id = "mass_mail",
 *   label = @Translation("Mass mailer"),
 *   description = @Translation("Formats Mass.gov email and sends it through Amazon SES.")
 * )
 */
class MassMail extends AmazonSes {

  /**
   * Constructs the Mass.gov mail plugin.
   */
  public function __construct(
    $config_factory,
    AmazonSesHandlerInterface $handler,
    MessageBuilderInterface $message_builder,
    QueueFactory $queue_factory,
    protected RendererInterface $renderer,
  ) {
    parent::__construct($config_factory, $handler, $message_builder, $queue_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    return new static(
      $container->get('config.factory'),
      $container->get('amazon_ses.handler'),
      $container->get('amazon_ses.message_builder'),
      $container->get('queue'),
      $container->get('renderer'),
    );
  }

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
    if (is_array($message['body'])) {
      $message['body'] = implode("\n\n", $message['body']);
    }

    $body = (string) $message['body'];
    if ($body === strip_tags($body)) {
      $body = nl2br(Html::escape($body));
    }

    $build = [
      '#theme' => 'mass_email',
      '#subject' => $message['subject'] ?? '',
      '#body' => Markup::create($body),
    ];
    $message['body'] = (string) $this->renderer->renderInIsolation($build);
    $message['headers']['Content-Type'] = 'text/html; charset=UTF-8';

    return $message;
  }

}

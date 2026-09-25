<?php

namespace Drupal\mass_utility\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\sendgrid\Event\SendgridSendEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Applies the Mass.gov dynamic template to SendGrid messages.
 */
class SendgridTemplateSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a SendgridTemplateSubscriber object.
   */
  public function __construct(protected ConfigFactoryInterface $configFactory) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Use the event name directly so container compilation can complete during
    // the deployment where SendGrid has not yet been enabled in active config.
    return ['sendgrid.send' => 'applyTemplate'];
  }

  /**
   * Applies the configured template and template data to an email.
   */
  public function applyTemplate(SendgridSendEvent $event): void {
    $template_id = $this->configFactory
      ->get('mass_utility.sendgrid')
      ->get('template_id');

    if (!is_string($template_id) || !str_starts_with($template_id, 'd-')) {
      throw new \LogicException('The MASS_SENDGRID_TEMPLATE_ID environment variable must contain a SendGrid dynamic template ID.');
    }

    $email = $event->getEmail();
    $template_data = [
      'subject' => $email->getGlobalSubject()?->getSubject() ?? '',
      'section' => '',
      'section_text' => '',
    ];

    foreach ($email->getContents() as $content) {
      if ($content->getType() === 'text/html') {
        $template_data['section'] = $content->getValue();
      }
      elseif ($content->getType() === 'text/plain') {
        $template_data['section_text'] = $content->getValue();
      }
    }

    $email->setTemplateId($template_id);
    $email->addDynamicTemplateDatas($template_data);
    $email->setClickTracking(FALSE, FALSE);
    $email->setOpenTracking(FALSE);
  }

}

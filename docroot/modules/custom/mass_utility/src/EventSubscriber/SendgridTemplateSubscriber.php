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
    $config = $this->configFactory->get('mass_utility.sendgrid');
    $template_id = $config->get('template_id');

    if (!is_string($template_id) || !str_starts_with($template_id, 'd-')) {
      throw new \LogicException('The MASS_SENDGRID_TEMPLATE_ID environment variable must contain a SendGrid dynamic template ID.');
    }

    $email = $event->getEmail();
    // Preserve optional values supplied by mail-specific subscribers while
    // ensuring that the canonical subject and body come from Drupal's final
    // formatted message. The HTML has already passed through the configured
    // Drupal text format and must not be escaped before template expansion.
    $template_data = $email->getDynamicTemplateDatas() ?? [];
    $template_data['subject'] = $email->getGlobalSubject()?->getSubject() ?? '';
    $template_data['content'] = '';

    foreach ($email->getContents() as $content) {
      if ($content->getType() === 'text/html') {
        $template_data['content'] = $content->getValue();
        break;
      }
    }

    $email->setTemplateId($template_id);
    $email->addDynamicTemplateDatas($template_data);

    $asm_group_id = $config->get('asm_group_id');
    if (is_numeric($asm_group_id) && (int) $asm_group_id > 0) {
      $email->setAsm((int) $asm_group_id);
    }

    $email->setClickTracking(FALSE, FALSE);
    $email->setOpenTracking(FALSE);
  }

}

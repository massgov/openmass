<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_utility\Unit\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\mass_utility\EventSubscriber\SendgridTemplateSubscriber;
use Drupal\sendgrid\Event\SendgridSendEvent;
use Drupal\Tests\UnitTestCase;
use Psr\Log\NullLogger;
use SendGrid\Mail\Mail;

/**
 * @coversDefaultClass \Drupal\mass_utility\EventSubscriber\SendgridTemplateSubscriber
 * @group mass_utility
 */
class SendgridTemplateSubscriberTest extends UnitTestCase {

  /**
   * Tests that the subscriber uses the event name without loading its class.
   */
  public function testSubscribedEvents(): void {
    $this->assertSame([
      'sendgrid.send' => 'applyTemplate',
    ], SendgridTemplateSubscriber::getSubscribedEvents());
  }

  /**
   * Tests applying the global SendGrid template and its dynamic data.
   */
  public function testApplyTemplate(): void {
    $subscriber = new SendgridTemplateSubscriber(
      $this->createConfigFactory('d-1234567890', 42),
    );
    $email = $this->createEmail();

    $subscriber->applyTemplate($this->createEvent($email));

    $this->assertSame('d-1234567890', $email->getTemplateId()->getTemplateId());
    $this->assertSame([
      'subject' => 'Test subject',
      'content' => '<p>Test body</p>',
    ], $email->getDynamicTemplateDatas());
    $this->assertSame(42, $email->getAsm()->getGroupId()->getGroupId());

    $payload = json_decode(json_encode($email, JSON_THROW_ON_ERROR), TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertSame('d-1234567890', $payload['template_id']);
    $this->assertSame([
      'subject' => 'Test subject',
      'content' => '<p>Test body</p>',
    ], $payload['personalizations'][0]['dynamic_template_data']);
    $this->assertArrayNotHasKey('substitutions', $payload['personalizations'][0]);
    $this->assertSame([
      ['email' => 'recipient@example.com'],
      ['email' => 'second-recipient@example.com'],
    ], $payload['personalizations'][0]['to']);
    $this->assertSame([
      'X-Test-Header' => 'preserved',
    ], $payload['personalizations'][0]['headers']);
    $this->assertSame([
      'source' => 'drupal',
    ], $payload['personalizations'][0]['custom_args']);
    $this->assertSame('noreply@noreply.mass.gov', $payload['from']['email']);
    $this->assertSame('Mass.gov', $payload['from']['name']);
    $this->assertSame('reply@example.com', $payload['reply_to']['email']);
    $tracking = $email->getTrackingSettings();
    $this->assertFalse($tracking->getClickTracking()->getEnable());
    $this->assertFalse($tracking->getClickTracking()->getEnableText());
    $this->assertFalse($tracking->getOpenTracking()->getEnable());
  }

  /**
   * Tests that an empty template ID cannot accidentally be used for a send.
   */
  public function testEmptyTemplateIdFails(): void {
    $subscriber = new SendgridTemplateSubscriber(
      $this->createConfigFactory(''),
    );

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('MASS_SENDGRID_TEMPLATE_ID');
    $subscriber->applyTemplate($this->createEvent($this->createEmail()));
  }

  /**
   * Tests that a legacy template ID cannot accidentally be used for a send.
   */
  public function testLegacyTemplateIdFails(): void {
    $subscriber = new SendgridTemplateSubscriber(
      $this->createConfigFactory('legacy-template-id'),
    );

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('MASS_SENDGRID_TEMPLATE_ID');
    $subscriber->applyTemplate($this->createEvent($this->createEmail()));
  }

  /**
   * Creates a config factory that returns the requested template ID.
   */
  private function createConfigFactory(string $template_id, int $asm_group_id = 0): ConfigFactoryInterface {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['template_id', $template_id],
      ['asm_group_id', $asm_group_id],
    ]);

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')
      ->with('mass_utility.sendgrid')
      ->willReturn($config);

    return $config_factory;
  }

  /**
   * Creates a SendGrid email for testing.
   */
  private function createEmail(): Mail {
    $email = new Mail();
    $email->setFrom('noreply@noreply.mass.gov', 'Mass.gov');
    $email->setSubject('Test subject');
    $email->addTo('recipient@example.com');
    $email->addTo('second-recipient@example.com');
    $email->setReplyTo('reply@example.com');
    $email->addHeader('X-Test-Header', 'preserved');
    $email->addCustomArg('source', 'drupal');
    $email->addContent('text/plain', 'Test body');
    $email->addContent('text/html', '<p>Test body</p>');

    return $email;
  }

  /**
   * Creates a SendGrid send event for testing.
   */
  private function createEvent(Mail $email): SendgridSendEvent {
    $config = $this->createMock(ImmutableConfig::class);

    return new SendgridSendEvent(
      $email,
      new \SendGrid('MASS_SENDGRID_API_KEY'),
      $config,
      new NullLogger(),
    );
  }

}

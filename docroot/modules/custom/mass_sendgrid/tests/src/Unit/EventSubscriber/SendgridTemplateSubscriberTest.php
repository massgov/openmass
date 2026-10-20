<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_sendgrid\Unit\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\mass_sendgrid\EventSubscriber\SendgridTemplateSubscriber;
use Drupal\sendgrid\Event\SendgridSendEvent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use SendGrid\Mail\Mail;

/**
 * @coversDefaultClass \Drupal\mass_sendgrid\EventSubscriber\SendgridTemplateSubscriber
 * @group mass_sendgrid
 */
class SendgridTemplateSubscriberTest extends UnitTestCase {

  /**
   * Tests applying the global SendGrid template and its dynamic data.
   */
  public function testApplyTemplate(): void {
    $subscriber = new SendgridTemplateSubscriber(
      $this->createConfigFactory('d-1234567890'),
    );
    $email = $this->createEmail();

    $subscriber->applyTemplate($this->createEvent($email));

    $payload = json_decode(json_encode($email, JSON_THROW_ON_ERROR), TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertSame('d-1234567890', $payload['template_id']);
    $this->assertSame([
      'subject' => 'Test subject',
      'content' => '<p>Test body</p>',
    ], $payload['personalizations'][0]['dynamic_template_data']);
    $this->assertSame('configured-sender@example.com', $payload['from']['email']);
    $this->assertSame('Mass.gov', $payload['from']['name']);
    $this->assertSame('reply@example.com', $payload['reply_to']['email']);
    $tracking = $email->getTrackingSettings();
    $this->assertFalse($tracking->getClickTracking()->getEnable());
    $this->assertFalse($tracking->getClickTracking()->getEnableText());
    $this->assertFalse($tracking->getOpenTracking()->getEnable());
  }

  /**
   * Tests the site address is used when the caller did not set Reply-To.
   */
  public function testDefaultReplyTo(): void {
    $subscriber = new SendgridTemplateSubscriber(
      $this->createConfigFactory('d-1234567890'),
    );
    $email = new Mail();
    $email->setSubject('Test subject');
    $email->addTo('recipient@example.com');
    $email->addContent('text/html', '<p>Test body</p>');

    $subscriber->applyTemplate($this->createEvent($email));

    $this->assertSame('DigitalSupport@mass.gov', $email->getReplyTo()->getEmail());
  }

  /**
   * Tests that invalid template IDs cannot accidentally be used for a send.
   */
  #[DataProvider('invalidTemplateIdProvider')]
  public function testInvalidTemplateIdFails(string $template_id): void {
    $subscriber = new SendgridTemplateSubscriber(
      $this->createConfigFactory($template_id),
    );

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('MASS_SENDGRID_TEMPLATE_ID');
    $subscriber->applyTemplate($this->createEvent($this->createEmail()));
  }

  /**
   * Provides invalid template IDs.
   */
  public static function invalidTemplateIdProvider(): array {
    return [
      'empty' => [''],
      'legacy' => ['legacy-template-id'],
    ];
  }

  /**
   * Creates a config factory that returns the requested template ID.
   */
  private function createConfigFactory(string $template_id): ConfigFactoryInterface {
    return $this->getConfigFactoryStub([
      'mass_sendgrid.settings' => [
        'template_id' => $template_id,
        'from_email' => 'configured-sender@example.com',
      ],
      'system.site' => [
        'mail' => 'DigitalSupport@mass.gov',
        'name' => 'Mass.gov',
      ],
    ]);
  }

  /**
   * Creates a SendGrid email for testing.
   */
  private function createEmail(): Mail {
    $email = new Mail();
    $email->setFrom('sender@example.com', 'Mass.gov');
    $email->setSubject('Test subject');
    $email->addTo('recipient@example.com');
    $email->setReplyTo('reply@example.com');
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

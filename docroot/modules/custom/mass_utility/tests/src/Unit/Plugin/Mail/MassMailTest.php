<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_utility\Unit\Plugin\Mail;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Render\RendererInterface;
use Drupal\mass_utility\Plugin\Mail\MassMail;
use Drupal\sendgrid\SendgridHandlerInterface;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

/**
 * @coversDefaultClass \Drupal\mass_utility\Plugin\Mail\MassMail
 * @group mass_utility
 */
class MassMailTest extends UnitTestCase {

  /**
   * Tests that the required Mass.gov sender overrides the site address.
   */
  public function testSender(): void {
    $site_config = $this->createMock(ImmutableConfig::class);
    $site_config->method('get')->willReturnMap([
      ['mail', 'DigitalSupport@mass.gov'],
      ['name', 'Mass.gov'],
    ]);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')
      ->with('system.site')
      ->willReturn($site_config);

    $plugin = new MassMail(
      $config_factory,
      $this->createMock(LoggerInterface::class),
      $this->createMock(RendererInterface::class),
      $this->createMock(QueueFactory::class),
      $this->createMock(SendgridHandlerInterface::class),
      $this->createMock(MimeTypeGuesserInterface::class),
    );

    $method = new \ReflectionMethod($plugin, 'buildMessage');
    $message = $method->invoke($plugin, [
      'to' => 'recipient@example.com',
      'subject' => 'Test subject',
      'body' => '<p>Test body</p>',
      'reply-to' => TRUE,
      'headers' => [
        'Reply-to' => 'DigitalSupport@mass.gov',
      ],
    ]);

    $this->assertSame('noreply@noreply.mass.gov', $message['from_email']);
    $this->assertSame('Mass.gov', $message['from_name']);
    $this->assertSame('DigitalSupport@mass.gov', $message['reply-to']);
    $this->assertSame(['recipient@example.com'], $message['to']);
    $this->assertSame('Test subject', $message['subject']);
    $this->assertSame('<p>Test body</p>', $message['html']);
  }

}

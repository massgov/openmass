<?php

namespace Drupal\Tests\mass_utility\Unit\Plugin\Mail;

use Drupal\amazon_ses\AmazonSesHandlerInterface;
use Drupal\amazon_ses\MessageBuilderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Render\RendererInterface;
use Drupal\mass_utility\Plugin\Mail\MassMail;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Mass.gov Amazon SES mail plugin.
 *
 * @coversDefaultClass \Drupal\mass_utility\Plugin\Mail\MassMail
 * @group mass_utility
 */
class MassMailTest extends UnitTestCase {

  /**
   * Tests that plain-text messages are escaped and use the HTML template.
   *
   * @covers ::format
   */
  public function testFormat(): void {
    $renderer = $this->createMock(RendererInterface::class);
    $renderer->expects($this->once())
      ->method('renderInIsolation')
      ->with($this->callback(function (array $build): bool {
        $this->assertSame('mass_email', $build['#theme']);
        $this->assertSame('Message subject', $build['#subject']);
        $this->assertSame("First &amp; line<br />\n<br />\nSecond line", (string) $build['#body']);
        return TRUE;
      }))
      ->willReturn('<html>Rendered email</html>');

    $plugin = new MassMail(
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(AmazonSesHandlerInterface::class),
      $this->createMock(MessageBuilderInterface::class),
      $this->createMock(QueueFactory::class),
      $renderer,
    );

    $message = $plugin->format([
      'subject' => 'Message subject',
      'body' => ['First & line', 'Second line'],
      'headers' => [],
    ]);

    $this->assertSame('<html>Rendered email</html>', $message['body']);
    $this->assertSame('text/html; charset=UTF-8', $message['headers']['Content-Type']);
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_utility\Unit\Logger\Processor;

use Drupal\mass_utility\Logger\Processor\BacktraceRemovalProcessor;
use Drupal\Tests\UnitTestCase;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * @coversDefaultClass \Drupal\mass_utility\Logger\Processor\BacktraceRemovalProcessor
 * @group mass_utility
 */
class BacktraceRemovalProcessorTest extends UnitTestCase {

  /**
   * Tests that the raw backtrace is removed from the log context.
   */
  public function testBacktraceIsRemoved(): void {
    $processor = new BacktraceRemovalProcessor();
    $record = $this->createRecord([
      'backtrace' => ['frame1', 'frame2'],
      '@backtrace_string' => "one\ntwo\nthree",
      'foo' => 'bar',
    ]);

    $processed = $processor($record);

    $this->assertArrayNotHasKey('backtrace', $processed->context);
    $this->assertSame("one\ntwo\nthree", $processed->context['@backtrace_string']);
    $this->assertSame('bar', $processed->context['foo']);
  }

  /**
   * Tests that the backtrace string is truncated on Acquia.
   */
  public function testBacktraceStringIsTrimmedOnAcquia(): void {
    putenv('AH_SITE_ENVIRONMENT=test');

    try {
      $processor = new BacktraceRemovalProcessor();
      $record = $this->createRecord([
        '@backtrace_string' => "one\ntwo\nthree\nfour\nfive\nsix\nseven",
      ]);

      $processed = $processor($record);

      $this->assertSame("one\ntwo\nthree\nfour\nfive", $processed->context['@backtrace_string']);
    }
    finally {
      putenv('AH_SITE_ENVIRONMENT');
    }
  }

  /**
   * Tests that the backtrace string is unchanged off Acquia.
   */
  public function testBacktraceStringIsUnchangedOutsideAcquia(): void {
    putenv('AH_SITE_ENVIRONMENT');

    $processor = new BacktraceRemovalProcessor();
    $record = $this->createRecord([
      '@backtrace_string' => "one\ntwo\nthree\nfour\nfive\nsix\nseven",
    ]);

    $processed = $processor($record);

    $this->assertSame(
      "one\ntwo\nthree\nfour\nfive\nsix\nseven",
      $processed->context['@backtrace_string'],
    );
  }

  /**
   * Creates a log record for testing.
   */
  private function createRecord(array $context): LogRecord {
    return new LogRecord(
      datetime: new \DateTimeImmutable(),
      channel: 'test',
      level: Level::Info,
      message: 'Test message',
      context: $context,
      extra: [],
    );
  }

}

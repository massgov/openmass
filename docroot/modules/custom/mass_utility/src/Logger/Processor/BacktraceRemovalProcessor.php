<?php

declare(strict_types=1);

namespace Drupal\mass_utility\Logger\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Removes backtrace data that can bloat or recurse in log payloads.
 */
class BacktraceRemovalProcessor implements ProcessorInterface {

  /**
   * Process an individual record.
   */
  public function __invoke(LogRecord $record): LogRecord {
    $context = $record->context;

    // Remove the raw backtrace so it doesn't cause infinite recursion.
    unset($context['backtrace']);

    if (getenv('AH_SITE_ENVIRONMENT') && isset($context['@backtrace_string'])) {
      // Limit the backtrace in the message to N lines in Acquia environments.
      // This prevents truncation of log lines that exceed the max length.
      $parts = explode("\n", (string) $context['@backtrace_string']);
      $context['@backtrace_string'] = implode("\n", array_slice($parts, 0, 5));
    }

    return $record->with(context: $context);
  }

}

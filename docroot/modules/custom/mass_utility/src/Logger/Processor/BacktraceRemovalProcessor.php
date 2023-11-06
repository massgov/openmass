<?php

namespace Drupal\mass_utility\Logger\Processor;

/**
 * Removes backtrace property, which causes infinite loop.
 */
class BacktraceRemovalProcessor {

  /**
   * Process an individual record.
   */
  public function __invoke($record) {
    // Remove the backtrace from context so it doesn't cause infinite recursion.
    if (isset($record['context']) && isset($record['context']['backtrace'])) {
      unset($record['context']['backtrace']);
    }

    if (getenv('AH_SITE_ENVIRONMENT')) {
      // Limit the backtrace in the message to N lines in Acquia environments.
      // This prevents truncation of log lines that exceed the max length.
      if (isset($record['context']) && isset($record['context']['@backtrace_string'])) {
        $parts = explode("\n", $record['context']['@backtrace_string']);
        $record['context']['@backtrace_string'] = implode("\n", array_slice($parts, 0, 5));
      }
    }

    return $record;
  }

}

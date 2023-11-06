<?php

namespace Drupal\mass_utility\Logger;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;
use Monolog\LogRecord;

/**
 * Syslog handler for mass.gov.
 *
 * Sets a dynamic ident based on the Acquia site name, and defaults
 * to LOG_LOCAL0 rather than LOG_USER. Also formats the line to match
 * Drupal's built in syslog logger.
 */
class AcquiaSyslogHandler extends SyslogHandler {

  // Was used when before we switched to JSONFormatter (3/2022).
  const FORMAT = '%extra.base_url%|%extra.timestamp%|%channel%|%level_name%|%extra.ip%|%extra.request_uri%|%extra.referer%|%extra.uid%|%context.link%|%message%';

  /**
   * Constructor.
   */
  public function __construct($facility = LOG_LOCAL0, $level = Logger::DEBUG, $bubble = TRUE, $logopts = LOG_PID) {
    $ident = getenv('AH_SITE_NAME') ?: 'drupal';
    parent::__construct($ident, $facility, $level, $bubble, $logopts);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormatter(): FormatterInterface {
    return new JsonFormatter();
  }

  /**
   * {@inheritdoc}
   */
  public function handle(LogRecord $record): bool {
    global $base_url;

    $record->extra += [
      'base_url' => $base_url,
      'timestamp' => $record['datetime']->format('U'),
    ];
    return parent::handle($record);
  }

}

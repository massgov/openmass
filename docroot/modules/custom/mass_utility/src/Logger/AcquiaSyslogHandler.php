<?php

namespace Drupal\mass_utility\Logger;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;

/**
 * Syslog handler for mass.gov.
 *
 * Sets a dynamic ident based on the Acquia site name, and defaults
 * to LOG_LOCAL0 rather than LOG_USER. Also formats the line to match
 * Drupal's built in syslog logger.
 */
class AcquiaSyslogHandler extends SyslogHandler {

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
  public function getDefaultFormatter() {
    return new LineFormatter(self::FORMAT);
  }

  /**
   * {@inheritdoc}
   */
  protected function processRecord(array $record) {
    global $base_url;

    $record['extra'] += [
      'base_url' => $base_url,
      'timestamp' => $record['datetime']->format('U'),
    ];
    return parent::processRecord($record);
  }

}

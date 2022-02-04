<?php

namespace Drupal\mass_utility\Logger;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;

/**
 * A more readable version of the standard stream handler.
 */
class DockerStreamHandler extends StreamHandler {
  const FORMAT = "[%datetime%] %channel%.%level_name%: %message%\n";

  /**
   * {@inheritdoc}
   */
  public function getFormatter(): FormatterInterface {
    return new LineFormatter(self::FORMAT, 'Y-m-d H:i:s');
  }

}

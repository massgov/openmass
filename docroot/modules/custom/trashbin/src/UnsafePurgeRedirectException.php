<?php

declare(strict_types=1);

namespace Drupal\trashbin;

/**
 * Concurrent redirect changes prevent applying a previously prepared purge.
 */
final class UnsafePurgeRedirectException extends \RuntimeException {

}

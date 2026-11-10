<?php

declare(strict_types=1);

namespace Drupal\trashbin;

/**
 * An entity must stay in trash because its redirects cannot be preserved.
 */
final class UnsafePurgeRedirectException extends \RuntimeException {}

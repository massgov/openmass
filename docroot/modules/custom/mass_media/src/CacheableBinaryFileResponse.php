<?php

namespace Drupal\mass_media;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Cache\CacheableResponseTrait;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A BinaryFileResponse that exposes Drupal cacheability metadata.
 *
 * Allows cache tag propagation to Akamai Edge-Cache-Tag headers and Drupal's
 * internal dynamic page cache for file download routes.
 */
class CacheableBinaryFileResponse extends BinaryFileResponse implements CacheableResponseInterface {

  use CacheableResponseTrait;

}

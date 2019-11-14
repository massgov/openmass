<?php

namespace Drupal\mass_jsonapi\Serializer;

use Drupal\jsonapi\Serializer\Serializer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;

/**
 * Class MassSerializer.
 */
class MassSerializer extends Serializer {

  /**
   * MassSerializer constructor.
   *
   * @param array $normalizers
   *   Normalizers.
   * @param array $encoders
   *   Encoders.
   */
  public function __construct(array $normalizers = [], array $encoders = []) {
    // We allow multiple normalizers until there is a better way.
    // See mass_jsonapi/src/Normalizer/MassOffsetPageNormalizer.php.
    SymfonySerializer::__construct($normalizers, $encoders);
  }

}

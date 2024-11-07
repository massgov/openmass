<?php

namespace Drupal\mass_serializer\Normalizer;

use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * Converts typed data objects to arrays.
 */
class TypedDataNormalizer extends NormalizerBase {
  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = TypedDataInterface::class;

  protected $format = 'json';

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []): array|bool|string|int|float|null|\ArrayObject {
    $value = $object->getValue();

    if (isset($value[0]) && isset($value[0]['value'])) {
      $value = $value[0]['value'];
    }

    if (is_array($value) && count($value) == 1) {
      $value = current($value);
    }

    return $value;
  }

}

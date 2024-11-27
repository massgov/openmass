<?php

namespace Drupal\mass_serializer\Normalizer;

use Drupal\Core\TypedData\Plugin\DataType\Uri;
use Drupal\Core\Url;
use Drupal\jsonapi\Normalizer\NormalizerBase;

/**
 * Converts entity: and internal: links to relative urls.
 */
class JSONAPIURINormalizer extends NormalizerBase {

  protected $supportedInterfaceOrClass = Uri::class;

  public function supportsNormalization($data, $format = NULL, array $context = []): bool {
    return parent::supportsNormalization($data, $format) && $this->isInternalUrl($data);
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []): array|bool|string|int|float|null|\ArrayObject {
    $url = Url::fromUri($object->getValue());
    return $url->toString();
  }

  /**
   * Check whether a URL is "internal" (can be converted).
   */
  public function isInternalUrl(Uri $data) {
    $value = $data->getValue();
    return strpos($value, 'internal:') === 0 || strpos($value, 'entity:') === 0;
  }

}

<?php

namespace Drupal\mass_serializer\Normalizer;

use Drupal\Core\Url;
use Drupal\serialization\Normalizer\ContentEntityNormalizer;
use Drupal\taxonomy\TermInterface;

/**
 * Converts the Drupal entity object structures to a normalized array.
 */
class TaxonomyTermUserOrganizationNormalizer extends ContentEntityNormalizer {
  /**
   * The list of included fields.
   *
   * @var array
   */
  private $includedFields = [
    'description',
    'langcode',
    'name',
    'tid',
    'uuid',
  ];

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = TermInterface::class;

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL, $context = []): bool {
    // If we aren't dealing with an object or the format is not supported return
    // now.
    if (!is_object($data) || !$this->checkFormat($format)) {
      return FALSE;
    }
    // This custom normalizer should be supported for "document" media entities.
    if ($data instanceof TermInterface && $data->bundle() == 'user_organization') {
      return TRUE;
    }
    // Otherwise, this normalizer does not support the $data object.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []): float|array|\ArrayObject|bool|int|string|null {
    $attributes = parent::normalize($object, $format, $context);

    // Keep only fields defined in context.
    $attributes = array_intersect_key($attributes, array_flip($this->includedFields));

    // Rename keys to needed values.
    $this->renameKeys($attributes);

    $attributes['@type'] = 'dcat:Dataset';
    // Description must be at least 1 characters long.
    $attributes['description'] = empty($attributes['description']['value']) ? 'N/A' : $attributes['description']['value'];
    /** @var \Drupal\media\Entity\Media $object */
    $attributes['identifier'] = $object->toUrl('canonical', ['absolute' => TRUE])->toString();

    $attributes['language'] = $attributes['language'][0];
    $attributes['uuid'] = $attributes['uuid'][0];

    // Conforms to.
    $attributes['conformsTo'] = 'https://project-open-data.cio.gov/v1.1/schema';

    // Distribution. One file is in the document.
    $attributes['distribution'][] = [
      '@type' => 'dcat:Distribution',
      'accessURL' => Url::fromUserInput(sprintf('/api/v1/organization/%s/data.json', $attributes['tid'][0]))->setAbsolute()->toString(),
      'format' => 'API',
    ];
    unset($attributes['tid']);

    $attributes['title'] = str_replace('* ', '', $attributes['title'][0]);

    // Re-sort the array after our new addition.
    ksort($attributes);
    // Return the $attributes with our new value.
    return $attributes;
  }

  /**
   * Change the key names according to the open data needed names.
   *
   * @param array $attributes
   *   Attributes with key names to change.
   */
  private function renameKeys(array &$attributes) {
    $key_names = [
      'langcode' => 'language',
      'name' => 'title',
    ];
    foreach ($key_names as $old_key => $new_key) {
      if (isset($attributes[$old_key])) {
        $attributes[$new_key] = $attributes[$old_key];
        unset($attributes[$old_key]);
      }
    }
  }

}

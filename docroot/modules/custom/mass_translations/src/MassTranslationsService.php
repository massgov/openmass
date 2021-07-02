<?php

namespace Drupal\mass_translations;

use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Language\Language;

/**
 * Provides a 'MassTranslationService' service.
 *
 * Helper methods for translation.
 */
class MassTranslationsService extends ServiceProviderBase {

  /**
   * Gets all node translations based on custom English version field.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Node object.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   Pass in the storage.
   * @param string $english_field_name
   *   Name of field containing English version.
   *
   * @return array
   *   Array of node IDs keyed by language code.
   */
  public function getTranslationLanguages(EntityInterface $entity, EntityStorageInterface $storage, $english_field_name) {
    $languages = [];

    $english_id = $entity->id();

    $language = $entity->language()->getId();
    if ($language !== 'en') {
      foreach ($entity->get($english_field_name)->referencedEntities() as $field_english_version) {
        $english_id = $field_english_version->id();
      }
    }

    $languages[Language::LANGCODE_DEFAULT] = $storage->load($english_id);

    $non_english_language_ids = $storage->getQuery()
      ->condition($english_field_name, $english_id)
      ->execute();

    foreach ($non_english_language_ids as $non_english_language_id) {
      $non_english_entity = $storage->load($non_english_language_id);
      $languages[$non_english_entity->language()->getId()] = $storage->load($non_english_language_id);
    }

    return $languages;
  }

}

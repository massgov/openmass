<?php

namespace Drupal\mass_translations\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Link;

/**
 * Class TranslationsController.
 *
 * @package Drupal\mass_translations\Controller
 */
class TranslationsController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function markup(EntityInterface $entity, $storage, $english_field_name) {
    $markup = '';
    $language_manager = new LanguageManager(new LanguageDefault([]));
    $languages = $this->getTranslationLanguages($entity, $storage, $english_field_name);

    foreach ($languages as $entity) {
      $entity_lang = $storage->load($entity->id());
      $markup .= '<h3>' . $entity_lang->language()->getName() . '</h3>';
      $markup .= Link::fromTextAndUrl($entity_lang->label(), $entity_lang->toUrl('canonical', [
        'language' => $language_manager->getLanguage('en'),
      ]))->toString();
    }

    return [
      '#type' => 'markup',
      '#markup' => $markup,
    ];
  }

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
  public function getTranslationLanguages(EntityInterface $entity, EntityStorageInterface $storage, string $english_field_name): array {
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
      ->accessCheck(FALSE)
      ->execute();

    foreach ($non_english_language_ids as $non_english_language_id) {
      $non_english_entity = $storage->load($non_english_language_id);
      $languages[$non_english_entity->language()->getId()] = $storage->load($non_english_language_id);
    }

    return $languages;
  }

}

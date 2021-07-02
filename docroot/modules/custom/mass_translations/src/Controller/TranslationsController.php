<?php

namespace Drupal\mass_translations\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Link;
use Drupal\mass_translations\MassTranslationsService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class TranslationsController.
 *
 * @package Drupal\mass_translations\Controller
 */
class TranslationsController extends ControllerBase {

  /**
   * The 'MassTranslationsService' service.
   *
   * @var \Drupal\mass_translations\MassTranslationsService
   */
  protected $massTranslationsService;

  /**
   * Class constructor.
   *
   * @param \Drupal\mass_translations\MassTranslationsService $mass_translations_service
   *   The 'MassTranslationsService' service.
   */
  public function __construct(MassTranslationsService $mass_translations_service) {
    $this->massTranslationsService = $mass_translations_service;
    $this->entityTypeManager();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mass_translations.service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function markup(EntityInterface $entity, $storage, $english_field_name) {
    $markup = '';
    $language_manager = new LanguageManager(new LanguageDefault([]));
    $languages = $this->massTranslationsService->getTranslationLanguages($entity, $storage, $english_field_name);

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

}

<?php

namespace Drupal\mass_translations\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\mass_translations\MassTranslationsService;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MediaTranslationsController.
 *
 * @package Drupal\mass_translations\Controller
 */
class MediaTranslationsController extends TranslationsController {

  protected $mediaStorage;
  protected $englishFieldName;

  /**
   * {@inheritdoc}
   */
  public function __construct(MassTranslationsService $mass_translations_service) {
    parent::__construct($mass_translations_service);
    $this->mediaStorage = $this->entityTypeManager->getStorage('media');
    $this->englishFieldName = 'field_media_english_version';
  }

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $media) {
    $languages = $this->massTranslationsService->getTranslationLanguages($media, $this->mediaStorage, $this->englishFieldName);

    return AccessResult::allowedIf(count($languages) > 1);
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
  public function content(EntityInterface $media) {
    return parent::markup($media, $this->mediaStorage, $this->englishFieldName);
  }

}

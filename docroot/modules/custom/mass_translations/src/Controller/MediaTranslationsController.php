<?php

namespace Drupal\mass_translations\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\media\MediaStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MediaTranslationsController.
 *
 * @package Drupal\mass_translations\Controller
 */
class MediaTranslationsController extends TranslationsController {

  protected $mediaStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(MediaStorage $media_storage) {
    $this->mediaStorage = $media_storage;
  }

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $media) {
    $languages = parent::getTranslationLanguages($media, $this->mediaStorage, $media->getEnglishFieldName());

    return AccessResult::allowedIf(count($languages) > 1);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('media')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function content(EntityInterface $media) {
    return parent::markup($media, $this->mediaStorage, $media->getEnglishFieldName());
  }

}

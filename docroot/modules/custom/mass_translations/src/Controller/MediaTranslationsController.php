<?php

namespace Drupal\mass_translations\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\media\MediaInterface;
use Drupal\media\MediaStorage;
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
  public function __construct(MediaStorage $media_storage) {
    $this->mediaStorage = $media_storage;
    $this->englishFieldName = 'field_media_english_version';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')->getStorage('media')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function content(EntityInterface $media) {
    return parent::markup($media, $this->mediaStorage, $this->englishFieldName);
  }

}

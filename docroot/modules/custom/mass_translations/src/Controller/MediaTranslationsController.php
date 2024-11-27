<?php

namespace Drupal\mass_translations\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\media\MediaStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MediaTranslationsController.
 *
 * @package Drupal\mass_translations\Controller
 */
class MediaTranslationsController extends TranslationsController {

  /**
   * The media storage service.
   *
   * @var \Drupal\media\MediaStorage
   */
  protected $mediaStorage;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs the MediaTranslationsController object.
   *
   * @param \Drupal\media\MediaStorage $media_storage
   *   The media storage service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   */
  public function __construct(MediaStorage $media_storage, AccountProxyInterface $current_user) {
    $this->mediaStorage = $media_storage;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('media'),
      $container->get('current_user')
    );
  }

  /**
   * Access check for the translations route.
   *
   * @param \Drupal\Core\Entity\EntityInterface $media
   *   The media entity.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(EntityInterface $media) {
    // Allow access only if the user is authenticated.
    if ($this->currentUser->isAuthenticated()) {
      // Check if the media entity has multiple translations.
      $languages = parent::getTranslationLanguages($media, $this->mediaStorage, $media->getEnglishFieldName());
      return AccessResult::allowedIf(count($languages) > 1);
    }

    // Deny access for anonymous users.
    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public function content(EntityInterface $media) {
    return parent::markup($media, $this->mediaStorage, $media->getEnglishFieldName());
  }

}

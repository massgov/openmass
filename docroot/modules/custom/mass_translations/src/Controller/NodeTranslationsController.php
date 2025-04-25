<?php

namespace Drupal\mass_translations\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class NodeTranslationsController.
 *
 * @package Drupal\mass_translations\Controller
 */
class NodeTranslationsController extends TranslationsController {

  /**
   * The node storage service.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs the NodeTranslationsController object.
   *
   * @param \Drupal\node\NodeStorageInterface $node_storage
   *   The node storage service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   */
  public function __construct(NodeStorageInterface $node_storage, AccountProxyInterface $current_user) {
    $this->nodeStorage = $node_storage;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('node'),
      $container->get('current_user')
    );
  }

  /**
   * Access check for the translations route.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   The node entity.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(EntityInterface $node) {
    // Allow access only if the user is authenticated.
    if ($this->currentUser->isAuthenticated()) {
      // Check if the node has multiple translations.
      $languages = parent::getTranslationLanguages($node, $this->nodeStorage, $node->getEnglishFieldName());
      return AccessResult::allowedIf(count($languages) > 1 && $node->bundle() !== 'api_service_card');
    }

    // Deny access for anonymous users.
    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public function content(EntityInterface $node) {
    return parent::markup($node, $this->nodeStorage, $node->getEnglishFieldName());
  }

}

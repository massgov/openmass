<?php

namespace Drupal\mass_translations\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\NodeStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class NodeTranslationsController.
 *
 * @package Drupal\mass_translations\Controller
 */
class NodeTranslationsController extends TranslationsController {

  protected $nodeStorage;
  protected $englishFieldName;

  /**
   * {@inheritdoc}
   */
  public function __construct(NodeStorageInterface $node_storage) {
    $this->nodeStorage = $node_storage;
    $this->englishFieldName = 'field_english_version';
  }

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $node) {
    $languages = parent::getTranslationLanguages($node, $this->nodeStorage, $this->englishFieldName);

    return AccessResult::allowedIf(count($languages) > 1);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('node')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function content(EntityInterface $node) {
    return parent::markup($node, $this->nodeStorage, $this->englishFieldName);
  }

}

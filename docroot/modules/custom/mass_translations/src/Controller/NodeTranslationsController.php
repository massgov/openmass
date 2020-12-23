<?php

namespace Drupal\mass_translations\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\node\NodeStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class TranslationsController.
 *
 * @package Drupal\mass_translations\Controller
 */
class NodeTranslationsController extends TranslationsController {

  protected $nodeStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(NodeStorageInterface $node_storage) {
    $this->nodeStorage = $node_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')->getStorage('node')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function content(EntityInterface $node) {
    return parent::markup($node, $this->nodeStorage, 'field_english_version');
  }

}

<?php

namespace Drupal\mass_translations\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\mass_translations\MassTranslationsService;
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
  public function __construct(MassTranslationsService $mass_translations_service) {
    parent::__construct($mass_translations_service);
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
    $this->englishFieldName = 'field_english_version';
  }

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $node) {
    $languages = $this->massTranslationsService->getTranslationLanguages($node, $this->nodeStorage, $this->englishFieldName);

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
  public function content(EntityInterface $node) {
    return parent::markup($node, $this->nodeStorage, $this->englishFieldName);
  }

}

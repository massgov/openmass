<?php

namespace Drupal\mass_moderation_migration\Plugin\migrate\source;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Content entity deriver.
 */
class ContentEntityDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * EntityModerationStateDeriver constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach ($this->entityTypeManager->getDefinitions() as $id => $entity_type) {
      if ($this->isCompatible($entity_type)) {
        $derivative = $base_plugin_definition;
        $derivative['source_module'] = $entity_type->getProvider();
        $this->derivatives[$id] = $derivative;
      }
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

  /**
   * Determines if an entity type can be used by the derived plugin.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   *
   * @return bool
   *   TRUE if the entity type is compatible with the plugin; FALSE otherwise.
   */
  protected function isCompatible(EntityTypeInterface $entity_type) {
    return $entity_type instanceof ContentEntityTypeInterface;
  }

}

<?php

namespace Drupal\mass_moderation_migration\Plugin\Deriver;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mass_moderation_migration\Plugin\migrate\source\ContentEntityDeriver;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Moderation deriver.
 */
class ModerationDeriver extends ContentEntityDeriver {

  /**
   * The moderatable entity type IDs.
   *
   * @var string[]
   */
  protected $entityTypes;

  /**
   * EntityModerationStateDeriver constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param string[] $entity_types
   *   The moderatable entity type IDs.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, array $entity_types) {
    parent::__construct($entity_type_manager);
    $this->entityTypes = $entity_types;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('state')->get('moderation_entity_types', [])
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach ($this->entityTypeManager->getDefinitions() as $id => $entity_type) {
      if ($this->isCompatible($entity_type)) {
        $derivative = $base_plugin_definition;
        $derivative['id'] .= ":$id";
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
    return parent::isCompatible($entity_type) && (
      $entity_type->isRevisionable() &&
      $entity_type->isTranslatable() &&
      in_array($entity_type->id(), $this->entityTypes)
    );
  }

}

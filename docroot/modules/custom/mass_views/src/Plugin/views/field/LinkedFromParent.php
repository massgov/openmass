<?php

namespace Drupal\mass_views\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_usage\EntityUsageInterface;
use Drupal\mayflower\Helper;
use Drupal\node\Entity\Node;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom view field to show linked from parent flag.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("linked_from_parent")
 */
class LinkedFromParent extends FieldPluginBase {

  /**
   * The EntityUsage service.
   *
   * @var \Drupal\entity_usage\EntityUsageInterface
   */
  protected $entity_usage;

  /**
   * The EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a Permission object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\entity_usage\EntityUsageInterface $entity_usage
   *   The EntityUsage service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityUsageInterface $entity_usage, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entity_usage = $entity_usage;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_usage.usage'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing -- to override the parent query.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $parent_id = $values->node_field_data_node__field_primary_parent_nid;
    $child = $values->_entity;
    $usages = $this->entity_usage->listSources($child);
    foreach ($usages as $source_type => $ids) {
      $type_storage = $this->entityTypeManager->getStorage($source_type);
      foreach ($ids as $source_id => $records) {
        $source_entity = $type_storage->load($source_id);
        if (!$source_entity) {
          // If for some reason this record is broken, just skip it.
          continue;
        }
        // If the source is a paragraph, get the parent node.
        if ($source_entity->getEntityTypeId() == 'paragraph') {
          /** @var \Drupal\paragraphs\ParagraphInterface $source_entity */
          $source_entity = Helper::getParentNode($source_entity);
        }
        if ($source_entity instanceof Node) {
          if ($source_entity->id() == $parent_id) {
            return $this->t('Yes');
          }
        }
      }
    }
    return $this->t('No');
  }

}

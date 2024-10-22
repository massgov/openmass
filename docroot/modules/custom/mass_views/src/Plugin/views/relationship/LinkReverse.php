<?php

namespace Drupal\mass_views\Plugin\views\relationship;

use Drupal\views\Plugin\ViewsHandlerManager;
use Drupal\views\Plugin\views\relationship\RelationshipPluginBase;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Handles (reverse) relationships from node to entity with link field referring to node.
 *
 * @ingroup views_relationship_handlers
 *
 * @ViewsRelationship("link_reverse")
 */
class LinkReverse extends RelationshipPluginBase {

  /**
   * Constructs a LinkReverse object.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Machine name of plugin.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\views\Plugin\ViewsHandlerManager $join_manager
   *   The views plugin join manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ViewsHandlerManager $join_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->joinManager = $join_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.views.join')
    );
  }

  /**
   * Called to implement a (reverse) relationship in a query.
   *
   * Copied from EntityReverse.php, with bugs fixed.
   */
  public function query() {
    $this->ensureMyTable();
    // First, relate our base table to the current base table to the
    // field, using the base table's id field to the field's column.
    $views_data = Views::viewsData()->get($this->table);
    $left_field = $views_data['table']['base']['field'];

    $first = [
      'left_table' => $this->tableAlias,
      'left_field' => $left_field,
      'table' => $this->definition['field table'],
      'field' => $this->definition['field field'],
      'adjusted' => TRUE,
      'reverse' => TRUE,
    ];
    if (!empty($this->options['required'])) {
      $first['type'] = 'INNER';
    }

    if (!empty($this->definition['join_extra'])) {
      $first['extra'] = $this->definition['join_extra'];
    }

    // The first join from linked node to linking field uses link join.
    if (!empty($this->definition['uri_join_id'])) {
      $id = $this->definition['uri_join_id'];
    }
    else {
      $id = 'link_join';
    }
    $first_join = $this->joinManager->createInstance($id, $first);

    $this->first_alias = $this->query->addTable($this->definition['field table'], $this->relationship, $first_join);

    // Second, relate the field table to the entity specified using
    // the entity id on the field table and the entity's id field.
    $second = [
      'left_table' => $this->first_alias,
      'left_field' => 'entity_id',
      'table' => $this->definition['base'],
      'field' => $this->definition['base field'],
      'adjusted' => TRUE,
    ];

    if (!empty($this->options['required'])) {
      $second['type'] = 'INNER';
    }

    // The second join from the field to its owner is standard.
    if (!empty($this->definition['join_id'])) {
      $id = $this->definition['join_id'];
    }
    else {
      $id = 'standard';
    }
    $second_join = $this->joinManager->createInstance($id, $second);
    $second_join->adjusted = TRUE;

    // Use a short alias for this.
    $alias = $this->definition['field_name'] . '_' . $this->table;

    $this->alias = $this->query->addRelationship($alias, $second_join, $this->definition['base'], $this->relationship);
  }

}

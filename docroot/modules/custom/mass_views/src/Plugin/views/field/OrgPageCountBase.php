<?php

namespace Drupal\mass_views\Plugin\views\field;

use Drupal\views\Plugin\views\field\NumericField;
use Drupal\views\ResultRow;

/**
 * Base field handler to count the org_page section content references.
 */
class OrgPageCountBase extends NumericField {

  /**
   * The table alias.
   *
   * @var string
   */
  protected $pseudoTableAlias = '';

  /**
   * The field name/expression alias.
   *
   * @var string
   */
  protected $pseudoFieldName = '';

  /**
   * The paragraph bundle.
   *
   * @var string
   */
  protected $paragraphBundle = '';

  /**
   * Add a subquery to the main query for sorting purposes.
   *
   * @{inheritdoc}
   */
  public function query() {
    // Add a subquery to the query that will find the paragraph type count.
    $subQuery = \Drupal::database()->select('paragraphs_item_field_data', $this->pseudoTableAlias);
    $subQuery->addField($this->pseudoTableAlias, 'parent_id');
    $subQuery->addExpression("COUNT($this->pseudoTableAlias.id)", $this->pseudoFieldName);
    $subQuery->condition("$this->pseudoTableAlias.parent_type", 'paragraph');
    $subQuery->condition("$this->pseudoTableAlias.type", $this->paragraphBundle);
    $subQuery->groupBy("$this->pseudoTableAlias.id");

    $relationship = $this->relationship ?: 'paragraphs_item_field_data';

    // Add the subquery to as a component of a join.
    $joinDefinition = [
      'table formula' => $subQuery,
      'field' => 'parent_id',
      'left_table' => $relationship,
      'left_field' => 'id',
      'adjust' => TRUE,
    ];

    // Create a join object and create a relationship between the main query and the subquery.
    $join = \Drupal::service('plugin.manager.views.join')->createInstance('standard', $joinDefinition);
    $this->query->addRelationship($this->pseudoFieldName, $join, 'paragraphs_item_field_data', $relationship);
    // DON'T Add the field to the Views interface to prevent record duplication.
  }

  /**
   * Render the field value.
   *
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    // Build the value from scratch.
    $entity = $values->_entity;
    $items = 0;
    if ($entity->bundle() !== 'org_page') {
      return $items;
    }

    foreach ($entity->field_organization_sections->referencedEntities() as $section) {
      foreach ($section->field_section_long_form_content->referencedEntities() as $content) {
        if ($content->bundle() === $this->paragraphBundle) {
          $items++;
        }
      }
    }

    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function clickSort($order) {
    if (isset($this->field_alias)) {
      $params = $this->options['group_type'] != 'group' ? ['function' => $this->options['group_type']] : [];
      $this->query->addOrderBy(NULL, $this->pseudoFieldName, $order, $this->field_alias, $params);
    }
  }

}

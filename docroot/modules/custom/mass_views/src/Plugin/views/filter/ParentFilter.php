<?php

namespace Drupal\mass_views\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filters by node's organization.
 *
 * Organization is determined by field_organizations, or the NID itself in the
 * case of an org_page node.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("mass_views_node_parent_filter")
 */
class ParentFilter extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);
    $form['value'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#tags' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // ONLY add the relationships if we have a value to filter on.
    if ($value = $this->getValue()) {
      // Pre-create the join we need, but convert it to an INNER JOIN for
      // performance.
      $relationship = 'node_field_data';
      $join = $this->query->getJoinData('node__field_primary_parent', $relationship);
      $join->type = 'INNER';

      // Ensure we have the tables we need.
      $org_table_alias = $this->query->ensureTable('node__field_primary_parent', $this->relationship, $join);

      $p1 = $this->placeholder() . '[]';

      $snippet = "$org_table_alias.field_primary_parent_target_id " . $this->operator . " $p1";
      $this->query->addWhereExpression($this->options['group'], $snippet, [$p1 => $value]);
    }
  }

  /**
   * Retrieve a single usable int value from the input value.
   *
   * @return int|null
   *   The organization ID, or NULL.
   */
  private function getValue() {
    if ($this->value) {
      return array_map(function ($item) {
        return (int) $item['target_id'];
      }, $this->value);
    }
    return NULL;
  }

  /**
   * Provide simple equality operator.
   */
  public function operatorOptions($which = 'title') {
    $options = [];
    $operators = [
      '=' => [
        'title' => $this->t('Equals'),
        'method' => 'opEmpty',
        'short' => $this->t('equals'),
        'values' => 0,
      ],
      '!=' => [
        'title' => $this->t('Doesn’t equal'),
        'method' => 'opEmpty',
        'short' => $this->t('doesn’t equal'),
        'values' => 0,
      ],
    ];
    foreach ($operators as $id => $info) {
      $options[$id] = $info[$which];
    }

    return $options;
  }

}

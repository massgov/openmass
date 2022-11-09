<?php

namespace Drupal\mass_views\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filters by node's organization count.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("mass_views_node_org_count_filter")
 */
class OrgCountFilter extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);
    $form['value'] = [
      '#type' => 'number',
      '#min' => 1,
      '#step' => 1,
    ];
  }

  public function query() {
    // ONLY add the relationships if we have a value to filter on.

    if ($value = $this->value[0]) {
      // Pre-create the join we need, but convert it to an INNER JOIN for
      // performance.
      $relationship = 'node_field_data';
      $join = $this->query->getJoinData('node__field_organizations', $relationship);
      $join->type = 'INNER';
      // Ensure we have the tables we need.
      $org_table_alias = $this->query->ensureTable('node__field_organizations', $this->relationship, $join);
      $this->query->addGroupBy("$org_table_alias.entity_id");
      $placeholder = $this->placeholder();
      $this->query->addHavingExpression($this->options['group'],"COUNT($org_table_alias.field_organizations_target_id) $this->operator $placeholder", [$placeholder => $value]);
    }
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

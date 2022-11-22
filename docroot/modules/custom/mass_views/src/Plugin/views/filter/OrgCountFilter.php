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

      // Create subquery using database api.
      $sub_query = \Drupal::database()->select('node__field_organizations', 'orgs');
      $sub_query->addField('orgs', 'entity_id');
      $sub_query->addExpression("COUNT(orgs.field_organizations_target_id)", 'orgs_count');
      $sub_query->groupBy("orgs.entity_id");

      $join_definition = [
        'table formula' => $sub_query,
        'field' => 'entity_id',
        'left_table' => 'node_field_data',
        'left_field' => 'nid',
        'adjust' => TRUE,
      ];
      $join = \Drupal::service('plugin.manager.views.join')->createInstance('standard', $join_definition);
      $org_table_alias = $this->query->ensureTable('node__field_organizations', $this->relationship, $join);
      $p1 = $this->placeholder();
      $snippet = "$org_table_alias.orgs_count " . $this->operator . " $p1";
      $this->query->addWhereExpression($this->options['group'], $snippet, [$p1 => $value]);

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
      '>=' => [
        'title' => $this->t('Greater than or equal'),
        'method' => 'opEmpty',
        'short' => $this->t('greater than or equal'),
        'values' => 0,
      ],
    ];
    foreach ($operators as $id => $info) {
      $options[$id] = $info[$which];
    }

    return $options;
  }

}

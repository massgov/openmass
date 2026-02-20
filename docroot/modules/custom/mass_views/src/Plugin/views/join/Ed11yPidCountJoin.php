<?php

namespace Drupal\mass_views\Plugin\views\join;

use Drupal\views\Plugin\views\join\JoinPluginBase;

/**
 * Joins ed11y_action table and groups by pid.
 *
 * @ingroup views_join_handlers
 *
 * @ViewsJoin("ed11y_pid_count_join")
 */
class Ed11YPidCountJoin extends JoinPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildJoin($select_query, $table, $view_query) {
    $pseudoTableAlias = $this->table . '_' . $this->leftTable;

    /** @var \Drupal\mysql\Driver\Database\mysql\Select $subQuery */
    $subQuery = \Drupal::database()->select($this->table, $pseudoTableAlias);
    $subQuery->addField($pseudoTableAlias, 'pid', 'pid');
    $subQuery->addExpression('COUNT(' . $pseudoTableAlias . '.pid)', 'pid_count');
    $subQuery->groupBy($pseudoTableAlias . '.pid');

    $right_table = $subQuery;
    $condition = $this->leftTable . '.pid = ' . $table['alias'] . '.pid';
    $arguments = [];

    $select_query->addJoin($this->type, $right_table, $table['alias'], $condition, $arguments);
  }

}

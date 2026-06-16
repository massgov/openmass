<?php

namespace Drupal\mass_views\Plugin\views\join;

use Drupal\Core\Database\Connection;
use Drupal\views\Plugin\views\join\JoinPluginBase;

/**
 * Joins ed11y_action table and groups by pid.
 *
 * @ingroup views_join_handlers
 *
 * @ViewsJoin("ed11y_pid_count_join")
 */
class Ed11yPidCountJoin extends JoinPluginBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs an Ed11yPidCountJoin object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public function buildJoin($select_query, $table, $view_query) {
    $pseudoTableAlias = $this->table . '_' . $this->leftTable;

    /** @var \Drupal\mysql\Driver\Database\mysql\Select $subQuery */
    $subQuery = $this->database->select($this->table, $pseudoTableAlias);
    $subQuery->addField($pseudoTableAlias, 'pid', 'pid');

    // Only filter dismissals (ed11y_action), not results (ed11y_result).
    if ($this->table === 'ed11y_action') {
      $subQuery->condition($pseudoTableAlias . '.action_type', 'ok');
    }

    $subQuery->addExpression('COUNT(' . $pseudoTableAlias . '.pid)', 'pid_count');
    $subQuery->groupBy($pseudoTableAlias . '.pid');

    $right_table = $subQuery;
    $condition = $this->leftTable . '.pid = ' . $table['alias'] . '.pid';
    $arguments = [];

    $select_query->addJoin($this->type, $right_table, $table['alias'], $condition, $arguments);
  }

}

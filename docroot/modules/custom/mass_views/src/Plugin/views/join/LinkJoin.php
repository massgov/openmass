<?php

namespace Drupal\mass_views\Plugin\views\join;

use Drupal\views\Plugin\views\join\JoinPluginBase;

/**
 * Joins entity tables with link fields.
 *
 * @ingroup views_join_handlers
 *
 * @ViewsJoin("link_join")
 */
class LinkJoin extends JoinPluginBase {

  /**
   * Permits joins using id extracted from uri column of link field.
   *
   * Mostly a copy of JoinPluginBase::buildJoin()
   *
   * {@inheritdoc}
   */
  public function buildJoin($select_query, $table, $view_query) {

    $right_field = "$table[alias].$this->field";
    $left = $view_query->getTableInfo($this->leftTable);
    $left_field = "$left[alias].$this->leftField";

    $node_nid = empty($this->configuration['reverse']) ? $right_field : $left_field;
    $link_uri = empty($this->configuration['reverse']) ? $left_field : $right_field;

    $arguments = [];

    // The link_uri expression needs a delimiter argument.
    $delimiter_placeholder = ':link_internal_delimiter_' . $select_query->nextPlaceholder();
    $arguments[$delimiter_placeholder] = empty($this->configuration['delimiter']) ? '/' : $this->configuration['delimiter'];
    $condition = "SUBSTRING_INDEX($link_uri, $delimiter_placeholder, -1) = $node_nid";

    $pattern_placeholder = ':link_internal_type_' . $select_query->nextPlaceholder();
    $arguments[$pattern_placeholder] = empty($this->configuration['uri_pattern']) ? 'entity:node%' : $this->configuration['uri_pattern'];

    $condition .= " AND $link_uri LIKE $pattern_placeholder";

    // Tack on the defined extras.
    if (isset($this->extra)) {
      if (is_array($this->extra)) {
        $extras = [];
        foreach ($this->extra as $info) {
          // Do not require 'value' to be set; allow for field syntax instead.
          $info += [
            'value' => NULL,
          ];
          // Figure out the table name. Remember, only use aliases provided
          // if at all possible.
          $join_table = '';
          if (!array_key_exists('table', $info)) {
            $join_table = $table['alias'] . '.';
          }
          elseif (isset($info['table'])) {
            // If we're aware of a table alias for this table, use the table
            // alias instead of the table name.
            if (isset($left) && $left['table'] == $info['table']) {
              $join_table = $left['alias'] . '.';
            }
            else {
              $join_table = $info['table'] . '.';
            }
          }

          // If one value, use = instead of IN.
          if (is_array($info['value']) && count($info['value']) == 1) {
            $info['value'] = array_shift($info['value']);
          }
          if (is_array($info['value'])) {
            // We use an SA-CORE-2014-005 conformant placeholder for our array
            // of values. Also, note that the 'IN' operator is implicit.
            // @see https://www.drupal.org/node/2401615.
            $operator = !empty($info['operator']) ? $info['operator'] : 'IN';
            $placeholder = ':views_join_condition_' . $select_query->nextPlaceholder() . '[]';
            $placeholder_sql = "( $placeholder )";
          }
          else {
            // With a single value, the '=' operator is implicit.
            $operator = !empty($info['operator']) ? $info['operator'] : '=';
            $placeholder = $placeholder_sql = ':views_join_condition_' . $select_query->nextPlaceholder();
          }
          // Set 'field' as join table field if available or set 'left field' as
          // join table field is not set.
          if (isset($info['field'])) {
            $join_table_field = "$join_table$info[field]";
            // Allow the value to be set either with the 'value' element or
            // with 'left_field'.
            if (isset($info['left_field'])) {
              $placeholder_sql = "$left[alias].$info[left_field]";
            }
            else {
              $arguments[$placeholder] = $info['value'];
            }
          }
          // Set 'left field' as join table field is not set.
          else {
            $join_table_field = "$left[alias].$info[left_field]";
            $arguments[$placeholder] = $info['value'];
          }
          // Render out the SQL fragment with parameters.
          $extras[] = "$join_table_field $operator $placeholder_sql";
        }

        if ($extras) {
          if (count($extras) == 1) {
            $condition .= ' AND ' . array_shift($extras);
          }
          else {
            $condition .= ' AND (' . implode(' ' . $this->extraOperator . ' ', $extras) . ')';
          }
        }
      }
      elseif ($this->extra && is_string($this->extra)) {
        $condition .= " AND ($this->extra)";
      }
    }

    $select_query->addJoin($this->type, $this->table, $table['alias'], $condition, $arguments);

  }

}

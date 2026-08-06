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
 * @ViewsFilter("mass_views_node_org_filter")
 */
class OrgFilter extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);
    $form['value'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#tags' => TRUE,
      '#selection_settings' => [
        'target_bundles' => ['org_page'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // ONLY add the conditions if we have a value to filter on.
    if ($value = $this->getValue()) {
      $nid_alias = $this->query->ensureTable('node_field_data', $this->relationship);

      // Use EXISTS instead of JOINing node__field_organizations. That table has
      // one row per org delta; a JOIN fans out rows and inflates SUM() fields
      // (e.g. accessibility report issue counts).
      $p_nid = $this->placeholder() . '[]';
      $p_org = $this->placeholder() . '[]';
      if ($this->operator === '!=') {
        $snippet = "$nid_alias.nid NOT IN ($p_nid) AND NOT EXISTS (SELECT 1 FROM {node__field_organizations} nfo WHERE nfo.entity_id = $nid_alias.nid AND nfo.field_organizations_target_id IN ($p_org))";
      }
      else {
        $snippet = "$nid_alias.nid IN ($p_nid) OR EXISTS (SELECT 1 FROM {node__field_organizations} nfo WHERE nfo.entity_id = $nid_alias.nid AND nfo.field_organizations_target_id IN ($p_org))";
      }
      $this->query->addWhereExpression($this->options['group'], $snippet, [
        $p_nid => $value,
        $p_org => $value,
      ]);
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

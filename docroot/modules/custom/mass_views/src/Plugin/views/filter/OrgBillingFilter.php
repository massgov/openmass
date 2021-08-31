<?php

namespace Drupal\mass_views\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filters by node's billing organization.
 *
 * Organization is determined by field_billing_organization, or the NID
 * itself in the case of an org_page node.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("mass_views_node_billing_org_filter")
 */
class OrgBillingFilter extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);
    $form['value'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'taxonomy_term',
      '#tags' => TRUE,
      '#selection_settings' => [
        'target_bundles' => ['billing_organizations'],
      ],
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
      $join = $this->query->getJoinData('node__field_billing_organization', $relationship);
      $join->type = 'INNER';

      // Ensure we have the tables we need.
      $nid_alias = $this->query->ensureTable('node_field_data', $this->relationship);
      $org_table_alias = $this->query->ensureTable('node__field_billing_organization', $this->relationship, $join);

      $p1 = $this->placeholder() . '[]';
      $snippet = "$nid_alias.nid = $p1 OR $org_table_alias.field_billing_organization_target_id = $p1";
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

}

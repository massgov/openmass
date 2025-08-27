<?php

namespace Drupal\mass_views\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filters by a TOP-LEVEL Organization and includes all of its descendants.
 *
 * This filter expects a single org_page node (top-level) and expands to the
 * entire subtree using org_page.field_parent only. The filter is applied by
 * constraining node_field_data.nid to the computed set of org IDs.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("mass_views_node_org_top_parent_filter")
 */
class OrgTopParentFilter extends FilterPluginBase {

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
        'filter' => ['status' => 1],
      ],
      '#description' => $this->t('Choose a TOP-LEVEL Organization. Filter will include that org and all of its descendant orgs.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $top_id = $this->getValue();
    if (!$top_id) {
      return;
    }

    /** @var \Drupal\mass_metatag\Service\MassMetatagUtilities $utils */
    $utils = \Drupal::service('mass_metatag.utilities');

    // Expand to the full subtree (top + all descendants) using field_parent only.
    $org_ids = $utils->getDescendantOrgIds((int) $top_id);
    dump($org_ids);

    if (empty($org_ids)) {
      // No matches — make the view return nothing cheaply.
      $this->query->addWhereExpression($this->options['group'], '1 = 0');
      return;
    }

    // Ensure we have the base node table for filtering by NID.
    $nid_alias = $this->query->ensureTable('node_field_data', $this->relationship);

    // Filter the nodes (org_page rows) by NID IN (...).
    $this->query->addWhere($this->options['group'], "$nid_alias.nid", $org_ids, 'IN');
  }

  /**
   * Retrieve a single usable int value from the input value.
   *
   * @return int|null
   *   The selected top-level organization ID, or NULL.
   */
  private function getValue() {
    if (!empty($this->value) && !empty($this->value[0]['target_id'])) {
      return (int) $this->value[0]['target_id'];
    }
    return NULL;
  }

}

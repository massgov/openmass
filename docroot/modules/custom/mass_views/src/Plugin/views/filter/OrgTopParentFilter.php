<?php

namespace Drupal\mass_views\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;

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
      '#title' => $this->t('Top-level organization qa'),
      '#target_type' => 'node',
      '#tags' => FALSE,
      '#selection_settings' => [
        'target_bundles' => ['org_page'],
      ],
      '#description' => $this->t('Choose a TOP-LEVEL Organization. Filter will include that org and all of its descendant orgs.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (!$this->value) {
      return;
    }
    $top_id = $this->value[0];

    if (!$top_id) {
      return;
    }

    // 1) Build the full set of descendant org ids (including the top).
    $org_ids = $this->buildOrgSubtreeIds($top_id);
    // Ensure the selected top-level org is included.
    if (!in_array((int) $top_id, $org_ids, TRUE)) {
      $org_ids[] = (int) $top_id;
    }

    $nid_alias = $this->query->ensureTable('node_field_data', $this->relationship);

    // Use EXISTS instead of JOINing node__field_organizations. That table has
    // one row per org delta; a JOIN fans out rows and inflates SUM() fields
    // (e.g. accessibility report issue counts: 252 issues x 2 orgs = 504).
    $or_group = $this->query->setWhereGroup('OR');
    $this->query->addWhere($or_group, "$nid_alias.nid", $org_ids, 'IN');
    $placeholder = $this->placeholder() . '[]';
    $this->query->addWhereExpression(
      $or_group,
      "EXISTS (SELECT 1 FROM {node__field_organizations} nfo WHERE nfo.entity_id = $nid_alias.nid AND nfo.deleted = 0 AND nfo.field_organizations_target_id IN ($placeholder))",
      [$placeholder => $org_ids]
    );
  }

  /**
   * Build the set of org_page node IDs in the subtree rooted at $top_id.
   *
   * Includes $top_id itself. Uses iterative BFS to avoid deep recursion.
   *
   * @param int $top_id
   *   The selected top-level organization node id.
   *
   * @return int[]
   *   A de-duplicated list of org_page node ids.
   */
  private function buildOrgSubtreeIds(int $top_id): array {
    $seen = [];
    $queue = [$top_id];

    while (!empty($queue)) {
      // Limit batch size to keep entityQuery "IN" reasonable.
      $batch = array_splice($queue, 0, 200);

      // Mark the current layer as seen.
      foreach ($batch as $id) {
        $seen[(int) $id] = TRUE;
      }

      // Find direct children: org_page nodes where field_parent IN $batch.
      $child_ids = \Drupal::entityQuery('node')
        ->accessCheck(FALSE)
        ->condition('type', 'org_page')
        ->condition('field_parent', $batch, 'IN')
        ->execute();

      if ($child_ids) {
        // Enqueue only unseen children to continue traversal.
        $new = array_diff(array_map('intval', array_values($child_ids)), array_keys($seen));
        $queue = array_merge($queue, $new);
      }
    }

    return array_map('intval', array_keys($seen));
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

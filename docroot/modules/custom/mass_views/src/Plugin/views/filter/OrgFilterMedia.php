<?php

namespace Drupal\mass_views\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filters by media's organization.
 *
 * Organization is determined by field_organization.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("mass_views_media_org_filter")
 */
class OrgFilterMedia extends FilterPluginBase {

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
    // ONLY add the relationships if we have a value to filter on.
    if ($value = $this->getValue()) {
      // Pre-create the join we need, but convert it to an INNER JOIN for
      // performance.
      $relationship = $this->relationship ? $this->relationship : $this->view->storage->get('base_table');
      $join = $this->query->getJoinData('media__field_organizations', $relationship);
      $join->type = 'INNER';

      // Ensure we have the tables we need.
      $org_table_alias = $this->query->ensureTable('media__field_organizations', $this->relationship, $join);

      $p1 = $this->placeholder() . '[]';
      $snippet = "$org_table_alias.field_organizations_target_id = $p1";
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

<?php

namespace Drupal\mass_views\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filters by a list of entity IDs entered via a popup textarea.
 *
 * This filter accepts multiple entity IDs (one per line or comma-separated)
 * and uses an IN() query for exact matching. The exposed form widget renders
 * a button that opens a popup with a textarea, and displays active IDs as
 * removable tags.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("mass_views_entity_ids_filter")
 */
class EntityIdsFilter extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['expose']['contains']['identifier']['default'] = 'entity_ids';
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function valueForm(&$form, FormStateInterface $form_state) {
    $form['value'] = [
      '#type' => 'hidden',
      '#default_value' => is_array($this->value) ? implode(',', $this->value) : ($this->value ?? ''),
      '#attributes' => [
        'class' => ['mass-views-entity-ids-value'],
        'data-entity-ids-label' => $this->options['expose']['label'] ?? $this->t('Entity IDs'),
      ],
      '#attached' => [
        'library' => ['mass_views/entity_ids_filter'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    $identifier = $this->options['expose']['identifier'];
    if (!empty($input[$identifier])) {
      $raw = $input[$identifier];
      $ids = array_filter(
        array_map('intval', preg_split('/[\s,]+/', $raw)),
        fn($v) => $v > 0
      );
      $this->value = array_values(array_unique($ids));
      return !empty($this->value);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (!empty($this->value) && is_array($this->value)) {
      $this->ensureMyTable();
      $id_field = $this->realField;
      $table = $this->tableAlias;
      $placeholder = $this->placeholder() . '[]';
      $this->query->addWhereExpression(
        $this->options['group'],
        "$table.$id_field IN ($placeholder)",
        [$placeholder => $this->value]
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    if (!empty($this->value) && is_array($this->value)) {
      return $this->t('@count IDs', ['@count' => count($this->value)]);
    }
    return '';
  }

  /**
   * Provide simple equality operator.
   */
  public function operatorOptions($which = 'title') {
    $options = [];
    $operators = [
      'in' => [
        'title' => $this->t('Is one of'),
        'method' => 'opSimple',
        'short' => $this->t('in'),
        'values' => 1,
      ],
    ];
    foreach ($operators as $id => $info) {
      $options[$id] = $info[$which];
    }
    return $options;
  }

}

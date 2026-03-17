<?php

namespace Drupal\mass_views\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\ViewsHandlerManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters by media's organization.
 *
 * Organization is determined by field_organization.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("mass_views_media_org_filter")
 */
class OrgFilterMedia extends FilterPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The views join plugin manager.
   *
   * @var \Drupal\views\Plugin\ViewsHandlerManager
   */
  protected $joinManager;

  /**
   * Constructs a new OrgFilterMedia object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ViewsHandlerManager $join_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->joinManager = $join_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.views.join')
    );
  }

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
      // Build the join to media__field_organizations.
      // Use getJoinData if the table is already joined, otherwise create
      // a new join definition manually.
      $relationship = $this->relationship ? $this->relationship : $this->view->storage->get('base_table');
      $join = $this->query->getJoinData('media__field_organizations', $relationship);
      if (!$join) {
        $join = $this->joinManager->createInstance('standard', [
          'table' => 'media__field_organizations',
          'field' => 'entity_id',
          'left_table' => $relationship,
          'left_field' => 'mid',
          'extra' => [
            [
              'field' => 'deleted',
              'value' => '0',
            ],
          ],
        ]);
      }
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

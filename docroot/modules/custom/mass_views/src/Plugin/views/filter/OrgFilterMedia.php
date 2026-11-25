<?php

namespace Drupal\mass_views\Plugin\views\filter;

use Drupal\Core\Database\Connection;
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
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new OrgFilterMedia object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ViewsHandlerManager $join_manager, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->joinManager = $join_manager;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.views.join'),
      $container->get('database')
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
    // ONLY add the join if we have a value to filter on.
    if ($value = $this->getValue()) {
      // Join a de-duplicated set of media ids instead of the raw field table.
      // The autocomplete accepts several organizations, and a document tagged
      // with more than one of them has one media__field_organizations row per
      // match, so joining the field table directly would list it once per
      // matching organization. SELECT DISTINCT removes that fan-out.
      $subquery = $this->database->select('media__field_organizations', 'mfo');
      $subquery->addField('mfo', 'entity_id');
      $subquery->condition('mfo.deleted', 0);
      $subquery->condition('mfo.field_organizations_target_id', $value, 'IN');
      $subquery->distinct();

      // INNER JOIN is always AND'd onto the query and does not honor
      // $this->options['group']. No media Organization filter is currently in
      // an OR group.
      $join = $this->joinManager->createInstance('standard', [
        'table' => 'media__field_organizations',
        'table formula' => $subquery,
        'field' => 'entity_id',
        'left_table' => $this->relationship ?: $this->view->storage->get('base_table'),
        'left_field' => 'mid',
        'type' => 'INNER',
      ]);
      // Handler IDs are unique per display. A hard-coded alias would let
      // Sql::queueTable() silently discard a second instance's subquery.
      $this->query->addTable('media__field_organizations', $this->relationship, $join, 'media_org_set_' . $this->options['id']);
    }
  }

  /**
   * Retrieve usable organization IDs from the input value.
   *
   * @return int[]|null
   *   The organization IDs, or NULL.
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

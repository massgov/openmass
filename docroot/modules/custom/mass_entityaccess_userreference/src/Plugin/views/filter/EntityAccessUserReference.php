<?php

namespace Drupal\mass_entityaccess_userreference\Plugin\views\filter;

use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\views\Plugin\ViewsHandlerManager;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Handle Entity Access User Reference.
 *
 * This view filter will remove any node that is configured with Entity
 * Access User Reference (EAUR) which the current user does not match the
 * list of approved users. It will not filter any content that is not
 * configured to user EAUR.
 *
 * @package Drupal\mass_entityaccess_userreference\Plugin\views\filter
 *
 * @ViewsFilter("mass_entityaccess_userreference_filter")
 */
class EntityAccessUserReference extends FilterPluginBase {

  /**
   * The Views join handler.
   *
   * @var \Drupal\views\Plugin\ViewsHandlerManager
   */
  protected $viewsJoin;

  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * EntityAccessUserReference constructor.
   *
   * @param array $configuration
   *   The configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\views\Plugin\ViewsHandlerManager $views_join
   *   The manager to create join operators.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The currently logged in user.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ViewsHandlerManager $views_join, AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->viewsJoin = $views_join;
    $this->currentUser = $current_user;
    $this->no_operator = TRUE;
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
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Return if the user has permission to bypass entity access user reference.
    if ($this->currentUser->hasPermission('bypass entityaccess userreference')) {
      return;
    }

    $this->ensureMyTable();

    $eaur_config = [
      'table' => 'user_ref_access',
      'field' => 'entity_id',
      'left_table' => 'node_field_data',
      'left_field' => 'nid',
      'operator' => '=',
    ];

    $eaur_join = $this->viewsJoin->createInstance('standard', $eaur_config);

    $this->query->addRelationship('ura', $eaur_join, 'node_field_data');

    $additional_users_config = [
      'table' => 'user_ref_access__additional_users',
      'field' => 'entity_id',
      'left_table' => 'ura',
      'left_field' => 'id',
    ];

    $additional_users_join = $this->viewsJoin->createInstance('standard', $additional_users_config);

    $this->query->addRelationship('uraau', $additional_users_join, 'ura');

    $condition = (new Condition('OR'))
      ->condition('ura.enabled', 1, '!=')
      ->condition('uraau.additional_users_target_id', '***CURRENT_USER***', '=')
      ->condition('node_field_data.uid', '***CURRENT_USER***', '=')
      ->isNull('ura.entity_id');

    $this->query->addWhere($this->options['group'], $condition);

  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    return $this->t('No operators');
  }

}

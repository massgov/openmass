<?php

namespace Drupal\mass_views\Plugin\views\access;

use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Access plugin that provides access control based on the node route parameter.
 *
 * @ingroup views_access_plugins
 *
 * @ViewsAccess(
 *   id = "mass_views_node_argument_access",
 *   title = @Translation("Node Argument access"),
 *   help = @Translation("Access will be granted to users if they have access to the node in the passed argument.")
 * )
 */
class NodeArgumentAccess extends AccessPluginBase {

  /**
   * Node Argument Access Handler service.
   *
   * @var \Drupal\mass_views\NodeArgumentAccessHandler
   */
  protected $nodeArgumentAccessHandler;

  /**
   * Constructs a Permission object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param mixed $nodeArgumentAccessHandler
   *   The nodeArgumentAccessHandler service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $nodeArgumentAccessHandler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->nodeArgumentAccessHandler = $nodeArgumentAccessHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('mass_views.node_argument_access'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    return $this->nodeArgumentAccessHandler->access($account);
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    $route->setRequirement('_custom_access', 'mass_views.node_argument_access::access');
    $options = $route->getOptions();
    $options['parameters']['node']['type'] = 'entity:node';
    $route->setOptions($options);
  }

  public function summaryTitle() {
    return $this->t('Node Argument access');
  }

}

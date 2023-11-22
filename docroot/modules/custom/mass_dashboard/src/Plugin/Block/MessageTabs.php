<?php

namespace Drupal\mass_dashboard\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\MessagesBlockPluginInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Menu\LocalTaskManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a combination of messages and tabs for the site.
 *
 * @Block(
 *   id = "message_tabs",
 *   admin_label = @Translation("Messages & Tabs"),
 * )
 */
class MessageTabs extends BlockBase implements ContainerFactoryPluginInterface, MessagesBlockPluginInterface {

  private $localTaskManager;
  private $routeMatch;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LocalTaskManagerInterface $taskManager, RouteMatchInterface $routeMatch) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->localTaskManager = $taskManager;
    $this->routeMatch = $routeMatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.menu.local_task'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'use mass dashboard');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $cacheability = new CacheableMetadata();
    $primary = $this->localTaskManager->getLocalTasks($this->routeMatch->getRouteName(), 0);
    $secondary = $this->localTaskManager->getLocalTasks($this->routeMatch->getRouteName(), 1);
    $cacheability = $cacheability
      ->merge($primary['cacheability'])
      ->merge($secondary['cacheability']);

    $tabs = [
      '#theme' => 'mass_dashboard_tabs',
      '#primary' => count(Element::getVisibleChildren($primary['tabs'])) > 1 ? $primary['tabs'] : [],
      '#secondary' => count(Element::getVisibleChildren($secondary['tabs'])) > 1 ? $secondary['tabs'] : [],
    ];
    $cacheability->applyTo($tabs);

    return [
      '#theme' => 'mass_dashboard_message_tabs',
      '#messages' => ['#type' => 'status_messages'],
      '#tabs' => $tabs,
    ];
  }

}

<?php

declare(strict_types=1);

namespace Drupal\mass_workbench_ui\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Block that displays the revision state.
 *
 * @Block(
 *   id = "current_revision",
 *   admin_label = @Translation("Current revision state"),
 * )
 */
class WorkbenchModerationCurrentState extends BlockBase implements ContainerFactoryPluginInterface {

  private $routeMatch;

  private EntityTypeManagerInterface $entityTypeManager;

  private AccountInterface $currentUser;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $routeMatch, EntityTypeManagerInterface $entityTypeManager, AccountInterface $currentUser) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $routeMatch;
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // Route is added by the node context, but we'll be explicit.
    return Cache::mergeContexts(['route'], parent::getCacheContexts());
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // We can't use contexts right now because revision routes aren't getting a
    // node context for some reason.
    /** @var \Drupal\node\Entity\Node $node */
    $node = $this->routeMatch->getParameter('node');
    $nodeStorage = $this->entityTypeManager->getStorage('node');

    if (is_a($node, ContentEntityBase::class)) {
      $block = [
        '#markup' => "Current moderation state: " . $node->moderation_state->value,
      ];
      $cacheability = CacheableMetadata::createFromObject($node)
        ->applyTo($block);
      return $block;
    }

  }

  /**
   * {@inheritdoc}
   *
   * Only show this block on node pages to users who have correct permission.
   */
  protected function blockAccess(AccountInterface $account) {
    $route_name = $this->routeMatch->getRouteName();

    // Control access via permission (implicitly adds user.permissions context).
    $has_permission = AccessResult::allowedIfHasPermission($account, 'view current moderation states');

    $routes = [
      'entity.node.canonical',
      'entity.node.latest_version',
      'entity.node.revision',
    ];
    // Hide on non-node routes.
    $on_route = AccessResult::allowedIf(in_array($route_name, $routes));
    $on_route->addCacheContexts(['route']);

    return $has_permission->andIf($on_route);
  }

}

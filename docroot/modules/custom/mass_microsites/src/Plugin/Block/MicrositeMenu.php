<?php

namespace Drupal\mass_microsites\Plugin\Block;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\entity_hierarchy\Storage\NestedSetStorageFactory;
use Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory;
use Drupal\entity_hierarchy_microsite\Entity\MicrositeInterface;
use Drupal\entity_hierarchy_microsite\Plugin\MicrositePluginTrait;
use Drupal\mass_microsites\NearestMicrositeLookup;
use Drupal\node\NodeInterface;
use Drupal\system\Plugin\Block\SystemMenuBlock;
use PNX\NestedSet\NodeKey;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class for a microsite menu.
 *
 * @Block(
 *   id = "mass_microsite_menu",
 *   admin_label = @Translation("Nearest Microsite Menu"),
 *   category = @Translation("Microsite"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Current node"))
 *   }
 * )
 */
class MicrositeMenu extends SystemMenuBlock implements ContainerFactoryPluginInterface {

  use MicrositePluginTrait {
    create as traitCreate;
  }

  protected NestedSetStorageFactory $nestedSetStorageFactory;

  protected NestedSetNodeKeyFactory $nestedSetNodeKeyFactory;

  protected NearestMicrositeLookup $nearestMicrositeLookup;

  /**
   * Constructs a new SystemMenuBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_tree
   *   The menu tree service.
   * @param \Drupal\Core\Menu\MenuActiveTrailInterface $menu_active_trail
   *   The active menu trail service.
   * @param NestedSetStorageFactory $nested_set_storage_factory
   *   The nested set storage service.
   * @param NestedSetNodeKeyFactory $nested_set_node_key_factory
   *   The nested set node key service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MenuLinkTreeInterface $menu_tree, MenuActiveTrailInterface $menu_active_trail, NestedSetStorageFactory $nested_set_storage_factory, NestedSetNodeKeyFactory $nested_set_node_key_factory, NearestMicrositeLookup $nearest_microsite_lookup) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $menu_tree, $menu_active_trail);
    $this->nestedSetStorageFactory = $nested_set_storage_factory;
    $this->nestedSetNodeKeyFactory = $nested_set_node_key_factory;
    $this->nearestMicrositeLookup = $nearest_microsite_lookup;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\mass_microsites\Plugin\Block\MicrositeMenu $instance */
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu.link_tree'),
      $container->get('menu.active_trail'),
      $container->get('entity_hierarchy.nested_set_storage_factory'),
      $container->get('entity_hierarchy.nested_set_node_factory'),
      $container->get('mass_microsites.nearest_microsite_lookup')
    );

    return $instance->setChildOfMicrositeLookup(
      $container->get('entity_hierarchy_microsite.microsite_lookup')
    )->setEntityFieldManager($container->get('entity_field.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $cache = new CacheableMetadata();

    if (!($node = $this->getContextValue('node')) ||
      !($node instanceof NodeInterface) ||
      !($microsites = $this->childOfMicrositeLookup->findMicrositesForNodeAndField($node, $this->configuration['field']))) {
      $build = [];
      if ($node) {
        $cache->addCacheableDependency($node);
      }
      $cache->applyTo($build);
      return $build;
    }

    /** @var MicrositeInterface $microsite */
    $microsite = $this->nearestMicrositeLookup->selectNearestMicrosite($microsites, $node);
    if (!$microsite) {
      return [];
    }
    $cache->addCacheableDependency($node);
    $cache->addCacheableDependency($microsite);
    if ($home = $microsite->getHome()) {
      $cache->addCacheableDependency($home);
    }

    $cache->addCacheTags($this->getSubPagesCacheTags($home));

    $menu_name = $this->getDerivativeId();
    if ($this->configuration['expand_all_items']) {
      $parameters = new MenuTreeParameters();
      $active_trail = $this->menuActiveTrail->getActiveTrailIds($menu_name);
      $parameters->setActiveTrail($active_trail);
    }
    else {
      $parameters = $this->menuTree->getCurrentRouteMenuTreeParameters($menu_name);
    }
    if ($home) {
      $parameters->setRoot('entity_hierarchy_microsite:' . $home->uuid());
    }

    // Adjust the menu tree parameters based on the block's configuration.
    $level = $this->configuration['level'];
    $depth = $this->configuration['depth'];
    $parameters->setMinDepth($level);
    // When the depth is configured to zero, there is no depth limit. When depth
    // is non-zero, it indicates the number of levels that must be displayed.
    // Hence this is a relative depth that we must convert to an actual
    // (absolute) depth, that may never exceed the maximum depth.
    if ($depth > 0) {
      $parameters->setMaxDepth(min($level + $depth - 1, $this->menuTree->maxDepth()));
    }

    // For menu blocks with start level greater than 1, only show menu items
    // from the current active trail. Adjust the root according to the current
    // position in the menu in order to determine if we can show the subtree.
    if ($level > 1) {
      if (count($parameters->activeTrail) >= $level) {
        // Active trail array is child-first. Reverse it, and pull the new menu
        // root based on the parent of the configured start level.
        if ($depth > 0) {
          $parameters->setMaxDepth(min($level - 1 + $depth - 1, $this->menuTree->maxDepth()));
        }
      }
      else {
        $build = [];
        $cache->applyTo($build);
        return $build;
      }
    }

    $tree = $this->menuTree->load($menu_name, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuTree->transform($tree, $manipulators);
    $build = $this->menuTree->build($tree);
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Retrieves cache tags for sub-pages of the given home node.
   *
   * @param \Drupal\node\NodeInterface $home
   *   The node entity representing the home page.
   *
   * @return string[]
   *   An array of cache tags for the sub-pages of the given home node.
   */
  private function getSubPagesCacheTags(NodeInterface $home): array {
    $parent_node = new NodeKey($home->id(), $home->getRevisionId());
    $tree_storage = $this->nestedSetStorageFactory->get($this->configuration['field'], 'node');
    $root_node = $tree_storage->getNode($parent_node);
    $children = $tree_storage->findChildren($root_node->getNodeKey());
    $children_node_cache_tags = [];

    foreach ($children as $child) {
      $children_node_cache_tags[] = 'node:' . $child->getNodeKey()->getId();
    }

    return $children_node_cache_tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeId() {
    return 'entity-hierarchy-microsite';
  }

}

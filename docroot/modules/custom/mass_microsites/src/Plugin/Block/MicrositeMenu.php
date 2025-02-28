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
use Drupal\node\NodeInterface;
use Drupal\system\Plugin\Block\SystemMenuBlock;
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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MenuLinkTreeInterface $menu_tree, MenuActiveTrailInterface $menu_active_trail, NestedSetStorageFactory $nested_set_storage_factory, NestedSetNodeKeyFactory $nested_set_node_key_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $menu_tree, $menu_active_trail);
    $this->nestedSetStorageFactory = $nested_set_storage_factory;
    $this->nestedSetNodeKeyFactory = $nested_set_node_key_factory;
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
      $container->get('entity_hierarchy.nested_set_node_factory')
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
    $microsite = $this->selectNearestMicrosite($microsites, $node);
    $cache->addCacheableDependency($node);
    $cache->addCacheableDependency($microsite);
    if ($home = $microsite->getHome()) {
      $cache->addCacheableDependency($home);
    }
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
   * Select the nearest microsite based on the node's hierarchy.
   *
   * @param array<MicrositeInterface> $microsites
   *   An array of microsites in which the node exists.
   * @param NodeInterface $node
   *   The node.
   *
   * @return MicrositeInterface
   *   The microsite with the fewest pages between the current node and the microsite's "home" page.
   */
  private function selectNearestMicrosite(array $microsites, NodeInterface $node) {
    /**
     * The microsite for which the "homepage" is closest to the current node.
     * @var MicrositeInterface|null
     */
    $nearest_microsite = NULL;
    $microsites_by_home_id = [];

    foreach ($microsites as $microsite) {
      $microsites_by_home_id[$microsite->getHome()->id()] = $microsite;
    }

    if (count($microsites_by_home_id)) {
      $nestedSetStorage = $this->nestedSetStorageFactory->get('field_primary_parent', 'node');
      $key = $this->nestedSetNodeKeyFactory->fromEntity($node);

      /**
       * Array of ancestors in hierarchy, starting with field_primary_parent and climbing upward.
       * @var Node[]
       */
      $ancestors = array_reverse($nestedSetStorage->findAncestors($key));

      foreach ($ancestors as $ancestor) {
        $ancestor_id = $ancestor->getNodeKey()->getId();
        if (
          !$nearest_microsite &&
          isset($microsites_by_home_id[$ancestor_id])
        ) {
          $nearest_microsite = $microsites_by_home_id[$ancestor_id];
        }
      }
    }

    return $nearest_microsite;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeId() {
    return 'entity-hierarchy-microsite';
  }

}

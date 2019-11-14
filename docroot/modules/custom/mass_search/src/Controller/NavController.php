<?php

namespace Drupal\mass_search\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Menu\MenuLinkTree;
use Drupal\Core\Menu\MenuTreeParameters;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class OrgController.
 */
class NavController extends ControllerBase {

  use CorsResponseTrait;

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTree
   */
  private $menuLinkTree;

  /**
   * Constructs a NavController.
   *
   * @param \Drupal\Core\Menu\MenuLinkTree $menu_link_tree
   *   The menu link tree service.
   */
  public function __construct(MenuLinkTree $menu_link_tree) {
    $this->menuLinkTree = $menu_link_tree;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('menu.link_tree')
    );
  }

  /**
   * Returns links for the requested menu tree in a formatted JSON response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The currently active request object.
   * @param string $menu_name
   *   The machine name of the menu to get links for.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   The formatted JSON response.
   */
  public function getLinks(Request $request, $menu_name) {
    $response_array = [];

    $build = $this->buildMenuTree($menu_name);
    if (empty($build)) {
      throw new NotFoundHttpException();
    }

    $cache_metadata = CacheableMetadata::createFromRenderArray($build);

    foreach ($build['#items'] as $item) {
      $response_item = $this->renderMenuItem($item, $cache_metadata);
      $response_item['subNav'] = [];

      if (!empty($item['below'])) {
        foreach ($item['below'] as $sub_item) {
          $sub_nav_item = $this->renderMenuItem($sub_item, $cache_metadata);
          $response_item['subNav'][] = $sub_nav_item;
        }
      }

      $response_array[] = $response_item;
    }

    $response = new CacheableJsonResponse($response_array);
    $this->addCorsHeaderToResponse($cache_metadata, $response, $request);
    $response->addCacheableDependency($cache_metadata);

    return $response;
  }

  /**
   * Builds the render array for the menu tree.
   *
   * @param string $menu_name
   *   The machine name of the menu tree to build.
   *
   * @return array
   *   The render array for the menu tree.
   */
  private function buildMenuTree($menu_name) {
    $build = [];
    $menu_parameters = new MenuTreeParameters();
    $menu_parameters->setMaxDepth(2);

    $tree = $this->menuLinkTree->load($menu_name, $menu_parameters);
    if (!empty($tree)) {
      $manipulators = [
        ['callable' => 'menu.default_tree_manipulators:checkNodeAccess'],
        ['callable' => 'menu.default_tree_manipulators:checkAccess'],
        ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
      ];
      $tree = $this->menuLinkTree->transform($tree, $manipulators);
      $build = $this->menuLinkTree->build($tree);
    }

    return $build;
  }

  /**
   * Renders a menu tree link item and updates the cache metadata.
   *
   * @param array $item
   *   The menu tree link item to render.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache_metadata
   *   The cache metadata to update with info for the current item.
   *
   * @return array
   *   The rendered menu item to add to the JSON response.
   */
  private function renderMenuItem(array $item, CacheableMetadata &$cache_metadata) {
    /** @var \Drupal\Core\Url $url */
    $url = $item['url'];
    $url_data = $url->setAbsolute()->toString(TRUE);
    $cache_metadata->merge($url_data);

    $result = [
      'href' => $url_data->getGeneratedUrl(),
      'text' => $item['title'],
      'active' => $item['in_active_trail'],
    ];

    return $result;
  }

}

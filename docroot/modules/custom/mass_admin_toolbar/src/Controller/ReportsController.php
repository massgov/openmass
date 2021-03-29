<?php

namespace Drupal\mass_admin_toolbar\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * System Manager Service.
 */
class ReportsController extends ControllerBase {

  /**
   * The menu link tree manager.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuTree;

  /**
   * The active menu trail service.
   *
   * @var \Drupal\Core\Menu\MenuActiveTrailInterface
   */
  protected $menuActiveTrail;

  /**
   * A static cache of menu items.
   *
   * @var array
   */
  protected $menuItems;

  /**
   * Constructs a SystemManager object.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_tree
   *   The menu tree manager.
   * @param \Drupal\Core\Menu\MenuActiveTrailInterface $menu_active_trail
   *   The active menu trail service.
   */
  public function __construct(MenuLinkTreeInterface $menu_tree, MenuActiveTrailInterface $menu_active_trail) {
    $this->menuTree = $menu_tree;
    $this->menuActiveTrail = $menu_active_trail;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('menu.link_tree'),
      $container->get('menu.active_trail')
    );
  }

  /**
   * Loads the contents of a menu block.
   *
   * This function is often a destination for these blocks.
   * For example, 'admin/structure/types' needs to have a destination to be
   * valid in the Drupal menu system, but too much information there might be
   * hidden, so we supply the contents of the block.
   *
   * @return array
   *   A render array suitable for
   *   \Drupal\Core\Render\RendererInterface::render().
   */
  public function getBlockContents() {
    // We hard-code the menu name here since otherwise a link in the tools menu
    // or elsewhere could give us a blank block.
    $link = $this->menuActiveTrail->getActiveLink('mass-dashboard');
    if ($link && $content = $this->getAdminBlock($link)) {
      $output = [
        '#theme' => 'admin_block_content',
        '#content' => $content,
      ];
    }
    else {
      $output = [
        '#markup' => $this->t('You do not have any administrative items.'),
      ];
    }
    return $output;
  }

  /**
   * Provide a single block on the administration overview page.
   *
   * @param \Drupal\Core\Menu\MenuLinkInterface $instance
   *   The menu item to be displayed.
   *
   * @return array
   *   An array of menu items, as expected by admin-block-content.html.twig.
   */
  public function getAdminBlock(MenuLinkInterface $instance) {
    $content = [];
    // Only find the children of this link.
    $link_id = $instance->getPluginId();
    $parameters = new MenuTreeParameters();
    $parameters->setRoot($link_id)->excludeRoot()->setTopLevelOnly()->onlyEnabledLinks();
    $tree = $this->menuTree->load(NULL, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuTree->transform($tree, $manipulators);
    foreach ($tree as $key => $element) {
      // Only render accessible links.
      if (!$element->access->isAllowed()) {
        // @todo Bubble cacheability metadata of both accessible and
        //   inaccessible links. Currently made impossible by the way admin
        //   blocks are rendered.
        continue;
      }

      /** @var $link \Drupal\Core\Menu\MenuLinkInterface */
      $link = $element->link;
      $content[$key]['title'] = $link->getTitle();
      $content[$key]['options'] = $link->getOptions();
      $content[$key]['description'] = $link->getDescription();
      $content[$key]['url'] = $link->getUrlObject();
    }
    ksort($content);
    return $content;
  }

}

<?php

namespace Drupal\mass_flagging;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\flag\FlagServiceInterface;
use Drupal\mass_flagging\Service\MassFlaggingFlagContentLinkBuilder;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Toolbar integration handler.
 */
class ToolbarHandler implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The current route.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  private $routeMatch;

  /**
   * Our custom link builder.
   *
   * @var \Drupal\mass_flagging\Service\MassFlaggingFlagContentLinkBuilder
   */
  private $linkBuilder;

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  private $flagService;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  public function __construct(CurrentRouteMatch $routeMatch, FlagServiceInterface $flagService, MassFlaggingFlagContentLinkBuilder $linkBuilder, AccountProxyInterface $account) {
    $this->routeMatch = $routeMatch;
    $this->flagService = $flagService;
    $this->linkBuilder = $linkBuilder;
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_route_match'),
      $container->get('flag'),
      $container->get('mass_flagging.flag_content.link_builder'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(AccountInterface $account) {
    $userHasAccess = AccessResult::allowedIfHasPermission($account, 'mass_flagging flag content');
    if ($watch_flag = $this->flagService->getFlagById('watch_content')) {
      $userHasAccess->orIf($watch_flag->access('flag', $account, TRUE));
    }

    return AccessResult::allowedIf($this->routeMatch->getRouteName() === 'entity.node.canonical')
      ->andIf(
        $userHasAccess
      );
  }

  /**
   * {@inheritdoc}
   */
  public function toolbar() {
    $items = [];
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    if ($this->routeMatch->getRouteName() == 'entity.node.canonical') {
      $entity = $this->routeMatch->getParameter('node');
      if ($entity instanceof NodeInterface) {

        if ($this->checkAccess($this->account)) {

          $flags = $this->flagService->getAllFlags($entity->getEntityTypeId(), $entity->bundle());
          // Add flags to the toolbar.  This duplicates the logic in flag_entity_view,
          // using a placeholder-ed lazy_builder so the links are not stored in the
          // dynamic page cache.
          foreach ($flags as $flag) {
            // Do not display the flag if disabled.
            if (!$flag->status()) {
              continue;
            }

            $flag_link = $flag
              ->getLinkTypePlugin()
              ->getAsFlagLink($flag, $entity);

            // @TODO - refacotr this add correct cache tags.
            $flag_link['#cache']['max-age'] = 0;

            $items["mass_flagging"] = [
              '#type' => 'toolbar_item',
              '#weight' => -100,
              'tab' => [
                $flag_link
              ],
            ];
          }
        }
      }
    }
    return $items;
  }

}

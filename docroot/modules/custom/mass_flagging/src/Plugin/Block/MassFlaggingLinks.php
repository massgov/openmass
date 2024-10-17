<?php

namespace Drupal\mass_flagging\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\mass_flagging\Service\MassFlaggingFlagContentLinkBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Block for flagging links available to editors/authors.
 *
 * @Block(
 *   id="mass_flagging",
 *   admin_label="Content Flags",
 *   category = @Translation("Flags"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *   }
 * )
 */
class MassFlaggingLinks extends BlockBase implements ContainerFactoryPluginInterface {

  private CurrentRouteMatch $routeMatch;
  private MassFlaggingFlagContentLinkBuilder $linkBuilder;
  private FlagServiceInterface $flagService;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CurrentRouteMatch $routeMatch, FlagServiceInterface $flagService, MassFlaggingFlagContentLinkBuilder $linkBuilder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $routeMatch;
    $this->flagService = $flagService;
    $this->linkBuilder = $linkBuilder;
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
      $container->get('flag'),
      $container->get('mass_flagging.flag_content.link_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
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
  public function getCacheContexts() {
    // We shut down access on non-node routes, so we need to depend on the
    // route.name context.
    return Cache::mergeContexts(['route.name'], parent::getCacheContexts());
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $this->getContext('node')->getContextValue();

    $build['#attached']['library'][] = 'mass_flagging/flag-link';

    $flags = $this->flagService->getAllFlags($entity->getEntityTypeId(), $entity->bundle());
    // Add flags to the toolbar.  This duplicates the logic in flag_entity_view,
    // using a placeholder-ed lazy_builder so the links are not stored in the
    // dynamic page cache.
    foreach ($flags as $flag) {
      $build['#cache']['tags'] = $flag->getCacheTags();

      // Do not display the flag if disabled.
      if (!$flag->status()) {
        continue;
      }

      $build['flag_' . $flag->id()] = [
        '#lazy_builder' => [
          'flag.link_builder:build', [
            $entity->getEntityTypeId(),
            $entity->id(),
            $flag->id(),
          ],
        ],
        '#create_placeholder' => TRUE,
      ];
    }

    return $build;
  }

}

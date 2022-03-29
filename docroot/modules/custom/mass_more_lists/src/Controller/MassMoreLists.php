<?php

namespace Drupal\mass_more_lists\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\mass_more_lists\Service\MassMoreListsListBuilder;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MassMoreLists.
 */
class MassMoreLists extends ControllerBase {

  /**
   * Drupal\mass_more_lists\Service\MassMoreListsListBuilder definition.
   *
   * @var \Drupal\mass_more_lists\Service\MassMoreListsListBuilder
   */
  protected $listBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct(MassMoreListsListBuilder $list_builder) {
    $this->listBuilder = $list_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mass_more_lists.list_builder')
    );
  }

  /**
   * Build list.
   *
   * @param \Drupal\node\Entity\Node $node
   *   Node object.
   *
   * @return
   *   Render array for More List page display.
   */
  public function buildList(Node $node): array {
    // Build list data for rendering.
    $more_list = $this->listBuilder->build($node);

    // Assembles overall 'more_list' render array for actual page output.
    $build['more_list'] = [
      '#theme' => 'more_list',
      '#contentEyebrow' => $more_list['contentEyebrow'] ?? '',
      '#pageHeader' => $more_list['pageHeader'] ?? '',
      '#resultsHeading' => $more_list['resultsHeading'] ?? '',
      '#formDownloads' => $more_list['formDownloads'] ?? '',
      '#pager' => [
        '#type' => 'pager',
        '#tags' => [
          '',
          'Previous',
          '',
          'Next',
        ],
      ],
    ];

    return $build;
  }

  /**
   * Custom access checking.
   *
   * @param \Drupal\node\Entity\Node $node
   *   Node passed as parameter to route.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   Whether access is allowed.
   */
  public function access(Node $node) {
    $allowed_bundles = [
      'service_page',
      'service_details',
    ];
    if (!empty($node) && $node instanceof Node) {
      return AccessResult::allowedIf(in_array($node->bundle(), $allowed_bundles));
    }
    else {
      return AccessResult::forbidden();
    }
  }

}

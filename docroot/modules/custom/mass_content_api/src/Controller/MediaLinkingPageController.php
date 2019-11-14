<?php

namespace Drupal\mass_content_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\mass_content_api\DescendantManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\Entity\Node;

/**
 * Class MediaImpactController.
 *
 * @package Drupal\mass_content_api\Controller
 */
class MediaLinkingPageController extends ControllerBase {

  /**
   * DescendantManager service interface.
   *
   * @var \Drupal\mass_content_api\DescendantManagerInterface
   */
  protected $descendantManager;

  /**
   * RequestStack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * MediaImpactController constructor.
   *
   * @param \Drupal\mass_content_api\DescendantManagerInterface $descendantManager
   *   Descendant manager interface.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack that controls the lifecycle of requests.
   */
  public function __construct(DescendantManagerInterface $descendantManager, RequestStack $requestStack) {
    $this->descendantManager = $descendantManager;
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('descendant_manager'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $help_url = Url::fromUri('https://massgovdigital.gitbook.io/knowledge-base/tools-for-improving-your-content/pages-linking-here');
    $help_text = Link::fromTextAndUrl('Learn how to use Linking Pages.', $help_url)->toString();
    $output = [];
    $output['linking_nodes'] = [
      '#type' => 'table',
      '#caption' => $this->t('The list below shows pages that include a link to this document. However, it DOES NOT include pages that that link to this document through inline links in a rich text editor — it only includes links added through structured fields. @help_text', ['@help_text' => $help_text]),
      '#header' => [
        $this->t('Node'),
        $this->t('NID'),
        $this->t('Node Type'),
      ],
      '#empty' => $this->t('No pages link here.')
    ];
    $media_id = $this->requestStack->getCurrentRequest()->attributes->get('media');
    $children = $this->descendantManager->getImpact($media_id, 'media');
    foreach ($children as $k => $child_id) {
      $child_node = Node::load($child_id);
      $label = $child_node->label();
      $child_link = Url::fromRoute('entity.node.canonical', ['node' => $child_id]);
      $output['linking_nodes'][$k]['node'][] = [
        '#type' => 'link',
        '#title' => $label,
        '#url' => $child_link,
      ];
      $output['linking_nodes'][$k]['nid'][] = [
        '#type' => 'item',
        '#title' => $child_id,
      ];
      $output['linking_nodes'][$k]['type'][] = [
        '#type' => 'item',
        '#title' => $child_node->getType(),
      ];
    }
    return $output;
  }

}

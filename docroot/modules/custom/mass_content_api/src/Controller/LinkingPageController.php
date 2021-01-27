<?php

namespace Drupal\mass_content_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\mass_content_api\DescendantManagerInterface;
use Drupal\mass_content_api\FieldProcessingTrait;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\Entity\Node;

/**
 * Class ImpactController.
 *
 * @package Drupal\mass_content_api\Controller
 */
class LinkingPageController extends ControllerBase {
  use FieldProcessingTrait;

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
   * ImpactAnalysisController constructor.
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
    $help_url = Url::fromUri('https://massgovdigital.gitbook.io/knowledge-base/content-improvement-tools/pages-linking-here');
    $help_text = Link::fromTextAndUrl('Learn how to use Linking Pages.', $help_url)->toString();
    $output = [];
    $output['linking_nodes'] = [
      '#type' => 'table',
      '#caption' => $this->t('The list below shows pages that include a link to this page. However, it DOES NOT include pages that link to this one through inline links in a rich text editor — it only includes links added through structured fields. @help_text', ['@help_text' => $help_text]),
      '#header' => [
        $this->t('Title'),
        $this->t('ID'),
        $this->t('Content Type'),
        $this->t('Field Label'),
      ],
      '#empty' => $this->t('No pages link here.'),
    ];
    $nid = $this->requestStack->getCurrentRequest()->attributes->get('node');
    $children = $this->descendantManager->getImpact($nid, 'node');

    $used_links = [];
    if (!empty($children)) {
      $unique = array_unique($children);
      foreach ($unique as $k => $child) {
        $child_node = Node::load($child);

        if ($child_node instanceof Node) {
          $field_names = $this->fetchNodeTypeConfig($child_node);
          $descendants = $this->fetchRelations($child_node, $field_names);

          foreach ($descendants as $dependency_status => $fields) {
            foreach ($fields as $name => $field) {
              if ($dependency_status === 'linking_pages') {
                foreach ($field as $field_info) {
                  if ($field_info['id'] == $nid) {
                    $used_links[$child][] = [
                      'label' => $field_info['field_label'],
                      'used' => 0,
                    ];
                  }
                }
              }
            }
          }

          $label = $child_node->label();
          $child_link = Url::fromRoute('entity.node.canonical', ['node' => $child]);
          $output['linking_nodes'][$k]['node'][] = [
            '#type' => 'link',
            '#title' => $label,
            '#url' => $child_link,
          ];
          $output['linking_nodes'][$k]['nid'][] = [
            '#type' => 'item',
            '#title' => $child,
          ];
          $output['linking_nodes'][$k]['type']['entity'][] = [
            '#type' => 'item',
            '#title' => $child_node->type->entity->label(),
          ];
          if (!empty($used_links)) {
            foreach ($used_links as $child_nid => $labels) {
              if ($child_nid == $child_node->id()) {
                foreach ($labels as $index => $label) {
                  if ($label['used'] == 0) {
                    $output['linking_nodes'][$k]['field_label'][] = [
                      '#type' => 'item',
                      '#title' => $label['label'],
                    ];
                    $used_links[$child_nid][$index]['used'] = 1;
                  }
                }
              }
            }
          }
        }
      }
    }
    return $output;
  }

}

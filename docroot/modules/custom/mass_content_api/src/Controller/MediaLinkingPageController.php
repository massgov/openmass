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
use Drupal\Core\Path\PathValidatorInterface;

/**
 * Class MediaImpactController.
 *
 * @package Drupal\mass_content_api\Controller
 */
class MediaLinkingPageController extends ControllerBase {
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
   * Path Validator service.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * MediaImpactController constructor.
   *
   * @param \Drupal\mass_content_api\DescendantManagerInterface $descendantManager
   *   Descendant manager interface.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack that controls the lifecycle of requests.
   * @param Drupal\Core\Path\PathValidatorInterface $pathValidator
   *   Path Validator interface.
   */
  public function __construct(DescendantManagerInterface $descendantManager, RequestStack $requestStack, PathValidatorInterface $pathValidator) {
    $this->descendantManager = $descendantManager;
    $this->requestStack = $requestStack;
    $this->pathValidator = $pathValidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('descendant_manager'),
      $container->get('request_stack'),
      $container->get('path.validator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $help_url = Url::fromUri('https://www.mass.gov/kb/pages-linking-here');
    $help_text = Link::fromTextAndUrl('Learn how to use Pages Linking Here.', $help_url)->toString();
    $output = [];
    $output['linking_nodes'] = [
      '#type' => 'table',
      '#caption' => $this->t('The list below shows pages that include a link to this document in structured and rich text fields. @help_text', ['@help_text' => $help_text]),
      '#header' => [
        $this->t('Node'),
        $this->t('NID'),
        $this->t('Node Type'),
        $this->t('Field Label'),
      ],
      '#empty' => $this->t('No pages link here.'),
    ];
    $media_id = $this->requestStack->getCurrentRequest()->attributes->get('media')->id();
    $children = $this->descendantManager->getImpact($media_id, 'media');
    $used_links = [];
    if (!empty($children)) {
      $unique = array_unique($children);
      foreach ($unique as $k => $child_id) {
        $child_node = Node::load($child_id);

        $field_names = $this->fetchNodeTypeConfig($child_node);
        $descendants = $this->fetchRelations($child_node, $field_names);

        foreach ($descendants as $dependency_status => $fields) {
          foreach ($fields as $name => $field) {
            if ($dependency_status === 'linking_pages') {
              foreach ($field as $field_info) {
                if ($field_info['id'] == $media_id) {
                  $used_links[$child_id][] = [
                    'label' => $field_info['field_label'],
                    'used' => 0,
                  ];
                }
              }
            }
          }
        }

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
    $total = count(array_filter($output['linking_nodes'], 'is_numeric', ARRAY_FILTER_USE_KEY));
    $output['linking_nodes']['#prefix'] = $total . ' total records.';
    return $output;
  }

}

<?php

namespace Drupal\mass_content_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\mass_content_api\DescendantManagerInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class DescendantController.
 *
 * @package Drupal\mass_content_api\Controller
 */
class DescendantController extends ControllerBase {

  /**
   * Drupal\Core\Entity\EntityTypeBundleInfo definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $bundleInfo;

  /**
   * Drupal\mass_content_api\DescendantManagerInterface definition.
   *
   * @var \Drupal\mass_content_api\DescendantManagerInterface
   */
  protected $descendantManager;

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Entity\Query\QueryFactory definition.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQuery;

  /**
   * Symfony\Component\HttpFoundation\RequestStack definition.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $requestStack;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeBundleInfoInterface $bundle_info, DescendantManagerInterface $descendant_manager, EntityTypeManager $entity_type_manager, RequestStack $request_stack) {
    $this->bundleInfo = $bundle_info;
    $this->descendantManager = $descendant_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityQuery = $entity_type_manager->getStorage('node')->getQuery();
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('descendant_manager'),
      $container->get('entity_type.manager'),
      $container->get('request_stack')
    );
  }

  /**
   * Build a display of content descendants.
   *
   * GET parameters can be used to specify the content to return.
   *   - id: specify to show descendants of this node specifically.
   *   - content_type: specify to show descendants of this type.
   *   - format: specify 'depth' if you would like to see multiple levels of
   *     relationships instead of all in just one list.
   *   Examples:
   *     - ?id=1234
   *     - ?content_type=service_page&format=depth
   * If id and content_type are both specified, only content_type will be used.
   *
   * @return array
   *   The render array to display on the page.
   */
  public function build() {
    $output = '<h2>Parameters help</h2>';
    $output .= 'Add parameters to the url to show filtered or adjust output.';
    $output .= '<ul><li>Use <em>?id=[node id]</em> to see descendants for a specific ID. By default you will see a nested tree view of depth MAX_DEPTH.';
    $output .= '<li>Add <em>&depth=[number between 1 and MAX_DEPTH]</em> to see descendants only up to desired level of depth.';
    $output .= '<li>Add <em>&flat=yes</em> to see output as a flat list instead of a nested tree.</li>';
    $output .= '<li>Use <em>?content_type=[content_type]</em> to see descendants for a specific Content Type.</li></ul>';

    $output .= '<h3>Examples</h3>';
    $output .= '<ul><li>/admin/config/content/descendants?id=1234</li>';
    $output .= '<li>/admin/config/content/descendants?id=1234&depth=3</li>';
    $output .= '<li>/admin/config/content/descendants?id=1234&depth=4&flat=yes</li>';
    $output .= '<li>/admin/config/content/descendants?content_type=service_page</li></ul>';
    $query_params = $this->requestStack->getCurrentRequest()->query->all();
    if (!empty($query_params['id']) || !empty($query_params['content_type'])) {
      // By default we prefer to show descendants as tree, but with a `flat=1` query param, a flat list can be shown.
      $show_flat = FALSE;
      if (isset($query_params['flat']) && $query_params['flat'] == 'yes') {
        $show_flat = TRUE;
      }
      // Set traversal depth to the maximum that we allow, or a custom value less then that passed via query params.
      $traversal_depth = $this->descendantManager::MAX_DEPTH;
      if (isset($query_params['depth']) && (int) $query_params['depth'] < $traversal_depth && (int) $query_params['depth'] > 0) {
        $traversal_depth = (int) $query_params['depth'];
      }
      $format = MASS_CONTENT_API_FLAT;
      if (!empty($query_params['format'])) {
        $format = ($query_params['format'] != MASS_CONTENT_API_DEPTH);
      }
      if (!empty($query_params['content_type'])) {
        $bundles = $this->bundleInfo->getBundleInfo('node');
        if (in_array($query_params['content_type'], array_keys($bundles))) {
          // Load and print descendant tree for all nodes of this bundle.
          $query = $this->entityQuery
            ->condition('type', $query_params['content_type'])
            ->condition('status', 1)
            ->pager(20);
          $results = $query->execute();
          foreach ($results as $result) {
            $node = $this->entityTypeManager->getStorage('node')->load($result);
            if (!empty($node)) {
              $output .= $this->printDescendants($node, $show_flat);
            }
          }
        }
      }
      elseif (!empty($query_params['id'])) {
        // Load and print descendant tree for just this node.
        $node = $this->entityTypeManager->getStorage('node')
          ->load($query_params['id']);
        if (!empty($node)) {
          $output .= $this->printDescendants($node, $show_flat, $traversal_depth);
        }
      }
    }

    return [
      'descendants' => [
        '#type' => 'markup',
        '#markup' => $output,
      ],
      'pager' => [
        '#type' => 'pager',
      ],
    ];
  }

  /**
   * Build a display to test relationships.
   *
   * @return array
   *   The render array to display test information.
   */
  public function relationships() {
    $orgs = [
      '#type' => 'markup',
      '#markup' => $this->t('No organizations defined.'),
    ];
    $services = [
      '#type' => 'markup',
      '#markup' => $this->t('No services defined.'),
    ];
    $topics = [
      '#type' => 'markup',
      '#markup' => $this->t('No topics defined.'),
    ];
    $parents = [
      '#type' => 'markup',
      '#markup' => $this->t('No ancestors defined.'),
    ];
    $output = $this->t('<h2>Parameters help</h2> Add or update the id parameter in the url to show a specific node (Use <em>?id=[node id]</em> to see relationships for a specific ID.)');
    $query_params = $this->requestStack->getCurrentRequest()->query->all();
    if (!empty($query_params['id'])) {
      $node = $this->entityTypeManager->getStorage('node')
        ->load($query_params['id']);
      if (!empty($node)) {
        $output .= $this->printRecord($node);
      }
      if ($org_ids = $this->descendantManager->getOrganizations($query_params['id'])) {
        $org_nodes = $this->entityTypeManager->getStorage('node')
          ->loadMultiple($org_ids);
        foreach ($org_nodes as $node) {
          $orgs[] = [
            '#type' => 'markup',
            '#markup' => $this->printRecord($node, 'h3'),
          ];
        }
      }
      if ($service_ids = $this->descendantManager->getServices($query_params['id'])) {
        $service_nodes = $this->entityTypeManager->getStorage('node')
          ->loadMultiple($service_ids);
        foreach ($service_nodes as $node) {
          $services[] = [
            '#type' => 'markup',
            '#markup' => $this->printRecord($node, 'h3'),
          ];
        }
      }
      if ($topic_ids = $this->descendantManager->getTopics($query_params['id'])) {
        $topics = $this->levelPrint($topic_ids);
      }
      if ($parent_ids = $this->descendantManager->getParents($query_params['id'])) {
        $parents = $this->levelPrint($parent_ids, 'id');
      }
    }
    return [
      'relationships' => [
        '#type' => 'markup',
        '#markup' => $output,
      ],
      'org' => [
        '#type' => 'details',
        '#title' => $this->t('Organizations'),
        '#open' => TRUE,
        'value' => $orgs,
      ],
      'services' => [
        '#type' => 'details',
        '#title' => $this->t('Services'),
        '#open' => TRUE,
        'value' => $services,
      ],
      'topics' => [
        '#type' => 'details',
        '#title' => $this->t('Topics'),
        '#open' => TRUE,
        'value' => $topics,
      ],
      'ancestors' => [
        '#type' => 'details',
        '#title' => $this->t('Ancestors'),
        '#open' => TRUE,
        'value' => $parents,
      ],
    ];
  }

  /**
   * Prepare a level representation for printing.
   *
   * @param array $levels
   *   The array defining the items to print.
   * @param string $key
   *   An optional key to use instead of the base item.
   *
   * @return array
   *   A render array representation of the levels.
   */
  private function levelPrint(array $levels, $key = '') {
    $output = [];
    foreach ($levels as $level => $items) {
      $paren = ($level == 1 ? $this->t(' (Parents)') : '');
      $paren .= ($level == 2 ? $this->t(' (Grandparents)') : '');
      $output[$level] = [
        '#type' => 'details',
        '#title' => $this->t('Level @level', ['@level' => $level]) . $paren,
        '#open' => TRUE,
      ];
      foreach ($items as $item) {
        $id = (empty($key) ? $item : $item[$key]);
        $node = $this->entityTypeManager->getStorage('node')
          ->load($id);
        if (!empty($node)) {
          $output[$level][] = [
            '#type' => 'markup',
            '#markup' => $this->printRecord($node, 'h3'),
          ];
        }
      }
    }
    return $output;
  }

  /**
   * Print the descendants of a specific node.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node object to use for printing.
   * @param bool $flag_show_flat
   *   If this flag is set then descendants are printed in a flat list.
   * @param int $traversal_depth
   *   A number between 1 and MAX_DEPTH to determine how many layers of childred we should traverse.
   *
   * @return string
   *   The markup for the descendants.
   */
  private function printDescendants(Node $node, bool $flag_show_flat, int $traversal_depth) {
    $output = $this->printRecord($node);

    if ($flag_show_flat) {
      $children = $this->descendantManager->getChildrenFlat($node->id(), $traversal_depth);
      $output .= $this->printChildren($children);
    }
    else {
      $children = $this->descendantManager->getChildrenTree($node->id(), $traversal_depth);
      $output .= $this->printChildrenDepth($children);
    }

    return $output;
  }

  /**
   * Prepare a record for print.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node object to use to print the record.
   * @param string $tag
   *   The tag to wrap the record in.
   *
   * @return string
   *   The markup output of the record.
   */
  private function printRecord(Node $node, $tag = 'h2') {
    $output = '<' . $tag . '><a href="' . $node->toUrl()->toString() . '">';
    $output .= $node->label() . '</a>';
    $output .= ' (' . $node->id() . ' | ' . $node->bundle() . ' | ';
    $output .= '<a href="' . $node->toUrl('edit-form')
      ->toString() . '">edit</a> ).';
    $output .= '</' . $tag . '>';

    return $output;
  }

  /**
   * Print the children in the descendant output.
   *
   * @param array $children
   *   A list of ids identified as children.
   *
   * @return string
   *   The flat list of the children.
   */
  private function printChildren(array $children) {
    $output = '<ol>';
    foreach ($children as $child) {
      $child_node = $this->entityTypeManager->getStorage('node')->load($child);
      if (!empty($child_node)) {
        $output .= '<li><a href="' . $child_node->toUrl()->toString() . '">';
        $output .= $child_node->label() . '</a>';
        $output .= ' (' . $child_node->id() . ' | ' . $child_node->bundle();
        $output .= ' | <a href="';
        $output .= $child_node->toUrl('edit-form')->toString();
        $output .= '">edit</a> ).</li>';
      }
    }
    $output .= '</ol>';

    return $output;
  }

  /**
   * Print the children in the descendant output.
   *
   * @param array $children
   *   A list of ids identified as children.
   *
   * @return string
   *   The list of the children with depth.
   */
  private function printChildrenDepth(array $children) {
    $output = '<ol>';
    foreach ($children as $child) {
      $child_node = $this->entityTypeManager->getStorage('node')
        ->load($child['id']);
      if (!empty($child_node)) {
        $output .= '<li><a href="' . $child_node->toUrl()->toString() . '">';
        $output .= $child_node->label() . '</a>';
        $output .= ' (' . $child_node->id() . ' | ' . $child_node->bundle();
        $output .= ' | <a href="';
        $output .= $child_node->toUrl('edit-form')->toString();
        $output .= '">edit</a> ).';
        $output .= $this->printChildrenDepth($child['children']);
        $output .= '</li>';
      }
    }
    $output .= '</ol>';

    return $output;
  }

}

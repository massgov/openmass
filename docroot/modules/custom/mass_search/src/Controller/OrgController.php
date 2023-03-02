<?php

namespace Drupal\mass_search\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class OrgController.
 */
class OrgController extends ControllerBase {

  use CorsResponseTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current active database's master connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a OrgController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The current active database's master connection.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database')
    );
  }

  /**
   * Gets all published Organization node IDs from the system.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
   *   A storage instance.
   *
   * @return array
   *   The non-QA Organization node IDs.
   */
  protected function getOrgNids(EntityStorageInterface $node_storage) {
    $query = $node_storage->getQuery()
      ->condition('type', 'org_page')
      ->condition('status', 1)
      ->condition('title', '\_QA%', 'NOT LIKE')
      ->sort('title', 'ASC');

    return $query->accessCheck(FALSE)->execute();
  }

  /**
   * Returns Orgs in a formatted JSON response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The currently active request object.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   The formatted JSON response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function listOrgs(Request $request) {
    $response_array = [];
    $node_storage = $this->entityTypeManager->getStorage('node');

    $org_nids = $this->getOrgNids($node_storage);

    /** @var \Drupal\node\Entity\Node[] $entities */
    $entities = $node_storage->loadMultiple($org_nids);
    foreach ($entities as $entity) {
      $response_array[] = $this->formatCommonOrgData($entity);
    }

    return $this->completeResponse($response_array, $request);
  }

  /**
   * Returns Orgs and additional content details in a formatted JSON response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The currently active request object.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   The formatted JSON response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function listOrgsDetail(Request $request) {
    $response_array = [];
    $node_storage = $this->entityTypeManager->getStorage('node');

    $org_nids = $this->getOrgNids($node_storage);

    // Gather organization content type info.
    $query = $this->database->select('node__field_organizations', 'f');
    $query->addExpression('GROUP_CONCAT(DISTINCT bundle)', 'types');
    $query->fields('f', ['field_organizations_target_id'])
      ->condition('bundle', 'org_page', '<>');
    $query->groupBy('field_organizations_target_id');
    $result = $query->execute();
    $content_info = $result->fetchAllKeyed();

    /** @var \Drupal\node\Entity\Node[] $entities */
    $entities = $node_storage->loadMultiple($org_nids);
    foreach ($entities as $entity) {
      $item = $this->formatCommonOrgData($entity);
      if (isset($content_info[$item['nid']])) {
        $item['contentInfo'] = explode(',', $content_info[$item['nid']]);
      }

      $response_array[] = $item;
    }

    return $this->completeResponse($response_array, $request);
  }

  /**
   * Creates an item for the response listing with common Organization info.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The Organization node to create a response item from.
   *
   * @return array
   *   The formatted Organization item.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  private function formatCommonOrgData(Node $node) {
    $url = $node->toUrl('canonical', ['absolute' => TRUE])->toString(TRUE);

    $image_url = '';
    $image_field = $node->get('field_sub_brand');
    if (!empty($image_field->entity)) {
      $image_url = $image_field->entity->getFileUri();
      $image_url = ImageStyle::load('thumbnail')->buildUrl($image_url);
    }

    $result = [
      'nid' => (int) $node->id(),
      'name' => $node->getTitle(),
      'acronym' => $node->get('field_title_sub_text')->getString(),
      'url' => $url->getGeneratedUrl(),
      'logoUrl' => $image_url,
      'description' => $node->get('field_sub_title')->getString(),
    ];

    return $result;
  }

  /**
   * Creates the JSON response and completes cache handling.
   *
   * @param array $response_content
   *   The content to return in the response.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The currently active request object.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   The formatted JSON response.
   */
  private function completeResponse(array $response_content, Request $request) {
    // Add cache tags so the endpoint results will update when nodes are
    // updated.
    $cache_tags = ['node_list:news', 'node_list:org_page'];
    $cache_metadata = new CacheableMetadata();
    $cache_metadata->setCacheTags($cache_tags);

    // Create the JSON response object.
    $response = new CacheableJsonResponse($response_content);
    $this->addCorsHeaderToResponse($cache_metadata, $response, $request);
    $response->addCacheableDependency($cache_metadata);

    return $response;
  }

}

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
use Drupal\entity_hierarchy_microsite\Entity\Microsite;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class MicrositeController.
 */
class MicrositeController extends ControllerBase {

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
  protected function getMicrositeIds(EntityStorageInterface $microsite_storage) {
    $query = $microsite_storage->getQuery()
      ->condition('name', '\A Test Microsite%', 'NOT LIKE')
      ->sort('name', 'ASC');

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
  public function listMicrosites(Request $request) {
    $response_array = [];
    $microsite_storage = $this->entityTypeManager->getStorage('entity_hierarchy_microsite');

    $microsite_ids = $this->getMicrositeIds($microsite_storage);

    /** @var \Drupal\entity_hierarchy_microsite\Entity\Microsite[] $entities */
    $entities = $microsite_storage->loadMultiple($microsite_ids);
    $cache_metadata = new CacheableMetadata();
    foreach ($entities as $entity) {
      $response_array[] = $this->formatCommonMicrositeData($entity);
      $cache_metadata->addCacheableDependency($entity);
      $cache_metadata->addCacheableDependency($entity->getHome());
    }

    return $this->completeResponse($response_array, $request, $cache_metadata);
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
  private function formatCommonMicrositeData(Microsite $microsite) {
    $home = $microsite->getHome();
    $url = $home->toUrl('canonical', ['absolute' => TRUE])->toString(TRUE);

    $result = [
      'id' => (int) $microsite->id(),
      'name' => $microsite->label(),
      'url' => $url->getGeneratedUrl(),
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
  private function completeResponse(array $response_content, Request $request, CacheableMetadata $cache_metadata) {

    $definition = $this->entityTypeManager->getDefinition('entity_hierarchy_microsite');
    $cache_metadata->setCacheTags($definition->getListCacheTags());

    // Create the JSON response object.
    $response = new CacheableJsonResponse($response_content);
    $this->addCorsHeaderToResponse($cache_metadata, $response, $request);
    $response->addCacheableDependency($cache_metadata);

    return $response;
  }
}

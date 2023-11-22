<?php

namespace Drupal\mass_search\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class NewsController.
 */
class NewsController extends ControllerBase {

  use CorsResponseTrait;

  /**
   * Return the recent News nodes in a formatted JSON response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The currently active request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The formatted JSON response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\Exception\UndefinedLinkTemplateException
   */
  public function listRecent(Request $request) {
    $response_array = [];
    $node_storage = $this->entityTypeManager()->getStorage('node');

    // Create UTC timezone variable for later (re)use.
    $utc_timezone = new \DateTimeZone('UTC');

    // Calculate the time cutoff for the query.
    $date_cutoff_raw = new DrupalDateTime('-2 days', $utc_timezone);
    $date_cutoff = $date_cutoff_raw->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    // Get all published News nodes from within the last 48 hours.
    $node_query = $node_storage->getQuery()->accessCheck(FALSE);
    $nids = $node_query->condition('type', 'news')
      ->condition('status', 1)
      ->condition('title', '_QA%', 'NOT LIKE')
      ->condition('field_date_published', $date_cutoff, '>')
      ->sort('field_date_published', 'DESC')
      ->execute();

    /** @var \Drupal\node\NodeInterface[] $entities */
    $entities = $node_storage->loadMultiple($nids);

    // Format the response for each matching News node. Store URLs to add to
    // cache dependencies later.
    $urls = [];
    foreach ($entities as $entity) {
      $signees = [];

      // Format each signee_name given the available data for each one.
      foreach ($entity->get('field_news_signees')->getValue() as $signee) {
        $paragraph = $this->entityTypeManager()
          ->getStorage('paragraph')
          ->loadRevision($signee['target_revision_id']);

        // External and State Organizations use different fields to store names.
        switch ($paragraph->bundle()) {
          case 'external_organization':
            $signees[] = $paragraph->get('field_external_org_name')->value;
            break;

          case 'state_organization':
            $signee_org_id = $paragraph->get('field_state_org_ref_org')->target_id;

            /** @var \Drupal\node\NodeInterface $signee_org */
            $signee_org = $node_storage->load($signee_org_id);
            $signees[] = $signee_org->getTitle();
            break;
        }
      }

      // Store this Url object so we can add it as a cacheable dependency later.
      $urls[] = $url = $entity->toUrl('canonical', ['absolute' => TRUE])
        ->toString(TRUE);
      $published_raw = $entity->get('field_date_published')->getString();
      $published_local = new DrupalDateTime($published_raw, $utc_timezone);
      $response_array[] = [
        'nid' => (int) $entity->id(),
        'title' => $entity->getTitle(),
        'url' => $url->getGeneratedUrl(),
        'type' => $entity->get('field_news_type')->getString(),
        'datePublished' => $published_local->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT) . 'Z',
        'signees' => $signees,
      ];
    }

    // Add the node_list cache tag so the endpoint results will update when nodes are
    // updated.
    $cache_metadata = new CacheableMetadata();
    $cache_metadata->setCacheTags(['node_list:news']);

    // Create the JSON response object and add the cache metadata and urls.
    $response = new CacheableJsonResponse($response_array);
    $this->addCorsHeaderToResponse($cache_metadata, $response, $request);
    $response->addCacheableDependency($cache_metadata);

    return $response;
  }

}

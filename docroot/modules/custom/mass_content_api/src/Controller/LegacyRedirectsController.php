<?php

namespace Drupal\mass_content_api\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Pager\PagerParametersInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controls legacy redirects.
 */
class LegacyRedirectsController extends ControllerBase {

  const LIMIT = 1000;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * The Pager param service.
   *
   * @var \Drupal\Core\Pager\PagerParametersInterface
   */
  private $pagerParams;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('pager.parameters')
    );
  }

  /**
   * Constructor.
   */
  public function __construct(Connection $database, PagerParametersInterface $pager_params) {
    $this->database = $database;
    $this->pagerParams = $pager_params;
  }

  /**
   * Controller for /redirects-prod.json.
   */
  public function redirectsProd() {
    $query = $this->database->select('node_field_data', 'n');
    $query->innerJoin('node__field_legacy_redirects_ref_conte', 'd', 'n.nid = d.entity_id');
    $query->innerJoin('node__field_legacy_redirects_legacyurl', 's', 'n.nid = s.entity_id');
    $query->innerJoin('node__field_legacy_redirect_env', 'e', 'n.nid = e.entity_id AND e.field_legacy_redirect_env_value = 0');
    $query->addField('s', 'field_legacy_redirects_legacyurl_value', 'source');
    $query->addField('d', 'field_legacy_redirects_ref_conte_target_id', 'dest');
    $query->condition('n.status', 1);
    $query->addTag('node_access');

    return $this->buildResponse($query);
  }

  /**
   * Controller for /redirects-staged.json.
   */
  public function redirectsStaged() {
    $query = $this->database->select('node_field_data', 'n');
    $query->innerJoin('node__field_legacy_redirects_ref_conte', 'd', 'n.nid = d.entity_id');
    $query->innerJoin('node__field_legacy_redirects_legacyurl', 's', 'n.nid = s.entity_id');
    $query->innerJoin('node__field_legacy_redirect_env', 'e', 'n.nid = e.entity_id');
    $query->addField('s', 'field_legacy_redirects_legacyurl_value', 'source');
    $query->addField('d', 'field_legacy_redirects_ref_conte_target_id', 'dest');
    $query->condition('n.status', 1);
    $query->addTag('node_access');

    return $this->buildResponse($query);
  }

  /**
   * Given a query, build the appropriate paged response.
   */
  private function buildResponse(SelectInterface $query) {
    $count = $query->countQuery()->execute()->fetchField();
    $query = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender');
    $query->limit(self::LIMIT);
    $results = [];
    $metadata = new CacheableMetadata();
    // Make sure we have the legacy redirects tag and the pagers context.
    $metadata->addCacheTags(['handy_cache_tags:node:legacy_redirects']);
    $metadata->addCacheContexts(['url.query_args.pagers']);
    foreach ($query->execute() as $row) {
      $url = Url::fromRoute('entity.node.canonical', ['node' => $row->dest])->toString(TRUE);
      $metadata->addCacheableDependency($url);
      $results[] = [
        'source' => $row->source,
        'dest' => sprintf('https://www.mass.gov%s', $url->getGeneratedUrl()),
      ];
    }

    $response = new CacheableJsonResponse([
      'results' => $results,
      'pager' => [
        'current_page' => $this->pagerParams->findPage(),
        'items_per_page' => self::LIMIT,
        'total_items' => $count,
      ],
    ]);
    $response->getCacheableMetadata()->addCacheableDependency($metadata);
    return $response;
  }

}

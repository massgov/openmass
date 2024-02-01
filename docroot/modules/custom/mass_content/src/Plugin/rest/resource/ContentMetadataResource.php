<?php

namespace Drupal\mass_content\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\taxonomy\Entity\Term;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a listing of all content node IDs w/ IDs of parent nodes.
 *
 * @RestResource(
 *   id = "content_metadata_resource_v2",
 *   label = @Translation("Content Metadata Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/content-metadata"
 *   }
 * )
 */
class ContentMetadataResource extends ResourceBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Symfony\Component\HttpFoundation\RequestStack definition.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $requestStack;

  /**
   * Alias Manager definition.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  private $aliasManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack, AliasManagerInterface $alias_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
    $this->aliasManager = $alias_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('path_alias.manager')
    );
  }

  /**
   * Responds to entity GET requests.
   *
   * GET query params:
   *   limit - max number of results to return.
   *   offset - number of results to skip.
   *
   * @return \Drupal\rest\ResourceResponse
   *   API response containing metadata & results keys.
   */
  public function get() {

    // Transforms a Node item into a simple object,
    // containing an ID attribute, and parent IDs array.
    $extract_meta_from_nodes = function ($item, $flag_show_flat, $traversal_depth) {
      // References to various 'parent reference fields'.
      $get_target_id = function ($r) {
        return $r['target_id'];
      };

      // Node owner/author data.
      $node_owner = $item->getOwner();
      if (!$node_owner->field_user_org->isEmpty()) {
        $org = Term::load($node_owner->field_user_org->target_id);
      }
      $organization = [];
      if (!empty($org)) {
        $organization = [
          'name' => $org->name->value,
          'uuid' => $org->uuid(),
          'id' => $org->tid->value,
        ];
      }

      // Mass Gov's every content type has a multi value field `field_organizations`
      // that has references to one or more `org_page`. In the author interface we
      // describe this field as "this content is offered by the following organizations",
      // so we mark it with similar key in the content API (ie "offered_by_organizations").
      // Doing so helps in clear separation from state organization taxonomy term too
      // which we would hopefully soon deprecate.
      $offered_by_organizations = [];
      if ($item->hasField('field_organizations')) {
        /** @var \Drupal\node\Entity\Node[] $org_pages */
        $org_pages = $item->field_organizations->referencedEntities();
        foreach ($org_pages as $org_page) {
          array_push($offered_by_organizations, [
            'id' => $org_page->id(),
            'uuid' => $org_page->uuid(),
            'title' => $org_page->getTitle(),
          ]);
        }
      }

      $labels = [];
      if ($item->hasField('field_reusable_label')) {
        $l_refs = $item->field_reusable_label->referencedEntities();
        if (!empty($l_refs)) {
          foreach ($l_refs as $l_ref) {
            $labels[] = [
              'id' => $l_ref->id(),
              'name' => $l_ref->label(),
            ];
          }
        }
      }

      /*
       * Get any field_kpi_ fields for this entity.
       * See https://jira.mass.gov/browse/DP-16429.
       */
      $kpis = [];
      $info = $item->getFieldDefinitions();
      foreach ($info as $name => $field) {
        if (strpos($name, 'field_kpi_') !== FALSE) {
          $is_pct = strpos($name, '_pct') !== FALSE;
          $is_ctr = strpos($name, '_ctr') !== FALSE;
          $value = $item->get($name)->value;
          if ($value && ($is_ctr || $is_pct)) {
            $value = $value / 100;
          }
          $kpis[$name] = $value;
        }
      }

      $roles = array_map($get_target_id, $node_owner->roles->getValue());
      $mod_state = $item->moderation_state->getValue();
      $path = $this->aliasManager->getAliasByPath('/node/' . $item->nid->value);
      $res = [
        'descendants' => [],
        'id' => $item->nid->value,
        'node_path' => $path,
        'uuid' => $item->uuid->value,
        'title' => $item->title->value,
        'date_created' => $item->created->value,
        'date_changed' => $item->changed->value,
        'intended_audience' => $item->field_intended_audience ?? '_none',
        'content_type' => $item->getType(),
        'offered_by_organizations' => $offered_by_organizations,
        'published' => $item->status->value == 1,
        'promoted' => $item->promote->value == 1,
        'sticky' => $item->sticky->value == 1,
        'moderation_state' => $mod_state,
        'author' => [
          'id' => $node_owner->uid->value,
          'uuid' => $node_owner->uuid->value,
          'name' => $node_owner->name->value,
          'organization' => $organization,
          'is_intern' => $node_owner->field_user_intern->value == 1,
          'roles' => $roles,
        ],
        'labels' => $labels,
        'kpis' => $kpis,
      ];
//
//      if ($flag_show_flat) {
//        $res['descendants'] = $this->descendantManager->getChildrenFlat($item->nid->value, $traversal_depth);
//      }
//      else {
//        $res['descendants'] = $this->descendantManager->getChildrenTree($item->nid->value, $traversal_depth);
//      }

      return $res;
    };

    // Set query params.
    $query_params = $this->requestStack->getCurrentRequest()->query->all();
    $offset_num = 0;
    $record_limit = 1000;
    $descendant_format = TRUE;
//    $traversal_depth = $this->descendantManager::MAX_DEPTH;
    $traversal_depth = 0;
    if (isset($query_params['depth']) && (int) $query_params['depth'] < $traversal_depth && (int) $query_params['depth'] > 0) {
      $traversal_depth = (int) $query_params['depth'];
    }
    // This function is invoked by the rest API endpoint `api/v1/content-metadata` where we prefer to
    // show descendants as a flat list (not a nested tree) because external systems that consume
    // the API output expect a flat tree. So we initialize $show_flat flag to TRUE and set it to false
    // only if an explicit parameter `?flat=0` is passed to the API endpoint.
    // NOTE: On the descendant test UI page `/admin/config/content/descendants` by default we show a tree, and a
    // flat list can be see by passing in a url parameter `?flat=1`.
    $show_flat = TRUE;
    if (isset($query_params['offset'])) {
      $offset_num = (int) $query_params['offset'];
    }
    if (isset($query_params['limit'])) {
      $record_limit = min($record_limit, (int) $query_params['limit']);
    }
    if (isset($query_params['descendant_format'])) {
      $descendant_format = ($query_params['descendant_format'] != 'depth');
    }
    if (isset($query_params['content_types'])) {
      $content_types = explode(',', $query_params['content_types']);
    }
    if (isset($query_params['published'])) {
      $published = 1;
    }
    if (isset($query_params['flat']) && $query_params['flat'] == 'no') {
      $show_flat = FALSE;
    }

    $node_storage = $this->entityTypeManager->getStorage('node');
    $query = $node_storage->getQuery();
    if (isset($published)) {
      $query->condition('status', $published);
    }
    if (!empty($content_types)) {
      $query->condition('type', $content_types, 'IN');
    }
    $query->range($offset_num, $record_limit);
    $query->sort('nid', 'ASC');
    $entity_ids = $query->accessCheck(FALSE)->execute();

    $nodes = $node_storage->loadMultiple($entity_ids);
    $results = [];

    foreach ($nodes as $n) {
      $results[] = $extract_meta_from_nodes($n, $show_flat, $traversal_depth);
    }

    $output = [
      'data' => $results,
      'metadata' => [
        'resultset' => [
          'count' => count($results),
          'limit' => $record_limit,
          'offset' => $offset_num,
        ],
      ],
    ];

    // No caching.
    $cache_opts = [
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    return (new ResourceResponse($output))->addCacheableDependency($cache_opts);
  }

}

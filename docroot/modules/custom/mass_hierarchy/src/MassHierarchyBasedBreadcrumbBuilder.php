<?php

declare(strict_types=1);

namespace Drupal\mass_hierarchy;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_hierarchy\Storage\QueryBuilderFactory;
use Drupal\entity_hierarchy\Storage\RecordCollectionCallable;
use Drupal\entity_hierarchy_breadcrumb\HierarchyBasedBreadcrumbBuilder;
use Drupal\mass_content\Entity\Bundle\media\DocumentBundle;
use Drupal\mass_microsites\NearestMicrositeLookup;
use Drupal\node\Entity\Node;

/**
 * Entity hierarchy based breadcrumb builder overrides.
 */
class MassHierarchyBasedBreadcrumbBuilder extends HierarchyBasedBreadcrumbBuilder {

  /**
   * The nearest microsite lookup service.
   *
   * @var \Drupal\mass_microsites\NearestMicrositeLookup
   */
  protected $nearestMicrositeLookup;

  /**
   * The admin context.
   *
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected $adminContext;

  public function __construct(
    EntityFieldManagerInterface $entity_field_manager,
    QueryBuilderFactory $storage_factory,
    NearestMicrositeLookup $nearest_microsite_lookup,
    AdminContext $admin_context,
  ) {
    parent::__construct($entity_field_manager, $storage_factory);
    $this->nearestMicrositeLookup = $nearest_microsite_lookup;
    $this->adminContext = $admin_context;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match, ?CacheableMetadata $cacheable_metadata = NULL) : bool {

    if ($this->adminContext->isAdminRoute($route_match->getRouteObject())) {
      if ($route_match->getRouteName() === 'entity.media.edit_form' && $route_match->getParameter('media') instanceof DocumentBundle) {
        return TRUE;
      }
      elseif ($route_match->getRouteName() !== 'entity.node.edit_form') {
        return FALSE;
      }
    }

    if ($route_match->getRouteName() == 'view.collection_all.page_all') {
      return TRUE;
    }

    if ($route_match->getRouteName() == "view.locations.page" && $route_match->getParameter('node')) {
      return TRUE;
    }
    $route_entity = $this->getEntityFromRouteMatch($route_match);
    if (!$route_entity || !$route_entity instanceof ContentEntityInterface || !$this->getHierarchyFieldFromEntity($route_entity)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheContexts(['route']);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $route_entity */
    if (!empty($route_match->getRouteObject()->getOption('parent_node'))) {
      $route_entity = $route_match->getRouteObject()->getOption('parent_node');
    }
    elseif ($route_match->getRouteName() == "view.locations.page") {
      // Views argument upcasting is still an issue in Drupal core.
      if (is_numeric($route_match->getParameter('node'))) {
        $route_entity = Node::load($route_match->getParameter('node'));
      }
      else {
        $route_entity = $route_match->getParameter('node');
      }
    }
    elseif ($route_match->getRouteName() == "view.collection_all.page_all") {
      $collection = mass_content_get_collection_from_current_page();
      $breadcrumb->addCacheableDependency($collection);
      /** @var \Drupal\entity_hierarchy\Plugin\Field\FieldType\EntityReferenceHierarchyFieldItemList */
      $field_primary_parent = $collection->field_primary_parent;
      /** @var \Drupal\node\Entity\Node[] */
      $referenced_entities = $field_primary_parent->referencedEntities();
      if (!$referenced_entities) {
        return $breadcrumb->setLinks([]);
      }
      $route_entity = end($referenced_entities);
    }
    else {
      $route_entity = $this->getEntityFromRouteMatch($route_match);
    }

    $breadcrumb->addCacheableDependency($route_entity);
    if ($route_entity instanceof DocumentBundle) {
      $links = [];
      $text = $route_entity->field_title->value;
      $links[] = Link::fromTextAndUrl($text, $route_entity->toUrl());
      array_unshift($links, Link::createFromRoute(new TranslatableMarkup('Home'), '<front>'));
      $breadcrumb->setLinks($links);
      return $breadcrumb;
    }

    $entity_type = $route_entity->getEntityTypeId();
    $storage = $this->queryBuilderFactory->get($this->getHierarchyFieldFromEntity($route_entity), $entity_type);
    $ancestors = $storage->findAncestors($route_entity)->filter(RecordCollectionCallable::viewLabelAccessFilter(...))->buildTree();

    /** @var \Drupal\entity_hierarchy_microsite\Entity\MicrositeInterface; $nearest_microsite */
    $nearest_microsite = $this->nearestMicrositeLookup->getNearestMicrosite($route_entity);
    $microsite_root = $nearest_microsite?->getHome();
    $links = [];
    foreach ($ancestors as $ancestor_entity) {
      $entity = $ancestor_entity->getEntity();

      if ($microsite_root && empty($links) && $entity->id() !== $microsite_root->id()) {
        // If we're in a microsite, and the entity is not the microsite root, and we havn't found the root yet, skip.
        continue;
      }

      $breadcrumb->addCacheableDependency($entity);

      // Show just the label for the entity from the route.
      if ($entity->id() == $route_entity->id() && $route_match->getParameter('node') instanceof ContentEntityInterface && $entity->id() == $route_match->getParameter('node')->id()) {
        // Override from extended class build() method: Use field_short_title if
        // it's set.
        $text = $entity->label();
        if ($entity instanceof Node
          && $entity->hasField('field_short_title')
          && !$entity->get('field_short_title')->isEmpty()) {
          $text = $entity->get('field_short_title')->value;
        }
        if ($route_match->getRouteName() == 'mass_more_lists.events_past' || $route_match->getRouteName() == 'mass_more_lists.events_upcoming' || $route_match->getRouteName() == "view.locations.page") {
          $links[] = Link::fromTextAndUrl($text, $entity->toUrl());
        }
        else {
          $links[] = Link::createFromRoute($text, '<none>');
        }

      }
      else {
        if ($entity->hasField('field_short_title') && !$entity->get('field_short_title')->isEmpty()) {
          $text = $entity->get('field_short_title')->value;
          $links[] = Link::fromTextAndUrl($text, $entity->toUrl());
        }
        else {
          $links[] = $entity->toLink();
        }
      }
    }

    if (!$microsite_root) {
      // For microsites, the home link is the microsite root.
      // If we're not in a microsite, add the home link.
      array_unshift($links, Link::createFromRoute(new TranslatableMarkup('Home'), '<front>'));
    }
    $breadcrumb->setLinks($links);
    return $breadcrumb;
  }

}

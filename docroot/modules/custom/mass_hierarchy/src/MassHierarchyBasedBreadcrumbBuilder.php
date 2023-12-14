<?php

declare(strict_types=1);

namespace Drupal\mass_hierarchy;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_hierarchy_breadcrumb\HierarchyBasedBreadcrumbBuilder;
use Drupal\node\Entity\Node;

/**
 * Entity hierarchy based breadcrumb builder overrides.
 */
class MassHierarchyBasedBreadcrumbBuilder extends HierarchyBasedBreadcrumbBuilder {

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    if ($this->adminContext->isAdminRoute($route_match->getRouteObject()) && $route_match->getRouteName() !== 'entity.node.edit_form') {
      return FALSE;
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
    if (!empty($route_match->getParameter('parent_node'))) {
      $route_entity = $route_match->getParameter('parent_node');
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
      $field_primary_parent = $collection->getPrimaryParent();
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
    $entity_type = $route_entity->getEntityTypeId();
    $storage = $this->storageFactory->get($this->getHierarchyFieldFromEntity($route_entity), $entity_type);
    $ancestors = $storage->findAncestors($this->nodeKeyFactory->fromEntity($route_entity));
    // Pass in the breadcrumb object for caching.
    $ancestor_entities = $this->mapper->loadAndAccessCheckEntitysForTreeNodes($entity_type, $ancestors, $breadcrumb);

    $links = [];
    foreach ($ancestor_entities as $ancestor_entity) {
      if (!$ancestor_entities->contains($ancestor_entity)) {
        // Doesn't exist or is access hidden.
        continue;
      }
      $entity = $ancestor_entities->offsetGet($ancestor_entity);
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

    array_unshift($links, Link::createFromRoute(new TranslatableMarkup('Home'), '<front>'));
    $breadcrumb->setLinks($links);
    return $breadcrumb;
  }

}

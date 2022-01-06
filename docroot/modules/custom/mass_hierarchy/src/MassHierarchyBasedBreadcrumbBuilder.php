<?php

namespace Drupal\mass_hierarchy;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_hierarchy_breadcrumb\HierarchyBasedBreadcrumbBuilder;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use SplObjectStorage;

/**
 * Entity hierarchy based breadcrumb builder overrides.
 */
class MassHierarchyBasedBreadcrumbBuilder extends HierarchyBasedBreadcrumbBuilder {

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheContexts(['route']);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $route_entity */
    $route_entity = $this->getEntityFromRouteMatch($route_match);

    $entity_type = $route_entity->getEntityTypeId();
    $storage = $this->storageFactory->get($this->getHierarchyFieldFromEntity($route_entity), $entity_type);
    $ancestors = $storage->findAncestors($this->nodeKeyFactory->fromEntity($route_entity));
    // Pass in the breadcrumb object for caching.
    $ancestor_entities = $this->mapper->loadAndAccessCheckEntitysForTreeNodes($entity_type, $ancestors, $breadcrumb);

    return $this->buildBreadcrumb($breadcrumb, $route_entity, $ancestor_entities);
  }

  /**
   * Build the breadcrumb based on an entity.
   *
   * @param \Drupal\node\NodeInterface $entity_passed
   *   The entity to use in building the breadcrumb.
   *
   * @return \Drupal\Core\Breadcrumb\Breadcrumb
   *   The breadcrumb.
   */
  public function buildFromEntity(NodeInterface $entity_passed) {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheContexts(['route']);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $route_entity */
    $route_entity = $entity_passed;

    $entity_type = $route_entity->getEntityTypeId();
    $storage = $this->storageFactory->get($this->getHierarchyFieldFromEntity($route_entity), $entity_type);
    $ancestors = $storage->findAncestors($this->nodeKeyFactory->fromEntity($route_entity));
    // Pass in the breadcrumb object for caching.
    $ancestor_entities = $this->mapper->loadAndAccessCheckEntitysForTreeNodes($entity_type, $ancestors, $breadcrumb);

    return $this->buildBreadcrumb($breadcrumb, $route_entity, $ancestor_entities);
  }

  /**
   * Build the breadcrumb based on an entity.
   *
   * @param \Drupal\Core\Breadcrumb\Breadcrumb $breadcrumb
   *   The breadcrumb.
   * @param \Drupal\Core\Entity\ContentEntityInterface $route_entity
   *   The route entity.
   * @param \SplObjectStorage $ancestor_entities
   *   The ancestor entities.
   *
   * @return \Drupal\Core\Breadcrumb\Breadcrumb
   *   The updated breadcrumb.
   */
  public function buildBreadcrumb(Breadcrumb $breadcrumb, ContentEntityInterface $route_entity, SplObjectStorage $ancestor_entities) {
    $links = [];
    if ($ancestor_entities->count() > 0) {
      foreach ($ancestor_entities as $ancestor_entity) {
        if (!$ancestor_entities->contains($ancestor_entity)) {
          // Doesn't exist or is access hidden.
          continue;
        }
        $entity = $ancestor_entities->offsetGet($ancestor_entity);
        $links[] = $this->buildBreadcrumbLink($entity, $route_entity);
      }
    }
    else {
      // If there are no ancestors in the hierarchy, add the current entity.
      // This is mostly applicable to the breadcrumb preview. A selected parent
      // will not have ancestors if it's never been assigned as a parent.
      $links[] = $this->buildBreadcrumbLink($route_entity, $route_entity);
    }

    array_unshift($links, Link::createFromRoute(new TranslatableMarkup('Home'), '<front>'));
    $breadcrumb->setLinks($links);
    return $breadcrumb;
  }

  /**
   * Build the breadcrumb based on an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Entity\ContentEntityInterface $route_entity
   *   The route entity.
   *
   * @return \Drupal\Core\Link
   *   The breadcrumb link.
   */
  public function buildBreadcrumbLink(ContentEntityInterface $entity, ContentEntityInterface $route_entity) {
    // Show just the label for the entity from the route.
    if ($entity->id() == $route_entity->id()) {
      // Override from extended class build() method: Use field_short_title if
      // it's set.
      $text = $entity->label();
      if ($entity instanceof Node
        && $entity->hasField('field_short_title')
        && !$entity->get('field_short_title')->isEmpty()) {
        $text = $entity->get('field_short_title')->value;
      }
      return Link::createFromRoute($text, '<none>');
    }
    else {
      if ($entity->hasField('field_short_title') && !$entity->get('field_short_title')->isEmpty()) {
        $text = $entity->get('field_short_title')->value;
        return Link::fromTextAndUrl($text, $entity->toUrl());
      }
      else {
        return $entity->toLink();
      }
      // End override.
    }
  }

}

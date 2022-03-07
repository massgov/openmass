<?php

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
    if (isset($route_match->parent_node)) {
      $route_entity = $route_match->parent_node;
    }
    else {
      $route_entity = $this->getEntityFromRouteMatch($route_match);
    }
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
        $links[] = Link::createFromRoute($text, '<none>');
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
